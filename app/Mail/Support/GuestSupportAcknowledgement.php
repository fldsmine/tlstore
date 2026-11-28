<?php

namespace App\Mail\Support;

class GuestSupportAcknowledgement extends SupportMail
{
    protected function viewName(): string
    {
        return 'emails.support.guest-acknowledgement';
    }

    protected function subjectLine(): string
    {
        return 'We received your message ('.$this->data['ticket_number'].')';
    }
}
