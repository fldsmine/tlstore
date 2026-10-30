<?php

namespace App\Mail;

use App\Enums\OtpType;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpMail extends BaseMail
{
    public function __construct(
        public string $code,
        public OtpType $type,
        public ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->resolveSubject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.otp',
            with: [
                'code' => $this->code,
                'name' => $this->name,
                'heading' => $this->resolveHeading(),
                'intro' => $this->resolveIntro(),
            ],
        );
    }

    private function resolveSubject(): string
    {
        return match ($this->type) {
            OtpType::EMAIL_VERIFICATION => 'Verify your email — Nextel Connect',
            OtpType::PASSWORD_RESET => 'Reset your password — Nextel Connect',
            OtpType::PHONE_VERIFICATION => 'Verify your phone — Nextel Connect',
        };
    }

    private function resolveHeading(): string
    {
        return match ($this->type) {
            OtpType::EMAIL_VERIFICATION => 'Verify your email',
            OtpType::PASSWORD_RESET => 'Reset your password',
            OtpType::PHONE_VERIFICATION => 'Verify your phone',
        };
    }

    private function resolveIntro(): string
    {
        return match ($this->type) {
            OtpType::EMAIL_VERIFICATION => 'Use the code below to verify your email address and finish setting up your Nextel account.',
            OtpType::PASSWORD_RESET => "Use the code below to reset your Nextel password. If you didn't request this, you can safely ignore this email.",
            OtpType::PHONE_VERIFICATION => 'Use the code below to verify your phone number.',
        };
    }
}
