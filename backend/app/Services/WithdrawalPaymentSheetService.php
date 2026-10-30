<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Exports\PaymentSheetExport;
use App\Models\Bank;
use App\Models\Withdrawal;
use App\Models\WithdrawalPaymentSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class WithdrawalPaymentSheetService
{
    public const array PROCESSORS = [
        'general' => 'General Bank Sheet',
        'flutterwave' => 'Flutterwave',
        'globus' => 'Globus Bank',
        'vfd' => 'VFD Microfinance Bank',
        'wema' => 'Wema Bank Plc',
        'uba' => 'United Bank for Africa',
    ];

    public const array FORMATS = [
        'csv' => 'CSV',
        'xlsx' => 'Excel (XLSX)',
        'xls' => 'Excel (XLS)',
        'pdf' => 'PDF',
    ];

    public function generate(array $ids, string $processor, string $format): ?array
    {
        return DB::transaction(function () use ($ids, $processor, $format): ?array {

            $withdrawals = Withdrawal::query()
                ->whereIn('id', $ids)
                ->where('status', WithdrawalStatus::PROCESSING->value)
                ->with('user:id,country')
                ->lockForUpdate()
                ->get();

            if ($withdrawals->isEmpty()) {
                return null;
            }

            $currency = $this->resolveCurrency($withdrawals, $processor);

            $sequence = (int) WithdrawalPaymentSheet::query()->lockForUpdate()->max('sequence_number') + 1;

            $sheet = WithdrawalPaymentSheet::create([
                'processor' => $processor,
                'sequence_number' => $sequence,

                'total_amount' => (float) $withdrawals->sum(fn (Withdrawal $w): float => (float) ($w->local_amount_paid ?? $w->amount_paid)),
                'currency' => $currency,
                'withdrawal_ids' => $withdrawals->pluck('id')->all(),
            ]);

            Withdrawal::query()->whereIn('id', $withdrawals->pluck('id'))->update(['exported_at' => now()]);

            return $this->render($sheet, $withdrawals, $processor, $format);
        });
    }

    public function reDownload(WithdrawalPaymentSheet $sheet, string $processor, string $format): array
    {

        return $this->render($sheet, $sheet->withdrawals(), $processor, $format);
    }

    /**
     * Read-only export of already-paid (COMPLETED) withdrawals — for reconciliation/records. Unlike
     * generate(), this does NOT persist a payment batch or stamp exported_at: completed payouts are
     * historical, so the sheet is rendered from an unsaved WithdrawalPaymentSheet and nothing changes.
     */
    public function generateCompleted(array $ids, string $processor, string $format): ?array
    {
        $withdrawals = Withdrawal::query()
            ->whereIn('id', $ids)
            ->where('status', WithdrawalStatus::COMPLETED->value)
            ->with('user:id,country')
            ->get();

        if ($withdrawals->isEmpty()) {
            return null;
        }

        $currency = $this->resolveCurrency($withdrawals, $processor);

        $sheet = new WithdrawalPaymentSheet([
            'processor' => $processor,
            'sequence_number' => WithdrawalPaymentSheet::getNextSequenceNumber(),
            'total_amount' => (float) $withdrawals->sum(fn (Withdrawal $w): float => (float) ($w->local_amount_paid ?? $w->amount_paid)),
            'currency' => $currency,
            'withdrawal_ids' => $withdrawals->pluck('id')->all(),
        ]);

        return $this->render($sheet, $withdrawals, $processor, $format);
    }

    protected function resolveCurrency(Collection $withdrawals, string $processor): string
    {
        $currencies = $withdrawals->map(fn (Withdrawal $w): string => (string) ($w->local_currency ?: 'NGN'))->unique()->values();

        if ($currencies->count() <= 1) {
            return (string) ($currencies->first() ?: 'NGN');
        }

        if ($currencies->contains('NGN')) {
            throw ValidationException::withMessages([
                'export' => 'NGN payouts must be exported on their own sheet — remove NGN or filter to a single currency ('.$currencies->implode(', ').' selected).',
            ]);
        }

        if ($processor !== 'general') {
            throw ValidationException::withMessages([
                'export' => 'The '.(self::PROCESSORS[$processor] ?? $processor).' template holds one currency ('.$currencies->implode(', ').' selected). Use the General Bank Sheet for a mixed-currency payout, or narrow to one currency.',
            ]);
        }

        return 'MIXED';
    }

    protected function render(WithdrawalPaymentSheet $sheet, Collection $withdrawals, string $processor, string $format): array
    {

        $this->resolveCurrency($withdrawals, $processor);

        $rows = $this->rowsFor($withdrawals);
        [$headings, $lines] = $this->tabular($processor, $rows);
        $fileName = $sheet->generateFileName($format, $processor);

        if ($format === 'pdf') {
            $currencyLabel = $sheet->currency ?: ((string) ($withdrawals->pluck('local_currency')->filter()->first() ?: 'NGN'));
            $localTotal = (float) ($sheet->total_amount ?: $withdrawals->sum(fn (Withdrawal $w): float => (float) ($w->local_amount_paid ?? $w->amount_paid)));

            $pdf = Pdf::loadView('pdf.payment-sheet', [
                'sheet' => $sheet,
                'headings' => $headings,
                'lines' => $lines,
                'processor' => self::PROCESSORS[$processor] ?? $processor,
                'generatedAt' => now(),
                'currencyLabel' => $currencyLabel,
                'localTotal' => $localTotal,
                'currencyTotals' => WithdrawalPaymentSheet::breakdown($withdrawals),
            ])->setPaper('a4', 'landscape');

            return ['content' => (string) $pdf->output(), 'file_name' => $fileName, 'mime_type' => 'application/pdf'];
        }

        if ($format === 'xlsx' || $format === 'xls') {
            $writer = $format === 'xls' ? ExcelFormat::XLS : ExcelFormat::XLSX;
            $mime = $format === 'xls'
                ? 'application/vnd.ms-excel'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $content = (string) Excel::raw(new PaymentSheetExport($headings, $this->neutralizeLines($lines)), $writer);

            return ['content' => $content, 'file_name' => $fileName, 'mime_type' => $mime];
        }

        return ['content' => $this->csv($headings, $lines), 'file_name' => $fileName, 'mime_type' => 'text/csv'];
    }

    protected function tabular(string $processor, \Illuminate\Support\Collection $rows): array
    {
        $headings = match ($processor) {
            'flutterwave' => ['Account Number', 'Bank', 'Amount', 'Debit Currency Amount', 'Narration'],
            'globus' => ['BeneficiaryName', 'BankCode', 'AccountNo', 'Amount', 'Narration', 'RefNumber'],
            'vfd' => ['Bank Code', 'Account Number', 'Amount', 'Narration'],
            'wema' => ['INSTITUTION', 'ACCOUNT NUMBER', 'ACCOUNT NAME', 'DESCRIPTION', 'AMOUNT', 'REFERENCE CODE'],
            'uba' => ['#RECORD_TYPE_ID', 'BANK_CODE', 'ACCOUNT_ID', 'BENEFICIARY_NAME', 'CURRENCY', 'AMOUNT', 'NARRATION'],

            default => ['Bank', 'Account Number', 'Account Name', 'Amount', 'Currency', 'Country', 'Narration'],
        };

        $lines = $rows->map(fn (array $row): array => match ($processor) {

            'flutterwave' => [$row['account_number'], $row['bank_tag'], '', $row['amount'], $row['narration']],
            'globus' => [$row['account_name'], $row['bank_code'], $row['account_number'], $row['amount'], $row['narration'], ''],
            'vfd' => [$row['institution_code'], $row['account_number'], $row['amount'], $row['narration']],
            'wema' => [$row['bank_code'], $row['account_number'], $row['account_name'], $row['narration'], $row['amount'], $row['reference']],

            'uba' => [$row['bank_code'] === '033' ? 'IFT' : 'NIP', $row['bank_code'], $row['account_number'], $row['account_name'], $row['currency'], $row['amount'], $row['narration']],
            default => [$row['bank_name'], $row['account_number'], $row['account_name'], $row['amount'], $row['currency'], $row['country'], $row['narration']],
        })->all();

        return [$headings, $lines];
    }

    protected function rowsFor(Collection $withdrawals): \Illuminate\Support\Collection
    {
        $institutionByCode = $this->institutionCodesFor($withdrawals);

        return $withdrawals->sortBy(function (Withdrawal $w): string {
            $meta = $w->meta ?? [];
            $currency = (string) ($w->local_currency ?: 'NGN');
            $country = (string) ($meta['country'] ?? $w->user?->country ?? '');

            return $currency.'|'.$country;
        })->map(function (Withdrawal $w) use ($institutionByCode): array {

            $meta = $w->meta ?? [];
            $bankCode = (string) ($meta['bank_code'] ?? '');
            $bankTag = ((string) ($meta['bank_tag'] ?? '')) ?: $bankCode;

            return [
                'account_number' => $meta['account_number'] ?? '',
                'account_name' => $meta['account_name'] ?? '',
                'bank_name' => $meta['bank_name'] ?? '',
                'bank_code' => $bankCode,
                // NIBSS institution code for sheets that require it (VFD); falls back to the CBN code.
                'institution_code' => ($institutionByCode[$bankCode] ?? null) ?: $bankCode,
                'bank_tag' => $bankTag,

                'amount' => number_format((float) ($w->local_amount_paid ?? $w->amount_paid), 2, '.', ''),
                'currency' => (string) ($w->local_currency ?: 'NGN'),
                'country' => (string) ($meta['country'] ?? $w->user?->country ?? ''),
                'reference' => $w->reference,

                'narration' => (string) ($meta['narration'] ?? $this->narration()),
            ];
        })->values();
    }

    /**
     * Map each withdrawal's bank code to its NIBSS institution code in one query (avoids N+1 under
     * the app's lazy-loading guard). Banks without an institution code are omitted.
     *
     * @return array<string, string>
     */
    protected function institutionCodesFor(Collection $withdrawals): array
    {
        $codes = $withdrawals
            ->map(fn (Withdrawal $w): string => (string) (($w->meta ?? [])['bank_code'] ?? ''))
            ->filter()
            ->unique()
            ->all();

        if ($codes === []) {
            return [];
        }

        return Bank::query()
            ->whereIn('code', $codes)
            ->whereNotNull('institution_code')
            ->pluck('institution_code', 'code')
            ->all();
    }

    protected function narration(): string
    {
        return config('settings.payment.narration') ?: (config('app.name').' Payout');
    }

    protected function csv(array $headings, array $lines): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headings, ',', '"', '');

        foreach ($this->neutralizeLines($lines) as $line) {
            fputcsv($handle, $line, ',', '"', '');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    protected function neutralizeLines(array $lines): array
    {
        return array_map(
            fn (array $line): array => array_map($this->neutralizeCell(...), $line),
            $lines,
        );
    }

    protected function neutralizeCell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
