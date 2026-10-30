<?php

namespace App\Http\Controllers;

use App\Models\CouponBatch;
use App\Models\User;
use App\Services\Agent\CouponExportService;
use App\Support\DownloadLinks;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Token exports handed back as files over a plain GET, so the download bridge can route them to
 * whichever surface the agent is using (see resources/js/downloads.js).
 *
 * Authorisation is either the signed-in session (the owner) or the temporary signature on the link
 * (which is only ever minted for the owner) — a signed, cookie-less request is how the Telegram Mini
 * App downloads, and the signature has already been checked by EnsureDownloadable by the time we
 * get here.
 */
class AgentExportController extends Controller
{
    /** A whole batch: /downloads/batch/{batch}?scope=&format= */
    public function batch(Request $request, string $batch, CouponExportService $exports): StreamedResponse
    {
        // Who is asking: the signed-in agent (browser / Android app) or the user the link was
        // minted for (Telegram Mini App, which sends no cookie). Either way the batch must be theirs.
        $owner = auth()->user() ?? DownloadLinks::signedUser();

        abort_unless($owner instanceof User, 403);

        $couponBatch = CouponBatch::query()
            ->where('agent_id', $owner->id)
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->findOrFail($batch);

        return $exports->batch(
            $couponBatch,
            CouponExportService::normalizeScope($request->query('scope')),
            CouponExportService::normalizeFormat($request->query('format')),
        );
    }

    /** The agent's own tokens: /downloads/tokens?ids[]=… (no ids = everything). */
    public function tokens(Request $request, CouponExportService $exports): StreamedResponse
    {
        $agent = auth()->user() ?? DownloadLinks::signedUser();

        abort_unless($agent instanceof User && $agent->is_agent, 403);

        $ids = $request->query('ids', []);
        $ids = is_array($ids) ? array_slice($ids, 0, CouponExportService::MAX_SELECTED_IDS) : [];

        return $exports->tokens($agent, $ids);
    }
}
