<?php

namespace App\Features\Wallets\Http\Controllers\Api\V1;

use App\Application\Money\CurrencyMinorUnits;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AccountHistoryController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'category' => ['sometimes', 'in:all,review,share,payment,withdrawal,referral,bonus,other'],
            'status' => ['sometimes', 'in:all,posted,completed,approved,pending,failed,rejected,cancelled,expired'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 5);
        $baseQuery = Transaction::query()->where('user_id', $request->user()->id);
        $this->applyFilters($baseQuery, $validated);

        $transactions = (clone $baseQuery)
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        $withdrawalIds = $transactions->getCollection()
            ->filter(fn (Transaction $transaction): bool => $transaction->type->value === 'withdrawal'
                && ($transaction->meta['source_type'] ?? null) === 'withdrawal_request'
                && isset($transaction->meta['source_id']))
            ->map(fn (Transaction $transaction): int => (int) $transaction->meta['source_id'])
            ->unique()->values();
        $withdrawalSnapshots = $withdrawalIds->isEmpty()
            ? collect()
            : WithdrawalRequest::query()->whereIn('id', $withdrawalIds)->get()->keyBy('id');

        $transactions->through(static function (Transaction $transaction) use ($withdrawalSnapshots): array {
            $meta = $transaction->meta ?? [];
            $currency = strtoupper((string) ($meta['currency'] ?? 'USD'));
            $amountMinor = CurrencyMinorUnits::toMinor($transaction->amount, $currency);
            $feeMinor = CurrencyMinorUnits::toMinor($transaction->fee, $currency);
            if ($transaction->type->value === 'withdrawal'
                && ($meta['source_type'] ?? null) === 'withdrawal_request') {
                $withdrawal = $withdrawalSnapshots->get((int) ($meta['source_id'] ?? 0));
                if ($withdrawal) {
                    // Older ledger rows reserved amount + fee. Present the
                    // actual request amount as the debit and fee as a split.
                    $amountMinor = (int) $withdrawal->amount_minor;
                    $feeMinor = (int) $withdrawal->fee_minor;
                    $meta['withdrawal_amount_minor'] = $amountMinor;
                    $meta['fee_minor'] = $feeMinor;
                    $meta['payout_minor'] = max(0, $amountMinor - $feeMinor);
                }
            }

            return [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'direction' => $transaction->direction,
                'amount_minor' => $amountMinor,
                'fee_minor' => $feeMinor,
                'currency' => $currency,
                'currency_minor_units' => $meta['currency_minor_units'] ?? CurrencyMinorUnits::precision($currency),
                'wallet' => $transaction->wallet,
                'status' => $transaction->status,
                'reference' => $transaction->reference,
                'description' => $transaction->description,
                'meta' => $meta,
                'created_at' => $transaction->created_at,
            ];
        });

        $userTransactions = Transaction::query()->where('user_id', $request->user()->id);
        $currencyExpression = DB::connection()->getDriverName() === 'mysql'
            ? "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.currency')), 'USD')"
            : "COALESCE(json_extract(meta, '$.currency'), 'USD')";
        $successRows = (clone $userTransactions)
            ->whereIn('status', [...TransactionStatus::settledValues(), 'posted', 'success', 'successful'])
            ->where('direction', 'credit')
            ->whereIn('type', ['app_review', 'ads_share', 'referral_bonus', 'rank_earning', 'social_follow_reward'])
            ->selectRaw("{$currencyExpression} as currency, SUM(amount) as total_amount")
            ->groupBy('currency')
            ->get();
        $earnedByCurrency = [];
        foreach ($successRows as $row) {
            $currency = strtoupper((string) $row->currency);
            $amountMinor = CurrencyMinorUnits::toMinor((float) $row->total_amount, $currency);
            $earnedByCurrency[$currency] = $amountMinor;
        }

        // Count only withdrawals that were actually paid. Reservations later
        // rejected by an admin are not platform spending.
        $withdrawalRows = WithdrawalRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'paid')
            ->selectRaw('currency, SUM(amount_minor) as total_amount_minor')
            ->groupBy('currency')
            ->get();
        $spentByCurrency = [];
        foreach ($withdrawalRows as $row) {
            $currency = strtoupper((string) $row->currency);
            $spentByCurrency[$currency] = (int) $row->total_amount_minor;
        }

        return response()->json([
            'data' => $transactions,
            'meta' => [
                'stats' => [
                    'total' => (clone $userTransactions)->count(),
                    'this_month' => (clone $userTransactions)->where('created_at', '>=', now()->startOfMonth())->count(),
                    'earned_minor_by_currency' => $earnedByCurrency,
                    'spent_minor_by_currency' => $spentByCurrency,
                ],
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $category = $filters['category'] ?? 'all';
        $types = match ($category) {
            'review' => ['app_review'],
            'share' => ['ads_share'],
            'payment' => ['top_up_fund', 'subscription_plan', 'package_addon', 'fund_transfer', 'vtu_purchase', 'campaign_order'],
            'withdrawal' => ['withdrawal'],
            'referral' => ['referral_bonus'],
            'bonus' => ['referral_bonus', 'social_follow_reward', 'rank_earning'],
            default => null,
        };
        if ($types !== null) {
            $query->whereIn('type', $types);
        } elseif ($category === 'other') {
            $query->whereNotIn('type', [
                'app_review', 'ads_share', 'top_up_fund', 'subscription_plan', 'package_addon',
                'fund_transfer', 'vtu_purchase', 'campaign_order', 'withdrawal', 'referral_bonus',
                'social_follow_reward', 'rank_earning',
            ]);
        }

        $status = TransactionStatus::normalize((string) ($filters['status'] ?? 'all'));
        if ($status === TransactionStatus::Completed->value) {
            // `posted` is the pre-Phase-2 spelling of a settled row and older
            // clients still send it; both spellings select the same set.
            $query->whereIn('status', [...TransactionStatus::settledValues(), 'posted', 'success', 'successful']);
        } elseif ($status === TransactionStatus::Cancelled->value) {
            $query->whereIn('status', ['cancelled', 'canceled']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date'].' 00:00:00');
        }
        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date'].' 23:59:59.999999');
        }
    }
}
