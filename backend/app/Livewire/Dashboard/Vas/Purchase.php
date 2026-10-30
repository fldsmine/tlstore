<?php

namespace App\Livewire\Dashboard\Vas;

use App\Contracts\BillPaymentProvider;
use App\Livewire\Concerns\VerifiesTransactionPin;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Vas\BillProviderManager;
use App\Services\Vas\VtuCatalog;
use App\Services\Vas\VtuPolicy;
use App\Services\Vas\VtuPurchaseService;
use App\Support\DisplayCurrency;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Value Added Services')]
class Purchase extends Component
{
    use VerifiesTransactionPin;

    public string $category = '';

    public string $serviceId = '';

    public string $wallet = '';

    public ?string $amount = null;

    public ?string $variationCode = null;

    public string $recipient = '';

    public string $phone = '';

    public string $meterType = 'prepaid';

    public string $subscriptionType = 'renew';

    public ?string $customerName = null;

    public bool $verified = false;

    public function mount(string $category): void
    {
        abort_unless(VtuCatalog::exists($category), 404);
        abort_unless((bool) config('settings.vtu.enabled', false), 404);

        $this->category = $category;
        $this->serviceId = $this->providersFor()[0]['id'] ?? '';
        $this->wallet = $this->usableWallets()->keys()->first() ?? '';
        $this->phone = (string) (auth()->user()->phone ?? '');
    }

    /** The admin-selected fulfilment provider — it owns the catalogue and the fulfilment flow. */
    protected function provider(): BillPaymentProvider
    {
        return app(BillProviderManager::class)->active();
    }

    /**
     * Billers/networks for this category, from the active provider.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function providersFor(): array
    {
        return $this->provider()->services()[$this->category]['providers'] ?? [];
    }

    /** @return array<string, mixed>|null */
    protected function providerEntry(string $serviceId): ?array
    {
        foreach ($this->providersFor() as $provider) {
            if (($provider['id'] ?? null) === $serviceId) {
                return $provider;
            }
        }

        return null;
    }

    /** @return array<int, array{code: string, label: string, amount: float}> */
    protected function variationsFor(string $serviceId): array
    {
        return $this->provider()->variations($this->category, $serviceId);
    }

    public function verify(string $serviceId, string $recipient, ?string $type = null): array
    {
        $recipient = trim($recipient);
        $label = strtolower($this->recipientLabelFor($serviceId));

        if ($recipient === '') {
            return ['ok' => false, 'error' => "Enter the {$label} first."];
        }

        $result = $this->provider()->validateCustomer($this->category, $serviceId, $recipient, $type ?: null);

        if (! ($result['ok'] ?? false) || blank($result['name'] ?? null)) {
            return ['ok' => false, 'error' => ($result['message'] ?? '') ?: "We could not verify that {$label}. Check it and try again."];
        }

        return ['ok' => true, 'name' => (string) $result['name']];
    }

    public function purchase(array $data): array
    {
        $this->fill([
            'serviceId' => (string) ($data['serviceId'] ?? ''),
            'wallet' => (string) ($data['wallet'] ?? ''),
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'variationCode' => $data['variationCode'] ?? null,
            'recipient' => (string) ($data['recipient'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'meterType' => (string) ($data['meterType'] ?? 'prepaid'),
            'subscriptionType' => (string) ($data['subscriptionType'] ?? 'renew'),
            'customerName' => $data['customerName'] ?? null,
        ]);

        $this->verified = ! $this->needsVerify() || filled($this->customerName);

        try {
            $this->validateOrder();
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => (string) collect($e->errors())->flatten()->first()];
        }

        if (! $this->verifyTransactionPin((string) ($data['pin'] ?? ''))) {
            return ['ok' => false, 'error' => $this->getErrorBag()->first('pin') ?: 'Your transaction PIN is incorrect.'];
        }

        try {
            $order = app(VtuPurchaseService::class)->purchase(auth()->user(), $this->orderParams());
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => (string) collect($e->errors())->flatten()->first()];
        }

        if ($order->status->isRefunded()) {
            return ['ok' => false, 'error' => 'The purchase could not be completed and your wallet was not charged. Please try again.'];
        }

        $this->redirectRoute('vas.success', ['ref' => $order->request_id], navigate: true);

        return ['ok' => true];
    }

