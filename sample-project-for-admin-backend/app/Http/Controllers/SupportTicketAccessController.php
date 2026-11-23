<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;

class SupportTicketAccessController extends Controller
{
    public function __invoke(SupportTicket $ticket, string $token): RedirectResponse
    {
        abort_unless($ticket->isGuestAccessTokenValid($token), 404);

        session()->put($ticket->guestSessionKey(), true);

        return redirect()->route('support.guest-ticket', $ticket->id);
    }
}
