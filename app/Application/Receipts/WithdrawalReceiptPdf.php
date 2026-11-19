<?php

namespace App\Application\Receipts;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\WithdrawalRequest;
use Barryvdh\DomPDF\Facade\Pdf;

final class WithdrawalReceiptPdf
{
    /** Render one authorized withdrawal as a downloadable PDF document. */
    public function render(WithdrawalRequest $withdrawal): string
    {
        $details = is_array($withdrawal->payout_details) ? $withdrawal->payout_details : [];
        $optionFields = is_array($withdrawal->option?->fields) ? $withdrawal->option->fields : [];
        $rows = [];

        $accountName = $details['account_name'] ?? $details['name'] ?? $withdrawal->user?->full_name;
        self::appendRow($rows, 'Name', $accountName);

        $bank = $details['bank_name'] ?? $details['bank'] ?? null;
        self::appendRow($rows, 'Bank', $bank);
        if (! filled($bank)) {
            self::appendRow($rows, 'Method', $withdrawal->option?->name);
        }

        $accountNumber = $details['account_number'] ?? null;
        self::appendRow($rows, 'Account No.', $accountNumber);

        $hiddenKeys = ['account_name', 'name', 'bank_name', 'bank', 'account_number', 'bank_code', 'bank_id'];
        foreach ($optionFields as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : (string) $field;
            if ($key === '' || in_array($key, $hiddenKeys, true) || ! array_key_exists($key, $details)) {
                continue;
            }

            $value = $details[$key];
            if (is_scalar($value)) {
                self::appendRow($rows, self::label($key), $value);
            }
        }

        $walletType = $withdrawal->wallet?->wallet_type;
        $walletType = $walletType instanceof \BackedEnum
            ? (string) $walletType->value
            : (string) ($walletType ?? 'wallet');
        $walletName = match ($walletType) {
            'share' => 'Share Wallet',
            'review_group_b' => 'Play Store Review Wallet',
            'review_group_c' => 'Apple Store Review Wallet',
            'referral' => 'Referral Wallet',
            'bonus' => 'Bonus Wallet',
            'topup_fund' => 'Top-up Fund Wallet',
            default => self::label($walletType).' Wallet',
        };
        $reference = $withdrawal->provider_reference;
        if (! filled($reference)) {
            $reference = $this->referenceFromKey((string) $withdrawal->idempotency_key, (int) $withdrawal->id);
        }

        $currency = strtoupper((string) $withdrawal->currency);
        $precision = CurrencyMinorUnits::precision($currency);
        $amount = self::money((int) $withdrawal->amount_minor, $currency, $precision);
        $fee = self::money((int) $withdrawal->fee_minor, $currency, $precision);
        $logoPath = public_path('images/logo-long.png');
        $googleBadgePath = public_path('images/pdf/google-play-badge.png');
        $appleBadgePath = public_path('images/pdf/app-store-badge.png');
        $status = is_object($withdrawal->status) ? $withdrawal->status->value : (string) $withdrawal->status;
        $rows[] = ['label' => 'Withdrawn From', 'value' => $walletName, 'tone' => null];
        $rows[] = ['label' => 'Withdrawal Fee', 'value' => $fee, 'tone' => null];
        $rows[] = ['label' => 'Reference No.', 'value' => (string) $reference, 'tone' => null];
        $rows[] = [
            'label' => 'Date',
            'value' => $withdrawal->requested_at?->format('d M Y, h:i A') ?? 'Date unavailable',
            'tone' => null,
        ];
        $rows[] = ['label' => 'Status', 'value' => self::label($status), 'tone' => self::statusTone($status)];
        if ($withdrawal->processed_at) {
            $rows[] = ['label' => 'Processed', 'value' => $withdrawal->processed_at->format('d M Y, h:i A'), 'tone' => null];
        }
        if (filled($withdrawal->failure_reason)) {
            $rows[] = ['label' => 'Reason', 'value' => (string) $withdrawal->failure_reason, 'tone' => 'red'];
        }

        $appUrl = (string) config('app.url');
        $domain = parse_url($appUrl, PHP_URL_HOST) ?: $appUrl;
        $brandName = (string) config('app.name', 'TLStore');

        return Pdf::loadView('pdf.withdrawal-receipt', [
            'brand' => [
                'name' => $brandName,
                'domain' => $domain,
                'logo' => $logoPath,
                'google_badge' => $googleBadgePath,
                'apple_badge' => $appleBadgePath,
            ],
            'receipt' => [
                'amount' => (int) $withdrawal->amount_minor / (10 ** $precision),
                'amount_minor' => (int) $withdrawal->amount_minor,
                'currency' => $currency,
                'status' => $status,
                'rows' => $rows,
                'reference' => $reference,
                'requested_at' => $withdrawal->requested_at,
                'generated_at' => now(),
                'processing_window' => '1-24hrs',
                'processing_note' => self::processingNote($status, $withdrawal->failure_reason, $brandName),
                'failure_reason' => $withdrawal->failure_reason,
            ],
        ])->setPaper('a4')->output();
    }

    public function fileName(WithdrawalRequest $withdrawal): string
    {
        $reference = $withdrawal->provider_reference
            ?: $this->referenceFromKey((string) $withdrawal->idempotency_key, (int) $withdrawal->id);

        return 'withdrawal-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $reference).'.pdf';
    }

    private static function money(int $minor, string $currency, int $precision): string
    {
        $major = $minor / (10 ** $precision);

        return $currency.' '.number_format($major, $precision, '.', ',');
    }

    private static function label(string $key): string
    {
        return ucwords(str_replace('_', ' ', $key));
    }

    /** @param list<array{label:string,value:string,tone:null|string}> $rows */
    private static function appendRow(array &$rows, string $label, mixed $value): void
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return;
        }

        $rows[] = ['label' => $label, 'value' => (string) $value, 'tone' => null];
    }

    private static function statusTone(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'paid', 'approved' => 'green',
            'failed', 'rejected', 'cancelled' => 'red',
            default => 'amber',
        };
    }

    private static function processingNote(string $status, ?string $reason, string $brand): string
    {
        return match (strtolower($status)) {
            'paid', 'completed' => 'Your withdrawal has been completed. Thank you for using '.$brand.'.',
            'failed', 'rejected', 'cancelled' => filled($reason)
                ? $reason
                : 'This withdrawal could not be completed. Please contact support if you need help.',
            default => 'Your withdrawal request was submitted successfully. You will be notified once it is completed.',
        };
    }

    private function referenceFromKey(string $key, int $id): string
    {
        $parts = explode('-', $key);
        $tail = end($parts);
        $tail = strtoupper((string) ($tail ?: $id));

        return str_starts_with($tail, 'WD-') ? $tail : 'WD-'.$tail;
    }
}
