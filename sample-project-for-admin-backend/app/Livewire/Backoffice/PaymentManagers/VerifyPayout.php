<?php

namespace App\Livewire\Backoffice\PaymentManagers;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Livewire\Backoffice\PaymentManagers\Concerns\GuardsPaymentManagerPages;
use App\Services\Payments\DTO\PayoutResult;
use App\Services\Payments\MerchantWalletService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Backoffice → payment manager → Verify payout: paste a payout reference and ask the provider for
 * the authoritative status.
 *
 * Both providers' own guidance points here: when a payout request errors out (502/504/500) the money
 * may still have moved, so the status must be verified by reference — never assumed. When the
 * reference belongs to a payout recorded here, the local audit row is updated with what the provider
 * says (status, fee, provider handle, response, verified timestamps).
 *
 * Concrete per-provider components supply the merchant service, the audit model, the display label,
 * the label for the provider's own handle (Korapay's trace id, Flutterwave's transfer id) and the view.
 */
abstract class VerifyPayout extends Component
{
    use AuthorizesAdminActions, GuardsPaymentManagerPages;

    /**
     * Bound to ?reference= so a link like /backoffice/flutterwave/verify?reference=FLW-… (the
     * "verify this payout" links in Send money and the recent-payout list) prefills AND checks the
     * moment the page opens — the reference itself is what makes the page shareable.
     */
    #[Url]
    public string $reference = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public ?string $localPayoutId = null;

    public function mount(?string $reference = null): void
    {
        // `#[Url]` hydrates properties AFTER mount, so the query string is read directly here: a link
        // to ?reference=FLW-… (from Send money or the recent-payout list) must both prefill the field
        // and check the payout as soon as the page opens.
        $this->reference = trim((string) ($reference ?? request()->query('reference', '')));

        if (strlen($this->reference) >= 5) {
            $this->verify();
        }
    }

    /** Prefill from the recent list (a full page load keeps the URL shareable). */
    public function useReference(string $reference): void
    {
        $this->assertAdmin();

        $this->redirectRoute($this->verifyRouteName(), ['reference' => $reference], navigate: true);
    }

    public function verify(): void
    {
        $this->assertAdmin();

        $this->validate([
            'reference' => ['required', 'string', 'min:5', 'max:60'],
        ], [
            'reference.min' => 'A payout reference is at least 5 characters long.',
        ]);

        $reference = trim($this->reference);
        $manager = $this->manager();
        $result = $manager->verifyPayout($reference);

        $this->result = [
            'ok' => $result->ok,
            'status' => $result->status,
            'amount' => $result->amount,
            'fee' => $result->fee,
            'currency' => $result->currency,
            'reference' => $result->reference !== '' ? $result->reference : $reference,
            // The provider's own handle. The key stays `trace_id` (the shape Korapay shipped with);
            // the page labels it per provider (Korapay: Trace ID, Flutterwave: Transfer ID).
            'trace_id' => $result->traceId ?? $result->gatewayId,
            'message' => $result->message,
            'raw' => $result->data,
            'checked_at' => now()->toIso8601String(),
        ];

        $this->localPayoutId = null;

        $model = $this->payoutModel();

        $payout = $model::query()->where('reference', $reference)->first();

        if ($payout !== null) {
            $this->localPayoutId = $payout->id;
            $payout->update([
                'status' => $result->status,
                'fee' => $result->fee > 0 ? $result->fee : $payout->fee,
                'gateway_reference' => $result->gatewayId ?? $payout->gateway_reference,
                'trace_id' => $result->traceId ?? $payout->trace_id,
                'response' => $result->data !== [] ? $result->data : $payout->response,
                'error' => $result->ok ? null : ($result->message ?? $payout->error),
                'verified_at' => $result->ok && $result->status !== PayoutResult::STATUS_UNKNOWN ? now() : $payout->verified_at,
                'last_verified_at' => now(),
            ]);
        }

        [$message, $type] = match (true) {
            ! $result->ok => [$result->message ?? $this->label().' could not be reached — the payout status is unknown.', 'warning'],
            $result->completed() => ["Payout {$reference} completed successfully.", 'success'],
            $result->failed() => ["Payout {$reference} failed.", 'error'],
            default => ["Payout {$reference} is ".$result->status.' — not final yet, check again later.', 'info'],
        };

        $this->dispatch('toast', message: $message, type: $type);
    }

    public function render(): View
    {
        $model = $this->payoutModel();

        return view($this->viewName(), [
            'recentPayouts' => $model::query()->with('admin')->latest()->limit(10)->get(),
            'identifierLabel' => $this->identifierLabel(),
            'referencePlaceholder' => $this->referencePlaceholder(),
            'referenceHint' => $this->referenceHint(),
        ]);
    }

    /* ------------------------------------------------------------------ per-provider hooks */

    /** The merchant service that talks to this provider. */
    abstract protected function manager(): MerchantWalletService;

    /** The payout audit model for this manager (must use RecordsPayoutStatus). */
    abstract protected function payoutModel(): string;

    /** Display name of this provider, e.g. "Korapay". */
    abstract protected function label(): string;

    /** Name of this provider's verify route, so the recent list can deep-link into this page. */
    abstract protected function verifyRouteName(): string;

    /** The blade this component renders (each provider has its own thin wrapper blade). */
    abstract protected function viewName(): string;

    /** Label for this provider's own payout handle (Korapay: trace id; Flutterwave: transfer id). */
    protected function identifierLabel(): string
    {
        return 'Provider ID';
    }

    protected function referencePlaceholder(): string
    {
        return 'e.g. '.strtoupper(substr($this->label(), 0, 3)).'-…';
    }

    protected function referenceHint(): string
    {
        return 'Paste any payout reference.';
    }
}
