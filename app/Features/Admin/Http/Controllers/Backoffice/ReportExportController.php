<?php

namespace App\Features\Admin\Http\Controllers\Backoffice;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV report exports for the backoffice. Strictly read-only, bounded to 5,000 rows,
 * newest first, and every export is written to the audit log (who / what / filters).
 * No export touches payment rails or personal payout secrets beyond what the
 * moderation screens already show.
 */
final class ReportExportController
{
    private const MAX_ROWS = 5000;

    private static function field(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return (string) ($value ?? '');
    }

    public function download(Request $request, string $report): StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin && $admin->isActive(), 403);

        abort_unless(in_array($report, ['users', 'transactions', 'ledger', 'withdrawals'], true), 404);

        $from = $request->query('from');
        $to = $request->query('to');
        $fromDate = $from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from.' 00:00:00' : null;
        $toDate = $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to.' 23:59:59' : null;

        [$filename, $headers, $rows] = match ($report) {
            'users' => $this->users($admin, $fromDate, $toDate),
            'transactions' => $this->transactions($fromDate, $toDate),
            'ledger' => $this->ledger($fromDate, $toDate),
            'withdrawals' => $this->withdrawals($fromDate, $toDate),
        };

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'report.exported',
            'entity_type' => 'report',
            'entity_id' => $report,
            'after_values' => ['report' => $report, 'from' => $fromDate, 'to' => $toDate, 'max_rows' => self::MAX_ROWS],
            'created_at' => now(),
        ]);

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // Excel-compatible with UTF-8 BOM.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function dateRange($query, string $column, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }

    private function users($admin, ?string $from, ?string $to): array
    {
        $query = User::withTrashed()->orderByDesc('id');
        $this->dateRange($query, 'created_at', $from, $to);
        if (! $admin->isSuperAdmin()) {
            // Standard admins do not see soft-deleted accounts in exports.
            $query = User::query()->orderByDesc('id');
            $this->dateRange($query, 'created_at', $from, $to);
        }

        return [
            'users.csv',
            ['ID', 'Full name', 'Username', 'Email', 'Phone', 'Country', 'Role', 'Status', 'Banned', 'Preferred currency', 'Joined'],
            function () use ($query) {
                foreach ($query->limit(self::MAX_ROWS)->cursor() as $u) {
                    yield [$u->id, $u->full_name, $u->username, $u->email, $u->phone, $u->country_code, self::field($u->role), self::field($u->status), $u->is_banned ? 'yes' : 'no', $u->preferred_currency, $u->created_at?->toDateTimeString()];
                }
            },
        ];
    }

    private function transactions(?string $from, ?string $to): array
    {
        $query = Transaction::query()->with('user:id,username')->orderByDesc('id');
        $this->dateRange($query, 'created_at', $from, $to);

        return [
            'transactions.csv',
            ['ID', 'User', 'Type', 'Direction', 'Amount', 'Fee', 'Wallet', 'Status', 'Reference', 'Created'],
            function () use ($query) {
                foreach ($query->limit(self::MAX_ROWS)->cursor() as $t) {
                    yield [$t->id, $t->user?->username, self::field($t->type), $t->direction, $t->amount, $t->fee, $t->wallet, $t->status, $t->reference, $t->created_at?->toDateTimeString()];
                }
            },
        ];
    }

    private function ledger(?string $from, ?string $to): array
    {
        $query = LedgerEntry::query()->with('wallet:id,user_id,wallet_type,currency')->orderByDesc('id');
        $this->dateRange($query, 'created_at', $from, $to);

        return [
            'ledger.csv',
            ['ID', 'Wallet', 'Currency', 'User ID', 'Entry type', 'Direction', 'Amount (minor)', 'Status', 'Idempotency key', 'Description', 'Posted'],
            function () use ($query) {
                foreach ($query->limit(self::MAX_ROWS)->cursor() as $e) {
                    yield [$e->id, $e->wallet?->wallet_type, $e->wallet?->currency, $e->wallet?->user_id, $e->entry_type, $e->direction, $e->amount_minor, self::field($e->status), $e->idempotency_key, $e->description, $e->posted_at?->toDateTimeString()];
                }
            },
        ];
    }

    private function withdrawals(?string $from, ?string $to): array
    {
        $query = WithdrawalRequest::query()->with('user:id,username')->orderByDesc('id');
        $this->dateRange($query, 'created_at', $from, $to);

        return [
            'withdrawals.csv',
            ['ID', 'User', 'Amount (minor)', 'Fee (minor)', 'Currency', 'Status', 'Provider reference', 'Requested', 'Processed'],
            function () use ($query) {
                foreach ($query->limit(self::MAX_ROWS)->cursor() as $w) {
                    yield [$w->id, $w->user?->username, $w->amount_minor, $w->fee_minor, $w->currency, self::field($w->status), $w->provider_reference, $w->requested_at?->toDateTimeString(), $w->processed_at?->toDateTimeString()];
                }
            },
        ];
    }
}
