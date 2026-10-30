<?php

namespace App\Livewire\Dashboard\SpecialCoupon;

use App\Models\User;
use App\Services\CloudActivationService;
use App\Services\Payments\RevenueCodeService;
use App\Services\Payments\SpecialCouponPurchaseService;
use App\Support\PayoutSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Revenue Authentication')]
class Activate extends Component
{
    public string $code = '';

    public ?string $successMessage = null;

    public bool $showForm = true;

    /** Reference of the charge whose code was just issued (set by the card-payment callback). */
    public ?string $issuedReference = null;

    public function mount(): void
    {
        // Prefill from session if available
        $this->code = (string) session('special_coupon_code', '');
        session()->forget('special_coupon_code');

        // The card-payment callback lands here: show the code that charge just bought. Only the
        // reference travels through the session — the code itself is read back from its own row.
        $issued = session('special_coupon_code_issued');
        session()->forget('special_coupon_code_issued');
        $this->issuedReference = is_string($issued) && $issued !== '' ? $issued : null;
    }

    public function activate(CloudActivationService $service): void
    {
        $this->validate([
            'code' => ['required', 'string', 'min:6', 'max:32'],
        ], [
            'code.required' => 'Enter your special coupon code.',
            'code.min' => 'The code must be at least 6 characters.',
            'code.max' => 'The code must be no more than 32 characters.',
        ]);

        try {
            // The raw code is passed straight to the service and cleared immediately after —
            // it is never kept in component state alongside a hash.
            $plan = $service->activateStorage($this->user(), $this->code);

            $grants = '+'.$plan->extra_contacts.' '.Str::plural('contact', (int) $plan->extra_contacts);
            if ((int) $plan->extra_sms > 0) {
                $grants .= ' · +'.$plan->extra_sms.' SMS/day';
            }

            $this->successMessage = $plan->name.' activated — '.$grants.' added to your account.';
            $this->showForm = false;
            $this->reset('code');

            $this->dispatch('toast', message: $this->successMessage, type: 'success');
            $this->dispatch('cloud-updated');

        } catch (ValidationException $e) {
            $this->addError('code', $e->validator->errors()->first());
        }
    }

    public function resetForm(): void
    {
        $this->reset('code', 'successMessage');
        $this->showForm = true;
        $this->resetErrorBag();
    }

    protected function user(): User
    {
        return auth()->user();
    }

    public function render(CloudActivationService $service): View
    {
        $user = $this->user();

        $codes = app(RevenueCodeService::class);
        $purchases = app(SpecialCouponPurchaseService::class);

        // What a special coupon grants for the user's tier — and what the card option sells, which is
        // the very same plan (the service enforces the tier match on both paths).
        $tierPlan = $codes->planFor($user);

        $cardCheckout = $purchases->availability($user);

        // A code the platform issued to this user (bought with a card, or issued by support) is shown
        // back to them — a buyer never typed it, so this page is the only place they can read it.
        $justIssued = $this->issuedReference !== null
            ? $codes->codeForReference($user, $this->issuedReference)
            : null;

        $revenueCode = $justIssued ?? $codes->issuedCodeFor($user);
        $revenuePlan = $revenueCode?->cloudStoragePlan;

        return view('livewire.dashboard.special-coupon.activate', [
            'hasPackage' => $user->package_id !== null,
            'packageName' => $user->package?->name,
            'tierLevel' => $user->package?->tier_level,
            'tierStorageContacts' => $tierPlan?->extra_contacts,
            'tierStorageSMS' => $tierPlan?->extra_sms,
            'expiryDays' => (int) config('settings.token.special_expiry_days', 90),
            'tierPlan' => $tierPlan,
            'cardCheckout' => $cardCheckout,
            'cardGrants' => $tierPlan ? $codes->describe($tierPlan) : null,
            'revenueCode' => $revenueCode,
            'revenueGrants' => $revenuePlan ? $codes->describe($revenuePlan) : null,
            'justIssued' => $justIssued !== null,
            'openCheckout' => $purchases->openCheckout($user),
            'supportUrl' => PayoutSupport::telegramRevenue(),
        ]);
    }
}
