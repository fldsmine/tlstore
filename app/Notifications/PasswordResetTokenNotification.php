<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class PasswordResetTokenNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your TrustLens password')
            ->greeting('Password reset requested')
            ->line('Enter this one-time token in the TrustLens app to choose a new password:')
            ->line($this->token)
            ->line('This token expires in 60 minutes. If you did not request it, you can ignore this email.');
    }
}
