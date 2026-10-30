<?php

namespace App\Services\Agent;

use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The agent-facing token exports (a whole batch, or a selection from My Tokens).
 *
 * Previously these streams were returned straight out of Livewire actions, which cannot work in
 * the Telegram Mini App or the Android WebView: both hand a URL to a native downloader, and a
 * Livewire update response is not a URL. The streaming now lives here so a plain GET route can
 * serve it — and so the download link the page renders matches the file it produces, name included.
 */
class CouponExportService
{
    public const FORMATS = ['csv', 'txt', 'pdf'];

    public const SCOPES = ['all', 'active', 'used'];

    /** More ids than this and the export link would be an unsafe length for a GET request. */
    public const MAX_SELECTED_IDS = 500;

    public static function normalizeFormat(?string $format): string
    {
        return in_array($format, self::FORMATS, true) ? (string) $format : 'csv';
    }

    public static function normalizeScope(?string $scope): string
    {
        return in_array($scope, self::SCOPES, true) ? (string) $scope : 'all';
    }

    /** The file name a batch export will carry — the page needs it before the click. */
    public function batchFilename(CouponBatch $batch, string $scope, string $format): string
    {
        $scope = self::normalizeScope($scope);
        $format = self::normalizeFormat($format);

        $name = 'nextel-batch-'.$batch->reference.($scope === 'all' ? '' : '-'.$scope);

        return $name.'.'.$format;
    }

    /** The file name a token export will carry. */
    public function tokensFilename(): string
    {
        return 'nextel-tokens-'.now()->format('Ymd-His').'.csv';
    }

    /** Every coupon in a batch, narrowed by scope, with the relations the export columns need. */
    public function coupons(CouponBatch $batch, string $scope): Collection
    {
        $scope = self::normalizeScope($scope);

        return $batch->coupons()
            ->when($scope === 'active', fn ($q) => $q->whereNull('user_id'))
            ->when($scope === 'used', fn ($q) => $q->whereNotNull('user_id'))
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->latest()
            ->get();
    }

    public function batch(CouponBatch $batch, string $scope, string $format): StreamedResponse
    {
        $scope = self::normalizeScope($scope);
        $format = self::normalizeFormat($format);

        $coupons = $this->coupons($batch, $scope);
        $filename = $this->batchFilename($batch, $scope, $format);

        if ($format === 'txt') {
            $body = $coupons->pluck('code')->implode(PHP_EOL);

            return response()->streamDownload(fn () => print ($body.PHP_EOL), $filename, ['Content-Type' => 'text/plain']);
        }

        if ($format === 'pdf') {
            $used = $batch->coupons()->whereNotNull('user_id')->count();
            $total = $batch->coupons()->count();

            $pdf = Pdf::loadView('pdf.tokens', [
                'batch' => $batch,
                'planName' => $batch->displayName(),
                'coupons' => $coupons,
                'scopeLabel' => $scope === 'all' ? 'All' : ucfirst($scope),
                'stats' => ['total' => $total, 'active' => $total - $used, 'used' => $used],
            ]);

            return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
        }

        return $this->csv($coupons, $filename, ['S/N', 'Token Code', 'Plan', 'Type', 'Status', 'Generated']);
    }

    /**
     * An agent's own tokens, optionally narrowed to a selection made in the table.
     *
     * @param  array<int, string>  $ids
     */
    public function tokens(User $agent, array $ids = []): StreamedResponse
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));

        $coupons = Coupon::query()
            ->where('agent_id', $agent->id)   // never another agent's codes, whatever ids are sent
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->latest()
            ->get();

        return $this->csv(
            $coupons,
            $this->tokensFilename(),
            ['S/N', 'Token Code', 'Plan', 'Type', 'Region', 'Status', 'Generated'],
            fn (Coupon $coupon): array => [ucfirst((string) $coupon->region)],
        );
    }

    /**
     * @param  array<int, string>  $header
     * @param  ?callable(Coupon): array<int, mixed>  $extra  columns inserted before Status
     */
    private function csv(Collection $coupons, string $filename, array $header, ?callable $extra = null): StreamedResponse
    {
        return response()->streamDownload(function () use ($coupons, $header, $extra): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($coupons->values() as $i => $coupon) {
                $row = [$i + 1, $coupon->code, $coupon->displayName(), $coupon->type->getName()];

                if ($extra !== null) {
                    $row = array_merge($row, $extra($coupon));
                }

                $row[] = $coupon->is_valid ? 'Active' : 'Used';
                $row[] = $coupon->created_at?->format('Y-m-d H:i');

                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
