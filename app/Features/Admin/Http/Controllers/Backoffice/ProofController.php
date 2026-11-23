<?php

namespace App\Features\Admin\Http\Controllers\Backoffice;

use App\Models\ManualDepositSubmission;
use App\Models\AdShareEvent;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin-only proxy for moderation proof files kept on the private `local` disk.
 * Mirrors ModerationController::depositProof / shareProof for the web session.
 */
final class ProofController
{
    public function deposit(ManualDepositSubmission $deposit): StreamedResponse
    {
        $path = $deposit->proof_storage_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'deposit-'.$deposit->id.'-proof');
    }

    public function share(AdShareEvent $event): StreamedResponse
    {
        $path = is_array($event->evidence) ? ($event->evidence['screenshot_path'] ?? null) : null;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException();
        }

        return Storage::disk('local')->download($path, 'share-'.$event->id.'-proof');
    }
}
