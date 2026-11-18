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
        if (filled($accountName)) {
            $rows[] = ['label' => 'Name', 'value' => (string) $accountName];
        }

        $bank = $details['bank_name'] ?? $details['bank'] ?? null;
        if (filled($bank)) {
            $rows[] = ['label' => 'Bank', 'value' => (string) $bank];
        }

        $accountNumber = $details['account_number'] ?? null;
        if (filled($accountNumber)) {
            $rows[] = ['label' => 'Account No.', 'value' => (string) $accountNumber];
        }

        $hiddenKeys = ['account_name', 'name', 'bank_name', 'bank', 'account_number', 'bank_code', 'bank_id'];
        foreach ($optionFields as $field) {
            $key = is_array($field) ? (string) ($field['key'] ?? '') : (string) $field;
            if ($key === '' || in_array($key, $hiddenKeys, true) || ! array_key_exists($key, $details)) {
                continue;
            }

            $value = $details[$key];
            if (is_scalar($value) && filled((string) $value)) {
                $rows[] = ['label' => self::label($key), 'value' => (string) $value];
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
        $logoPath = public_path('web/icons/tlstore.jpg');
        $logo = is_file($logoPath)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return Pdf::loadView('pdf.withdrawal-receipt', [
            'brand' => config('app.name', 'TLStore'),
            'logo' => $logo,
            'generatedAt' => now(),
            'amount' => $amount,
            'currency' => $currency,
            'fee' => $fee,
            'status' => is_object($withdrawal->status) ? $withdrawal->status->value : (string) $withdrawal->status,
            'details' => $rows,
            'walletName' => $walletName,
            'reference' => $reference,
            'requestedAt' => $withdrawal->requested_at,
            'processedAt' => $withdrawal->processed_at,
            'failureReason' => $withdrawal->failure_reason,
            'appUrl' => config('app.url'),
            'supportEmail' => config('mail.from.address'),
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

    private function referenceFromKey(string $key, int $id): string
    {
        $parts = explode('-', $key);
        $tail = end($parts);

        return 'WD-'.strtoupper((string) ($tail ?: $id));
    }
}
