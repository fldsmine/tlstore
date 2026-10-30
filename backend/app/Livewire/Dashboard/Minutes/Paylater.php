<?php

namespace App\Livewire\Dashboard\Minutes;

use App\Livewire\Concerns\VerifiesTransactionPin;
use App\Models\AdditionalMinutePlan;
use App\Services\PayLaterService;
use App\Services\TokenRedemptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Pay Later')]
class Paylater extends Component
{
    use VerifiesTransactionPin;

    public string $step = 'list';

    public ?string $planId = null;

    public bool $agreed = false;

    public string $pin = '';

    public ?array $activated = null;

    public ?string $repayingId = null;

    public string $repayToken = '';

    private function plan(): ?AdditionalMinutePlan
    {
        return $this->planId ? AdditionalMinutePlan::find($this->planId) : null;
    }

    public function apply(string $planId): void
    {
        if (! app(PayLaterService::class)->isEligible(auth()->user())) {
            $this->dispatch('toast', message: 'Pay Later isn’t available on your account right now.', type: 'error');

            return;
        }

        $this->planId = $planId;
        $this->reset(['agreed', 'pin']);
        $this->step = 'confirm';
        $this->resetValidation();
    }

    public function backToList(): void
    {
        $this->reset(['planId', 'agreed', 'pin']);
        $this->step = 'list';
    }

    public function toTerms(): void
    {
        $this->agreed = false;
        $this->step = 'terms';
    }

    public function acceptTerms(): void
    {
        if (! $this->agreed) {
            $this->dispatch('toast', message: 'Please accept the terms to continue.', type: 'warning');

            return;
        }

        $this->pin = '';
        $this->step = 'pin';
        $this->resetValidation();
    }

    public function confirmPin(): void
    {
        $user = auth()->user();

        $this->validate(['pin' => ['required', 'digits:4']], [
            'pin.required' => 'Enter your transaction PIN.',
            'pin.digits' => 'Your PIN must be 4 digits.',
        ]);

        if (! $this->verifyTransactionPin($this->pin)) {
            $this->reset('pin');

            return;
        }

        $plan = $this->plan();

        if (! $plan) {
            $this->backToList();

            return;
        }

        $credit = app(PayLaterService::class)->apply($user, $plan);

        $this->activated = [
            'plan' => $credit->plan_name,
            'amount' => (float) $credit->amount,
            'minutes' => (float) $credit->minutes,
            'due_date' => $credit->due_date->format('j M Y'),
        ];
        $this->step = 'done';
        $this->dispatch('toast', message: 'Minutes activated — start earning immediately.', type: 'success');
    }

    public function finish(): void
    {
        $this->reset(['planId', 'agreed', 'pin', 'activated']);
        $this->step = 'list';
    }

    public function startRepay(string $creditId): void
    {
        $this->repayingId = $creditId;
        $this->repayToken = '';
        $this->resetValidation();
    }

    public function cancelRepay(): void
    {
        $this->reset(['repayingId', 'repayToken']);
        $this->resetValidation();
    }

    public function repay(TokenRedemptionService $redemption): void
    {
        $user = auth()->user();
        $credit = $user->minuteCredits()->outstanding()->whereKey($this->repayingId)->first();

        if (! $credit) {
            $this->cancelRepay();

            return;
        }

        $this->validate(['repayToken' => ['required', 'string', 'min:6', 'max:32']], [
            'repayToken.required' => 'Enter a minutes token to repay.',
        ]);

        $redemption->repayPayLater($user, $credit, $this->repayToken, 'repayToken');

        $this->cancelRepay();
        $this->dispatch('toast', message: 'Pay Later repaid — you’re all settled.', type: 'success');
    }

    public function render(): View
    {
        $user = auth()->user()->loadMissing('package');
        $svc = app(PayLaterService::class);

        $region = RegionPricing::forUser($user);

        $plans = AdditionalMinutePlan::query()->active()->ordered()->get()->map(function (AdditionalMinutePlan $p) use ($region): array {
            $price = RegionPricing::customer($p, $region);
            $rate = (float) $p->min_earning_rate;
            $days = (int) $p->paylater_duration_days;

            $potential = round($price + (float) $p->daily_call_minutes * $rate * $days * 0.05);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'minutes' => (float) $p->daily_call_minutes,
                'price' => $price,
                'min_rate' => $rate,
                'days' => $days,
                'extra_numbers' => (int) $p->call_id_bonus,
                'potential' => $potential,
                'trend' => $price > 0 ? round(($potential / $price - 1) * 100, 1) : 0,
                'is_featured' => (bool) $p->is_featured,
            ];
        });

        return view('livewire.dashboard.minutes.paylater', [
            'eligible' => $svc->isEligible($user),
            'plans' => $plans,
            'selectedPlan' => $this->planId ? $plans->firstWhere('id', $this->planId) : null,
            'credits' => $user->minuteCredits()->latest()->take(15)->get(),
            'hasOutstanding' => $svc->hasOutstanding($user),
            'subsetLimit' => config('settings.paylater.audience') === 'subset' ? (int) config('settings.paylater.limit') : null,
        ]);
    }
}
