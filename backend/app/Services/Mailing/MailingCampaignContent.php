<?php

namespace App\Services\Mailing;

final readonly class MailingCampaignContent
{
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
        public ?string $replyTo = null,
    ) {}
}
