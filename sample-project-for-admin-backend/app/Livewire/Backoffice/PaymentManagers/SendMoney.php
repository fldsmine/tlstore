<?php

namespace App\Livewire\Backoffice\PaymentManagers;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Livewire\Backoffice\PaymentManagers\Concerns\GuardsPaymentManagerPages;
use App\Models\Bank;
use App\Models\Concerns\RecordsPayoutStatus;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\DTO\PayoutResult;
use App\Services\Payments\MerchantWalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Backoffice → payment manager → Send money: a single payout (disbursement) from a merchant wallet
 * to a bank account. Standalone by design — it is NOT connected to registered users, withdrawal
 * requests or payment sheets; the only trace is the payout audit row.
 *
 * Money movement, so it is guarded twice: the route sits behind the super-admin role and every
 * action re-checks it (Livewire actions are separate requests that skip route middleware), the
 * amount is capped, and the send is a two-step confirm. If the provider does not answer
 * definitively (timeout/5xx) the payout is stored as `unknown` and the admin is told to verify the
 * reference — never to re-send, because the payout may already be processing.
 *
 * Concrete per-provider subclasses (Korapay\SendMoney, Flutterwave\SendMoney) supply the four hooks
 * at the bottom of this class — which audit model, which merchant service, which extra wallets and
 * which view — and inherit the whole flow, so a payout behaves identically whichever manager is
 * active.
 */
abstract class SendMoney extends Component
{
    use AuthorizesAdminActions, GuardsPaymentManagerPages;

    /** currency → payout-rail country, used to pick the right bank list. */
    private const CURRENCY_COUNTRIES = ['NGN' => 'NG', 'KES' => 'KE', 'GHS' => 'GH', 'ZAR' => 'ZA'];

    /* ------------------------------------------------------------------ per-provider hooks */

    /** The merchant service that talks to this provider. */
    abstract protected function manager(): MerchantWalletService;

    /** The payout audit model for this manager (must use RecordsPayoutStatus). */
    abstract protected function payoutModel(): string;

    /** The blade this component renders (each provider has its own thin wrapper blade). */
    abstract protected function viewName(): string;

    /** The manager slug recorded on the audit row (Korapay: "korapay", Flutterwave: "flutterwave"). */
    protected function providerValue(): string
    {
        return $this->manager()->name();
    }

    /** Wallets this provider's payouts may be funded from (empty hides the wallet picker). */
    protected function debitCurrencies(): array
    {
        return [];
    }

    /**
     * Provider-specific audit columns to record once the payout has been attempted (default: none).
     *
     * @return array<string, mixed>
     */
    protected function auditExtras(PayoutResult $result): array
    {
        return [];
    }

    public string $currency = 'NGN';

    /** Some providers let a payout be funded from another wallet (cross-currency transfer). */
    public string $debitCurrency = '';

    public string $bankCode = '';

    public string $accountNumber = '';

    public string $accountName = '';

    public string $amount = '';

    public string $narration = '';

    public string $note = '';

    public string $customerName = '';

    public string $customerEmail = '';

    public string $reference = '';

    public bool $showConfirm = false;

    /** True once the account name came back from the bank-account resolve API. */
    public bool $accountResolved = false;

    public ?string $lastReference = null;

    public function mount(): void
    {
        $this->assertSuperAdmin();

        $admin = auth('admin')->user();

        $this->currency = $this->manager()->payoutCurrencies()[0] ?? 'NGN';
        $this->reference = $this->payoutModel()::generateReference();
        $this->customerName = (string) $admin->name;
        $this->customerEmail = (string) $admin->email;
        $this->narration = 'Payout from '.config('app.name');
    }

    public function updatedCurrency(): void
    {
        $this->assertSuperAdmin();
        $this->reset('bankCode', 'accountName', 'debitCurrency');
        $this->accountResolved = false;
        $this->resetValidation(['bankCode', 'accountName']);
    }

    public function updatedAccountNumber(): void
    {
        // Any manual edit invalidates a previously resolved name.
        $this->accountResolved = false;
    }

    /** Ask the provider who owns the destination account before money leaves the wallet. */
    public function resolveAccount(): void
    {
        $this->assertSuperAdmin();

        $data = $this->validate([
            'currency' => ['required', Rule::in($this->payoutCurrencies())],
            'bankCode' => ['required', 'string', 'max:20'],
            'accountNumber' => ['required', 'string', 'max:34', 'regex:/^[0-9]+$/'],
        ], [
            'accountNumber.regex' => 'The account number may only contain digits.',
        ]);

        $result = $this->manager()->resolveBankAccount($data['bankCode'], $data['accountNumber'], $data['currency']);

        if (! $result['ok']) {
            $this->accountResolved = false;
            $this->dispatch('toast', message: $result['message'] ?? 'That account could not be resolved.', type: 'error');

            return;
        }

        $this->accountName = (string) ($result['account_name'] ?? '');
        $this->accountResolved = filled($this->accountName);
        $this->resetValidation('accountName');

        $this->dispatch('toast', message: $this->accountName !== '' ? 'Account resolved: '.$this->accountName : 'Account resolved.', type: 'success');
    }

    /** Step 1 → open the confirmation panel (nothing is sent yet). */
    public function confirm(): void
    {
        $this->assertSuperAdmin();

        $this->validatePayment();

        $this->showConfirm = true;
    }

    public function cancelConfirm(): void
    {
        $this->showConfirm = false;
    }

