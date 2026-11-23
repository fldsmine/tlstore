<?php

namespace App\Livewire\Dashboard\Agent;

use App\Models\Coupon;
use App\Services\Agent\CouponExportService;
use App\Services\CouponMintingService;
use App\Support\DownloadLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Tokens')]
class Token extends Component
{
    public string $planKey = '';

    public int $quantity = 1;

    public bool $generating = false;

    public array $selected = [];

    public function mount(): void
    {
        if (! auth()->user()->is_agent) {
            $this->redirectRoute('dashboard', navigate: true);

            return;
        }

        $this->generating = request()->boolean('generate');
    }

    public function openGenerate(): void
    {
        $this->resetValidation();
        $this->generating = true;
    }

    public function closeGenerate(): void
    {
        $this->generating = false;
        $this->reset('planKey');
        $this->quantity = 1;
        $this->resetValidation();
    }

    protected function planOptions(): Collection
    {
        return app(CouponMintingService::class)->planOptions(auth()->user());
    }

    protected function selectedPlan(): ?array
    {
        return $this->planOptions()->firstWhere('key', $this->planKey);
    }

    public function generate(): void
    {
        $user = auth()->user();
        abort_unless($user->is_agent, 403);

        $this->validate([
            'planKey' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'planKey.required' => 'Select a plan.',
            'quantity.min' => 'Generate at least 1 code.',
            'quantity.max' => 'You can generate up to 500 codes at once.',
        ]);

        $plan = $this->selectedPlan();

        if (! $plan) {
            $this->addError('planKey', 'Select a valid plan.');

            return;
        }

        app(CouponMintingService::class)->mint($user, $this->planKey, $this->quantity);

        $this->closeGenerate();
        session()->flash('token-success', "{$this->quantity} {$plan['label']} token(s) generated.");
    }

    protected function planLabel(Coupon $coupon): string
    {
        return $coupon->displayName();
    }

    public function render(): View
    {
        $user = auth()->user();

        $coupons = Coupon::query()
            ->where('agent_id', $user->id)
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->latest()
            ->get();

        $used = $coupons->whereNotNull('user_id')->count();
        $total = $coupons->count();

        $planOptions = $this->planOptions();
        $plan = $planOptions->firstWhere('key', $this->planKey);

        // The export is a GET link so it can be handled by a native downloader. A selection is
        // carried as ids; when it would make the URL unreasonably long we export everything and say
        // so on the page rather than silently truncating the list.
        $selected = array_values(array_filter(array_map('strval', $this->selected)));
        $selectionTooLarge = count($selected) > CouponExportService::MAX_SELECTED_IDS;

        return view('livewire.dashboard.agent.token', [
            'exportUrl' => $coupons->isEmpty()
                ? null
                : DownloadLinks::signed('agent.tokens.export', $selected !== [] && ! $selectionTooLarge ? ['ids' => $selected] : []),
            'exportFilename' => app(CouponExportService::class)->tokensFilename(),
            'selectionTooLarge' => $selectionTooLarge,
            'coupons' => $coupons,
            'stats' => ['total' => $total, 'active' => $total - $used, 'used' => $used],
            'planOptions' => $planOptions,
            'selectedPlan' => $plan,
            'agentBalance' => (float) $user->agent_balance,
            'estimate' => $plan ? round($plan['agent_price'] * max(0, $this->quantity), 2) : 0.0,
        ]);
    }
}
