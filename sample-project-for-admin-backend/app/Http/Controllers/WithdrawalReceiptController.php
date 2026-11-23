<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Withdrawal;
use App\Support\DownloadLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class WithdrawalReceiptController
{
    /**
     * Render (or download) a withdrawal's receipt PDF.
     *
     * Two callers, both scoped to the owner: the signed-in dashboard (which must own the
     * withdrawal) and the temporary signed link the dashboard renders, which is what the Telegram
     * Mini App follows because its downloader carries no session cookie. A signed, cookie-less
     * request is already authorised by EnsureDownloadable.
     */
    public function __invoke(string $reference): Response
    {
        // Who is asking: the signed-in user (browser / Android app) or the user the link was minted
        // for (Telegram Mini App, which sends no cookie). Either way the receipt must be theirs.
        $owner = auth()->user() ?? DownloadLinks::signedUser();

        abort_unless($owner instanceof User, 403);

        $withdrawal = Withdrawal::query()
            ->where('user_id', $owner->id)
            ->where('reference', $reference)
            ->firstOrFail();

        $pdf = Pdf::loadView('pdf.withdrawal-receipt', [
            'withdrawal' => $withdrawal,
            'amountPaid' => money((float) $withdrawal->amount_paid),
            'fee' => (float) $withdrawal->fee > 0 ? money((float) $withdrawal->fee) : null,
            'maskedAccount' => $withdrawal->account_number ? '**** '.substr((string) $withdrawal->account_number, -4) : null,
        ])->setPaper([0, 0, 384, 800]); // tall, narrow receipt — everything on one page

        $filename = 'Nextel-Receipt-'.$withdrawal->reference.'.pdf';

        return request()->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
