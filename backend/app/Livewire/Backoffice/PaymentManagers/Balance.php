<?php

namespace App\Livewire\Backoffice\PaymentManagers;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Livewire\Backoffice\PaymentManagers\Concerns\GuardsPaymentManagerPages;
use App\Models\PaymentReference;
use App\Services\Payments\MerchantWalletService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Backoffice → payment manager → Balance: the merchant wallet balance (available / pending per
 * currency). Read-only: it calls the provider's Balance API live and remembers nothing.
 *
 * The concrete per-provider component supplies the merchant service, the payout model and the view
 * (see the hooks at the bottom), so every manager gets the same page.
 */
abstract class Balance extends Component
{
    use AuthorizesAdminActions, GuardsPaymentManagerPages;

    /** @var list<array{currency: string, available: float, pending: float, issuing: ?float}> */
    public array $balances = [];

    public ?string $error = null;

    public ?string $fetchedAt = null;

    public function mount(): void
    {
        $this->load();
    }

    public function refresh(): void
    {
        $this->assertAdmin();

        $this->load();

        $this->dispatch('toast', message: $this->error ?? 'Balance refreshed.', type: $this->error ? 'error' : 'success');
    }

    private function load(): void
    {
        $result = $this->manager()->balances();

        $this->balances = $result->ok ? $result->data : [];
        $this->error = $result->ok ? null : $result->message;
        $this->fetchedAt = now()->toIso8601String();
    }

    public function render(): View
    {
        $manager = $this->manager();
        $model = $this->payoutModel();

        return view($this->viewName(), [
            'configured' => $manager->configured(),
            'testMode' => $manager->isTestMode(),
            'available' => collect($this->balances)->where('issuing', null)->pluck('available', 'currency')->all(),
            'recentPayouts' => $model::query()->with('admin')->latest()->limit(5)->get(),
            'inFlightPayouts' => $model::query()->inFlight()->count(),
            'settledLast24h' => PaymentReference::query()->where('settled_at', '>=', now()->subDay())->count(),
        ]);
    }

    /* ------------------------------------------------------------------ per-provider hooks */

    /** The merchant service that talks to this provider. */
    abstract protected function manager(): MerchantWalletService;

    /** The payout audit model for this manager (must use RecordsPayoutStatus). */
    abstract protected function payoutModel(): string;

    /** The blade this component renders (each provider has its own thin wrapper blade). */
    abstract protected function viewName(): string;
}
