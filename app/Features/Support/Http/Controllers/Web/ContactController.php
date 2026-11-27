<?php

namespace App\Features\Support\Http\Controllers\Web;

use App\Application\Actions\Support\SubmitGuestSupportTicket;
use App\Features\Support\Http\ContactFormRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public contact page on the marketing site (`/contact`).
 *
 * It posts to the same application action as `POST /api/v1/guest-support`, so the
 * storefront, the mobile app and any future surface share one validation
 * contract, one spam guard and one email flow.
 */
final class ContactController
{
    public function show(): View
    {
        return view('contact', [
            'appName' => (string) config('app.name', 'TLStore'),
            'supportEmail' => (string) config('trustlens.support_email'),
            'webAppUrl' => rtrim((string) config('app.web_url'), '/'),
            'categories' => ContactFormRules::CATEGORIES,
        ]);
    }

    public function store(Request $request, SubmitGuestSupportTicket $submit): RedirectResponse
    {
        $data = $request->validate(
            ContactFormRules::rules(),
            ContactFormRules::messages(),
            ContactFormRules::attributes(),
        );

        $ticket = $submit->handle([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
            'category' => $data['category'] ?? null,
            'honeypot' => (string) $request->input('website', ''),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($ticket === null) {
            return redirect()->route('contact.show')->with('contact_status', 'Thanks — if your message needs a reply we will be in touch.');
        }

        return redirect()->route('contact.show')->with(
            'contact_status',
            'Thanks — your message is with our support team. We will reply to '.$ticket->guest_email
            .' and your reference is '.$ticket->ticket_number.'.',
        );
    }
}
