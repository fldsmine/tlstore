<?php

namespace App\Livewire\Backoffice\Payments;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\PaymentIntent;
use App\Models\PaymentWebhookEvent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Payment providers')]
class ProvidersIndex extends Component
{
    use AuthorizesAdminActions;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $configured = array_keys((array) config('trustlens.payment_gateways', []));
        $implemented = (array) config('trustlens.implemented_payment_gateways', []);

        $webhooks = PaymentWebhookEvent::query()
            ->selectRaw('provider, processing_status, count(*) as aggregate, max(received_at) as last_received')
            ->where('received_at', '>=', now()->subDays(30))
            ->groupBy('provider', 'processing_status')
            ->get()
            ->groupBy('provider');

        $signatureFailures = PaymentWebhookEvent::query()
            ->selectRaw('provider, count(*) as aggregate')
            ->where('signature_valid', false)
            ->where('received_at', '>=', now()->subDays(30))
            ->groupBy('provider')
            ->pluck('aggregate', 'provider');

        $recentErrors = PaymentWebhookEvent::query()
            ->whereIn('processing_status', ['failed', 'rejected'])
            ->whereNotNull('processing_error')
            ->latest('received_at')
            ->limit(10)
            ->get(['id', 'provider', 'event_type', 'processing_status', 'processing_error', 'received_at']);

        $intents = PaymentIntent::query()
            ->selectRaw('provider, status, count(*) as aggregate')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('provider', 'status')
            ->get()
            ->groupBy('provider');

        $providers = collect(array_unique(array_merge($configured, $implemented)))
            ->map(fn (string $name): array => [
                'name' => $name,
                'implemented' => in_array($name, $implemented, true),
                'secret_configured' => filled(config("trustlens.payment_gateways.{$name}.secret_key"))
                    || filled(config("trustlens.payment_gateways.{$name}.webhook_secret")),
                'webhooks' => ($webhooks[$name] ?? collect())->mapWithKeys(fn ($row) => [$row->processing_status => (int) $row->aggregate]),
                'last_webhook' => ($webhooks[$name] ?? collect())->max('last_received'),
                'signature_failures' => (int) ($signatureFailures[$name] ?? 0),
                'intents' => ($intents[$name] ?? collect())->mapWithKeys(fn ($row) => [$row->status => (int) $row->aggregate]),
            ])
            ->sortBy('name')
            ->values();

        return view('livewire.backoffice.payments.providers-index', [
            'providers' => $providers,
            'recentErrors' => $recentErrors,
        ]);
    }
}
