<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const SOURCE_USER = 'user';

    public const SOURCE_PUBLIC_FORM = 'public_form';

    protected $fillable = [
        'ticket_number', 'user_id', 'guest_name', 'guest_email', 'source',
        'ip_address', 'user_agent', 'subject', 'category', 'status', 'priority',
        'last_message_at', 'closed_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('id');
    }

    /** A ticket submitted through the public contact form by a non-user. */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /** Who the ticket belongs to, for admin listings. */
    public function ownerName(): string
    {
        return trim((string) ($this->user?->full_name
            ?: $this->user?->username
            ?: $this->guest_name
            ?: 'Guest'));
    }

    /** Where a reply has to go: the account email, or the guest's address. */
    public function contactEmail(): ?string
    {
        $email = $this->user?->email ?: $this->guest_email;

        return $email !== null && trim($email) !== '' ? trim($email) : null;
    }
}
