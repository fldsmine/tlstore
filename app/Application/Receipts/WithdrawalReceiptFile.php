<?php

namespace App\Application\Receipts;

use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders the withdrawal receipt from `resources/views/pdf/withdrawal-receipt.blade.php`
 * into a file that Telegram can attach.
 *
 * Telegram shows photos inline, so the pipeline is PDF → first page PNG via
 * Imagick + Ghostscript when the server has them. Without that delegate the
 * PDF itself is sent as a document: the receipt still reaches the chat, and
 * nothing depends on an optional PHP extension being present.
 */
final class WithdrawalReceiptFile
{
    public function __construct(private readonly WithdrawalReceiptPdf $pdf) {}

    /**
     * @return array{path: string, relative: string, type: 'photo'|'document', mime: string}|null
     */
    public function make(WithdrawalRequest $withdrawal): ?array
    {
        try {
            $bytes = $this->pdf->render($withdrawal);
        } catch (\Throwable $error) {
            Log::warning('telegram.receipt_render_failed', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $error->getMessage(),
            ]);

            return null;
        }

        $directory = trim((string) config('telegram.receipt_directory', 'telegram/receipts'), '/');
        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        $base = 'receipt-'.$withdrawal->id.'-'.Str::lower(Str::random(8));
        $pdfRelative = $directory.'/'.$base.'.pdf';
        $disk->put($pdfRelative, $bytes);

        $imageRelative = $this->convertFirstPage($disk->path($pdfRelative), $directory.'/'.$base.'.png');
        if ($imageRelative !== null) {
            $disk->delete($pdfRelative);

            return [
                'path' => $disk->path($imageRelative),
                'relative' => $imageRelative,
                'type' => 'photo',
                'mime' => 'image/png',
            ];
        }

        return [
            'path' => $disk->path($pdfRelative),
            'relative' => $pdfRelative,
            'type' => 'document',
            'mime' => 'application/pdf',
        ];
    }

    private function ghostscriptBinary(): ?string
    {
        $configured = trim((string) config('telegram.ghostscript_binary', ''));
        if ($configured !== '') {
            return is_executable($configured) ? $configured : null;
        }

        foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'] as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function delete(?string $relative): void
    {
        if (filled($relative)) {
            Storage::disk('local')->delete($relative);
        }
    }

    private function convertFirstPage(string $pdfPath, string $relativeTarget): ?string
    {
        if (! extension_loaded('imagick') || ! config('telegram.receipt_image', true)) {
            return null;
        }

        // Imagick reads PDF through Ghostscript. Probing first keeps a server
        // without it on the document path instead of an error (some Imagick
        // builds abort the process rather than throwing).
        if ($this->ghostscriptBinary() === null) {
            return null;
        }

        $disk = Storage::disk('local');
        $target = $disk->path($relativeTarget);

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(144, 144);
            // Ghostscript is required to rasterise PDF input; without it this
            // throws and the PDF is attached instead.
            $imagick->readImage($pdfPath.'[0]');
            $imagick->setImageBackgroundColor('white');
            if (method_exists($imagick, 'setImageAlphaChannel')) {
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }
            $imagick->setImageFormat('png');
            $imagick->writeImage($target);
            $imagick->clear();
            $imagick->destroy();

            return is_file($target) && filesize($target) > 0 ? $relativeTarget : null;
        } catch (\Throwable $error) {
            $disk->delete($relativeTarget);
            Log::info('telegram.receipt_image_conversion_skipped', [
                'reason' => $error->getMessage(),
            ]);

            return null;
        }
    }
}
