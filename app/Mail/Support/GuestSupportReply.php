<?php

namespace App\Mail\Support;

class GuestSupportReply extends SupportMail
{
    protected function viewName(): string
    {
        return 'emails.support.guest-reply';
    }

    protected function subjectLine(): string
    {
        return 'Re: '.$this->data['subject'].' ('.$this->data['ticket_number'].')';
    }
}
