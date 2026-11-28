<?php

namespace App\Mail\Support;

class GuestSupportInboxNotification extends SupportMail
{
    protected function viewName(): string
    {
        return 'emails.support.inbox-notification';
    }

    protected function subjectLine(): string
    {
        return '[Guest contact] '.$this->data['subject'].' · '.$this->data['ticket_number'];
    }
}
