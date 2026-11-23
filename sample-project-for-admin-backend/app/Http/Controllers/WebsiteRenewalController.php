<?php

namespace App\Http\Controllers;

use App\Services\Payments\WebsiteRenewalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteRenewalController extends Controller
{
    public function start(Request $request, WebsiteRenewalService $renewals): RedirectResponse
    {
        $result = $renewals->start($request->user());

        if (! $result['ok']) {
            return back()->with('toast', ['type' => 'error', 'message' => $result['error'] ?? 'Could not start the renewal.']);
        }

        return redirect()->away($result['checkout_url']);
    }

    public function callback(Request $request, WebsiteRenewalService $renewals): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        $status = $reference !== '' ? $renewals->finalize($reference) : 'failed';

        [$message, $type] = match ($status) {
            'completed' => ['Your website has been renewed.', 'success'],
            'pending', 'unavailable' => ['Payment received — your website will be renewed shortly.', 'info'],
            default => ['That renewal did not go through. You were not charged.', 'error'],
        };

        return redirect()->route('my-website')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