    /** Step 2 → actually disburse, then record what happened. */
    public function send(): void
    {
        $this->assertSuperAdmin();

        $data = $this->validatePayment();
        $manager = $this->manager();
        $admin = auth('admin')->user();
        $model = $this->payoutModel();

        // Double-submit guard: an identical in-flight payout in the last 5 minutes must be answered
        // by looking at it, not by sending a second one.
        $duplicate = $model::query()
            ->inFlight()
            ->where('currency', $data['currency'])
            ->where('account_number', $data['accountNumber'])
            ->where('amount', round((float) $data['amount'], 2))
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest()
            ->first();

        if ($duplicate !== null) {
            $this->showConfirm = false;
            $this->dispatch('toast', message: "An identical payout ({$duplicate->reference}) is still in flight — verify it before sending again.", type: 'warning');

            return;
        }

        $payout = $model::create([
            'admin_id' => $admin->id,
            'reference' => $data['reference'],
            'provider' => $this->providerValue(),
            'destination_type' => 'bank_account',
            'currency' => $data['currency'],
            'amount' => round((float) $data['amount'], 2),
            'status' => 'unknown',
            'narration' => $data['narration'],
            'note' => $data['note'] ?: null,
            'bank_code' => $data['bankCode'],
            'bank_name' => $this->bankName(),
            'account_number' => $data['accountNumber'],
            'account_name' => $data['accountName'] ?: null,
            'customer_name' => $data['customerName'],
            'customer_email' => $data['customerEmail'],
            'debit_currency' => $data['debitCurrency'] ?: null,
            'ip_address' => request()->ip(),
            'initiated_at' => now(),
        ]);

        $result = $manager->disburse(new PayoutRequest(
            reference: $data['reference'],
            destinationType: 'bank_account',
            amount: (float) $data['amount'],
            currency: $data['currency'],
            narration: $data['narration'],
            accountNumber: $data['accountNumber'],
            bankCode: $data['bankCode'],
            bankName: $this->bankName(),
            accountName: $data['accountName'] ?: null,
            customerName: $data['customerName'],
            customerEmail: $data['customerEmail'],
            debitCurrency: $data['debitCurrency'] ?: null,
            metadata: [
                'source' => 'nextel-bo',
                'admin' => substr((string) $admin->id, 0, 20),
            ],
        ));

        $payout->update([
            'status' => $result->status,
            'fee' => $result->fee > 0 ? $result->fee : null,
            'gateway_reference' => $result->gatewayId
                ?? ($result->reference !== $payout->reference ? $result->reference : null),
            'trace_id' => $result->traceId,
            'request' => $result->data['payload'] ?? null,
            'response' => $result->data,
            // Record why it did not go through: a definitive rejection, or an unanswered request.
            'error' => $result->failed() || ! $result->ok ? $result->message : null,
            ...$this->auditExtras($result),
        ]);

        $this->showConfirm = false;
        $this->lastReference = $payout->reference;

        [$message, $type] = match (true) {
            $result->completed() => ["Payout {$payout->reference} sent successfully.", 'success'],
            $result->inFlight() => ["Payout {$payout->reference} is processing — verify it before treating it as final.", 'info'],
            $result->failed() => ["Payout {$payout->reference} was rejected: ".($result->message ?? 'unknown reason'), 'error'],
            default => ["Payout {$payout->reference} has no definitive answer yet — verify the reference before retrying.", 'warning'],
        };

        $this->dispatch('toast', message: $message, type: $type);

        $this->reset('amount', 'accountName', 'accountResolved', 'customerName', 'customerEmail', 'narration', 'note', 'debitCurrency');
        $this->narration = 'Payout from '.config('app.name');
        $this->reference = $model::generateReference();
        $this->resetValidation();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayment(): array
    {
        return $this->validate([
            'reference' => ['required', 'string', 'min:5', 'max:40', Rule::unique($this->payoutModel()::make()->getTable(), 'reference')],
            'currency' => ['required', Rule::in($this->payoutCurrencies())],
            'bankCode' => ['required', 'string', 'max:20'],
            'accountNumber' => ['required', 'string', 'max:34', 'regex:/^[0-9]+$/'],
            'accountName' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$this->maxAmount()],
            'debitCurrency' => ['nullable', 'string', 'size:3', Rule::in($this->debitCurrencies())],
            'narration' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:200'],
            'customerName' => ['nullable', 'string', 'max:100'],
            'customerEmail' => ['required', 'email', 'max:120'],
        ], [
            'accountNumber.regex' => 'The account number may only contain digits.',
            'amount.max' => 'The most you can send in one payout is '.number_format($this->maxAmount(), 2).' '.$this->currency.'.',
            'reference.unique' => 'That payout reference has already been used — reload the page for a fresh one.',
        ]);
    }

    /** @return list<string> */
    private function payoutCurrencies(): array
    {
        return $this->manager()->payoutCurrencies();
    }

    /** The ceiling comes from the ACTIVE manager's config — never another provider's. */
    private function maxAmount(): float
    {
        return $this->manager()->maxPayoutAmount();
    }

    private function bankName(): ?string
    {
        return Bank::query()
            ->where('code', $this->bankCode)
            ->where('country', $this->country())
            ->value('name');
    }

    private function country(): string
    {
        return self::CURRENCY_COUNTRIES[strtoupper($this->currency)] ?? 'NG';
    }

    public function render(): View
    {
        $model = $this->payoutModel();

        return view($this->viewName(), [
            'banks' => Bank::query()
                ->active()
                ->forCountry($this->country())
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'currencies' => $this->payoutCurrencies(),
            'debitCurrencies' => $this->debitCurrencies(),
            'maxAmount' => $this->maxAmount(),
            'country' => $this->country(),
            'selectedBank' => $this->bankCode !== '' ? $this->bankName() : null,
            'recentPayouts' => $model::query()->with('admin')->latest()->limit(8)->get(),
            'managerConfigured' => $this->manager()->configured(),
            'testMode' => $this->manager()->isTestMode(),
        ]);
    }
}
