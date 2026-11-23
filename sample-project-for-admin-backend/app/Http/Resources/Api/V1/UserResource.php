<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use App\Support\Onboarding;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The authenticated user. Sensitive fields (password hash, transaction PIN,
 * remember token) are never exposed — the model already hides them; this
 * resource additionally whitelists explicitly.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'phone' => $this->phone,
            'country' => $this->country,
            'avatar_url' => $this->avatar,
            'biography' => $this->biography,
            'gender' => $this->gender?->value,
            'status' => $this->status?->value,
            'is_agent' => (bool) $this->is_agent,
            'telegram_url' => $this->telegram_url,
            // Mini App sign-in link (only present when the relation is loaded).
            'telegram' => $this->when(
                $this->relationLoaded('telegramIdentity'),
                fn () => [
                    'linked' => $this->telegramIdentity !== null,
                    'username' => $this->telegramIdentity?->username,
                    'linked_at' => $this->telegramIdentity?->created_at?->toIso8601String(),
                ],
            ),
            'package' => $this->when(
                $this->relationLoaded('package'),
                fn () => $this->package ? new PackageSummaryResource($this->package) : null,
            ),
            'has_transaction_pin' => filled($this->transaction_pin),
            'onboarding' => [
                'email_verified' => $this->email_verified_at !== null,
                'activated' => $this->package_id !== null,
                'pin_set' => filled($this->transaction_pin),
                'complete' => Onboarding::nextRoute($this->resource) === null,
            ],
            'website' => [
                'expires_at' => $this->website_expires_at?->toIso8601String(),
                'active' => $this->websiteActive(),
            ],
            'display_currency' => $this->display_currency,
            'referrer_username' => $this->when(
                $this->relationLoaded('referrer'),
                fn () => $this->referrer?->username,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
