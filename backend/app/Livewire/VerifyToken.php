<?php

namespace App\Livewire;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Verify Token')]
class VerifyToken extends Component
{
    public string $code = '';

    public ?array $result = null;

    public function verify(): void
    {
        $this->validate(
            ['code' => ['required', 'string', 'min:6', 'max:32']],
            ['code.required' => 'Enter your token code.'],
        );

        $key = 'verify-token:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('code', 'Too many attempts. Please try again shortly.');

            return;
        }

        RateLimiter::hit($key, 60);

        $coupon = Coupon::with(['package', 'agent', 'redeemer.referrer', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->where('code', strtoupper(trim($this->code)))
            ->first();

        if (! $coupon) {
            $this->result = [
                'status' => 'invalid',
                'title' => 'Token not found',
                'message' => "We couldn't find a token with that code. Please check it and try again.",
            ];

            return;
        }

        if ($coupon->type === CouponType::PIN_RESET) {
            $this->result = [
                'status' => 'invalid',
                'title' => 'PIN-reset token',
                'message' => 'This is a PIN-reset token. Redeem it on the Reset PIN page to set a new transaction PIN.',
            ];

            return;
        }

        $plan = $coupon->additionalMinutePlan;

        $grants = match ($coupon->type) {
            CouponType::ACTIVATION => 'Activates (or upgrades to) the '.($coupon->package?->name ?? 'selected package').'.',
            CouponType::UPGRADE => 'Upgrades to the '.($coupon->package?->name ?? 'selected package').'.',
            CouponType::EXTRA_MINUTES => 'Adds the '.($plan?->name ?? 'extra minutes').' top-up.',
            CouponType::CLOUD => 'Adds the '.($coupon->cloudStoragePlan?->name ?? 'Nextel Cloud').' storage plan.',
            CouponType::CLOUD_RENEWAL => 'Renews the '.($coupon->cloudStoragePlan?->name ?? 'Nextel Cloud').' storage plan.',
            CouponType::STORAGE_ACTIVATION => 'Activates the '.($coupon->cloudStoragePlan?->name ?? 'special storage').' plan.',
            CouponType::WEBSITE_RENEWAL => 'Renews your personalized website.',
        };

        $trim = fn (float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');

        $minutes = match ($coupon->type) {
            CouponType::ACTIVATION, CouponType::UPGRADE => $coupon->package
                ? $trim((float) $coupon->package->daily_call_minutes).' daily call minutes'
                : null,
            CouponType::EXTRA_MINUTES => $plan
                ? '+'.$trim((float) $plan->daily_call_minutes).' daily minutes for '.$plan->duration_days.' days'
                : null,
            CouponType::CLOUD, CouponType::CLOUD_RENEWAL, CouponType::STORAGE_ACTIVATION => $coupon->cloudStoragePlan
                ? '+'.$coupon->cloudStoragePlan->extra_contacts.' contacts'
                    .((int) $coupon->cloudStoragePlan->extra_sms > 0 ? ' · +'.$coupon->cloudStoragePlan->extra_sms.' SMS/day' : '')
                    .((int) $coupon->cloudStoragePlan->duration_days > 0 ? ' for '.$coupon->cloudStoragePlan->duration_days.' days' : '')
                : null,
            CouponType::WEBSITE_RENEWAL => '+'.(int) config('settings.website.duration_days').' days of website validity',
        };

        $detailsLabel = match ($coupon->type) {
            CouponType::ACTIVATION, CouponType::UPGRADE, CouponType::EXTRA_MINUTES => 'Call minutes',
            CouponType::CLOUD, CouponType::CLOUD_RENEWAL, CouponType::STORAGE_ACTIVATION => 'Cloud access',
            CouponType::WEBSITE_RENEWAL => 'Website',
        };

        $this->result = [
            'status' => $coupon->user_id ? 'used' : 'valid',
            'title' => $coupon->user_id ? 'Token already redeemed' : 'Token is valid',
            'code' => $coupon->code,
            'type' => $coupon->type->getName(),
            'grants' => $grants,
            'minutes' => $minutes,
            'details_label' => $detailsLabel,
            'amount' => $coupon->price ? money((float) $coupon->price) : null,
            'vendor' => $coupon->agent?->username ? '@'.$coupon->agent->username : null,
            'used_by' => $coupon->redeemer?->username ? '@'.$coupon->redeemer->username : null,
            // Who referred the token's redeemer (their direct upline).
            'upline' => $coupon->redeemer?->referrer?->username ? '@'.$coupon->redeemer->referrer->username : null,
            'used_at' => $coupon->used_at?->format('M j, Y'),
            'message' => $coupon->used_at
                ? 'This token has already been redeemed.'
                : 'This token is active and ready to be redeemed.',
        ];
    }

    public function render(): View
    {
        return view('livewire.verify-token');
    }
}
