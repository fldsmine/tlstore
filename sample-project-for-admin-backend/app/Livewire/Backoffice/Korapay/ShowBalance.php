<?php

namespace App\Livewire\Backoffice\Korapay;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\KorapayPayout;
use App\Models\PaymentReference;
use App\Services\Payments\KorapayMerchantService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Backoffice → Korapay → Balance: the merchant wallet balance (available / pending per currency).
 * Read-only: it calls the Balance API live and remembers nothing.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Korapay Balance — Backoffice')]
class ShowBalance extends Component
{
    use AuthorizesAdminActions;

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
        $this->load();

        $this->dispatch('toast', message: $this->error ?? 'Balance refreshed.', type: $this->error ? 'error' : 'success');
    }

    private function load(): void
    {
        $result = app(KorapayMerchantService::class)->balances();

        $this->balances = $result->ok ? $result->data : [];
        $this->error = $result->ok ? null : $result->message;
        $this->fetchedAt = now()->toIso8601String();
    }

    public function render(): View
    {
        $korapay = app(KorapayMerchantService::class);

        return view('livewire.backoffice.korapay.show-balance', [
            'configured' => $korapay->configured(),
            'testMode' => $korapay->isTestMode(),
            'available' => collect($this->balances)->where('issuing', null)->pluck('available', 'currency')->all(),
            'recentPayouts' => KorapayPayout::query()->with('admin')->latest()->limit(5)->get(),
            'inFlightPayouts' => KorapayPayout::query()->inFlight()->count(),
            'settledLast24h' => PaymentReference::query()->where('settled_at', '>=', now()->subDay())->count(),
        ]);
    }
}