    protected function validateOrder(): void
    {
        $errors = [];

        if (! $this->usableWallets()->has($this->wallet)) {
            $errors['wallet'] = 'Choose a wallet to pay from.';
        }

        if (! $this->providerEntry($this->serviceId)) {
            $errors['serviceId'] = 'Choose a provider.';
        }

        if ($this->entersAmount()) {
            if (! is_numeric($this->amount) || (float) $this->amount <= 0) {
                $errors['amount'] = 'Enter a valid amount.';
            }
        } elseif (blank($this->variationCode) || $this->selectedPlan() === null) {
            $errors['variationCode'] = 'Choose a plan.';
        }

        if (trim($this->recipient) === '') {
            $errors['recipient'] = 'Enter the '.strtolower($this->recipientLabel()).'.';
        } elseif ($this->recipientIsPhone() && ! preg_match('/^0\d{10}$/', trim($this->recipient))) {
            $errors['recipient'] = 'Enter a valid 11-digit phone number.';
        }

        if (! $this->recipientIsPhone() && ! preg_match('/^0\d{10}$/', trim($this->phone))) {
            $errors['phone'] = 'Enter a valid 11-digit contact phone.';
        }

        if ($this->needsVerify() && ! $this->verified) {
            $errors['recipient'] = 'Verify the '.strtolower($this->recipientLabel()).' before continuing.';
        }

        if (! isset($errors['amount']) && ! isset($errors['variationCode'])) {
            $check = app(VtuPolicy::class)->check(auth()->user(), $this->wallet, $this->ngnAmount());
            if (! $check['allowed']) {
                $errors['amount'] = $check['reason'];
            } elseif ($this->usdAmount() > $this->available()) {
                $errors['amount'] = 'That is more than your '.$this->walletLabel().' balance.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function orderParams(): array
    {
        $recipientIsPhone = $this->recipientIsPhone();
        $phone = $recipientIsPhone ? trim($this->recipient) : trim($this->phone);
        $billers = $recipientIsPhone
            ? ($this->category === 'data' ? trim($this->recipient) : null)
            : trim($this->recipient);
        $variation = $this->entersAmount()
            ? ($this->category === 'electricity' ? $this->meterType : null)
            : $this->variationCode;

        return [
            'service' => $this->category,
            'service_id' => $this->serviceId,
            'wallet' => $this->wallet,
            'amount_ngn' => $this->ngnAmount(),
            'phone' => $phone,
            'billers_code' => $billers,
            'variation_code' => $variation,
            'extra' => $this->category === 'tv' ? ['subscription_type' => $this->subscriptionType] : [],
            'meta' => array_filter([
                'provider_label' => $this->providerEntry($this->serviceId)['label'] ?? $this->serviceId,
                'plan_label' => $this->selectedPlan()['label'] ?? null,
                'customer_name' => $this->customerName,
            ], fn ($v) => $v !== null),
        ];
    }

    protected function categoryConfig(): array
    {
        return VtuCatalog::category($this->category) ?? [];
    }

    protected function entersAmount(): bool
    {
        return ($this->categoryConfig()['pricing'] ?? 'amount') === 'amount';
    }

    protected function needsVerify(): bool
    {
        $provider = $this->providerEntry($this->serviceId) ?? [];

        return (bool) ($provider['verify'] ?? $this->categoryConfig()['verify'] ?? false);
    }

    protected function recipientLabel(): string
    {
        return $this->recipientLabelFor($this->serviceId);
    }

    protected function recipientLabelFor(string $serviceId): string
    {
        $provider = $this->providerEntry($serviceId) ?? [];

        return (string) ($provider['recipient'] ?? $this->categoryConfig()['recipient'] ?? 'Phone number');
    }

    protected function recipientIsPhone(): bool
    {
        return str_contains(strtolower($this->recipientLabel()), 'phone');
    }

    protected function selectedPlan(): ?array
    {
        foreach ($this->variationsFor($this->serviceId) as $plan) {
            if ($plan['code'] === $this->variationCode) {
                return $plan;
            }
        }

        return null;
    }

    protected function ngnAmount(): float
    {
        return $this->entersAmount()
            ? round((float) $this->amount, 2)
            : (float) ($this->selectedPlan()['amount'] ?? 0);
    }

    protected function usdAmount(): float
    {
        return app(CurrencyService::class)->toUsd($this->ngnAmount(), 'NG');
    }

    protected function usableWallets(): Collection
    {
        $policy = app(VtuPolicy::class);
        $fx = app(CurrencyService::class);
        $user = auth()->user();

        return collect([User::BILLS_WALLET])
            ->mapWithKeys(fn (string $key): array => [$key => User::WALLET_COLUMNS[$key]])
            ->filter(fn ($col, $key) => $policy->isWalletUsable($key))
            ->map(fn ($col, $key) => [
                'label' => User::WALLET_LABELS[$key] ?? $key,
                'balance' => (float) $user->{$col},
                'display' => money((float) $user->{$col}),
                'ngn' => $fx->toNgn((float) $user->{$col}),
                'min' => $policy->minNgn($key),
                'max' => $policy->maxNgn($key),
            ]);
    }

    protected function available(): float
    {
        return (float) (auth()->user()->{User::WALLET_COLUMNS[$this->wallet] ?? 'bills_balance'} ?? 0);
    }

    protected function walletLabel(): string
    {
        return User::WALLET_LABELS[$this->wallet] ?? 'wallet';
    }

    protected function alpineState(): array
    {
        $config = $this->categoryConfig();
        $pricing = $config['pricing'] ?? 'amount';

        $providers = collect($this->providersFor())
            ->map(fn (array $p): array => [
                'id' => $p['id'],
                'label' => $p['label'],
                'logo' => empty($p['logo']) ? null : asset('images/networks/'.$p['logo']),
                'verify' => (bool) ($p['verify'] ?? $config['verify'] ?? false),
                'recipient' => (string) ($p['recipient'] ?? $config['recipient'] ?? 'Phone number'),
            ])
            ->values()
            ->all();

        $plansByProvider = [];
        if ($pricing === 'variation') {
            foreach ($providers as $p) {
                $plansByProvider[$p['id']] = $this->variationsFor($p['id']);
            }
        }

        return [
            'category' => $this->category,
            'label' => $config['label'] ?? 'Buy',
            'pricing' => $pricing,
            'subscription' => (bool) ($config['subscription'] ?? false),
            'providers' => $providers,
            'plansByProvider' => $plansByProvider,
            'meterTypes' => VtuCatalog::meterTypes(),
            'wallets' => $this->usableWallets()->all(),
            'isLocal' => DisplayCurrency::isLocal(),
            'rate' => app(CurrencyService::class)->rate('NG'),
            'serviceId' => $this->serviceId,
            'wallet' => $this->wallet,
            'phone' => $this->phone,
        ];
    }

    public function render(): View
    {
        return view('livewire.dashboard.vas.purchase', [
            'hasWallet' => $this->usableWallets()->isNotEmpty(),
            'init' => $this->alpineState(),
        ]);
    }
}
