<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VtuPurchaseRequest;
use App\Http\Requests\Api\V1\VtuVerifyCustomerRequest;
use App\Http\Resources\Api\V1\VtuOrderResource;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Vas\BillProviderManager;
use App\Services\Vas\VtuCatalog;
use App\Services\Vas\VtuPolicy;
use App\Services\Vas\VtuPurchaseService;
use App\Support\ApiResponse;
use App\Support\TransactionPinGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VasController extends Controller
{
    public function __construct(
        private BillProviderManager $providers,
        private VtuPolicy $policy,
        private CurrencyService $currency,
    ) {}

    public function categories(): JsonResponse
    {
        return ApiResponse::success('Bill payment categories retrieved.', [
            'enabled' => $this->policy->enabled(),
            'categories' => collect(VtuCatalog::categories())
                ->map(fn (array $category, string $key): array => [
                    'key' => $key,
                    'label' => $category['label'],
                    'blurb' => $category['blurb'] ?? null,
                    'pricing' => $category['pricing'] ?? 'amount',
                    'verify' => (bool) ($category['verify'] ?? false),
                    'subscription' => (bool) ($category['subscription'] ?? false),
                    'recipient' => $category['recipient'] ?? null,
                ])
                ->values(),
        ]);
    }

    public function category(string $category): JsonResponse
    {
        abort_unless(VtuCatalog::exists($category), 404);

        $config = VtuCatalog::category($category);

        return ApiResponse::success('Billers retrieved.', [
            'category' => [
                'key' => $category,
                'label' => $config['label'],
                'pricing' => $config['pricing'] ?? 'amount',
                'verify' => (bool) ($config['verify'] ?? false),
                'subscription' => (bool) ($config['subscription'] ?? false),
                'recipient' => $config['recipient'] ?? null,
            ],
            'providers' => collect($this->providers->active()->services()[$category]['providers'] ?? [])
                ->map(fn (array $p): array => [
                    'id' => $p['id'],
                    'label' => $p['label'],
                    'logo_url' => empty($p['logo']) ? null : asset('images/networks/'.$p['logo']),
                    'verify' => (bool) ($p['verify'] ?? $config['verify'] ?? false),
                    'recipient' => (string) ($p['recipient'] ?? $config['recipient'] ?? 'Phone number'),
                ])
                ->values(),
            'wallets' => $this->usableWallets(),
        ]);
    }

    public function variations(Request $request, string $serviceId): JsonResponse
    {
        $category = (string) $request->query('category', '');

        if (! VtuCatalog::exists($category)) {
            return ApiResponse::error('Unknown category — pass ?category=airtime|data|tv|electricity|education.', 422);
        }

        $provider = $this->providers->active();

        return ApiResponse::success('Plans retrieved.', [
            'service_id' => $serviceId,
            'plans' => $provider->variations($category, $serviceId),
        ]);
    }

    public function verify(VtuVerifyCustomerRequest $request): JsonResponse
    {
        $provider = $this->providers->active();

        $result = $provider->validateCustomer(
            (string) $request->validated('category'),
            (string) $request->validated('service_id'),
            trim((string) $request->validated('recipient')),
            $request->validated('meter_type') ?: null,
        );

        if (! ($result['ok'] ?? false) || blank($result['name'] ?? null)) {
            $message = ($result['message'] ?? '') ?: 'We could not verify that customer. Check it and try again.';

            return ApiResponse::error($message, 422, ['recipient' => [$message]]);
        }

        return ApiResponse::success('Customer verified.', ['name' => (string) $result['name']]);
    }

    public function purchase(VtuPurchaseRequest $request, VtuPurchaseService $purchases, TransactionPinGuard $pins): JsonResponse
    {
        $user = $request->user();

        $params = $this->orderParams($request);

        $this->validateOrder($request, $user, $params);

        if ($pins->throttled($user)) {
            return ApiResponse::error('Too many incorrect attempts. Try again in '.$pins->availableIn($user).' seconds.', 429)
                ->withHeaders(['Retry-After' => (string) $pins->availableIn($user)]);
        }

        if (! $pins->verify($user, (string) $request->validated('pin'))) {
            return ApiResponse::error('Your transaction PIN is incorrect.', 422, [
                'pin' => ['Your transaction PIN is incorrect.'],
            ]);
        }

        $order = $purchases->purchase($user, $params);

        if ($order->status->isRefunded()) {
            return ApiResponse::error('The purchase could not be completed and your wallet was not charged. Please try again.', 422);
        }

        return ApiResponse::created('Bill payment submitted.', [
            'order' => new VtuOrderResource($order),
        ]);
    }

    /** Server-side validation mirroring the web purchase flow (prices are NEVER client-supplied for variations). */
    private function validateOrder(VtuPurchaseRequest $request, User $user, array $params): void
    {
        $errors = [];

        if (! (bool) config('settings.vtu.enabled', false)) {
            throw ValidationException::withMessages(['category' => 'Bill payments are temporarily unavailable.']);
        }

        if (! $this->policy->isWalletUsable($params['wallet'])) {
            $errors['wallet'] = 'This wallet cannot be used for bill payments.';
        }

        if (! $this->providerEntry($params['service'], $params['service_id'])) {
            $errors['service_id'] = 'Choose a valid provider.';
        }

        if ($this->entersAmount($params['service'])) {
            if (! is_numeric($params['amount_ngn']) || (float) $params['amount_ngn'] <= 0) {
                $errors['amount'] = 'Enter a valid amount.';
            }
        } elseif (blank($params['variation_code']) || $this->selectedPlan($params['service'], $params['service_id'], $params['variation_code']) === null) {
            $errors['variation_code'] = 'Choose a plan.';
        }

        $recipient = trim((string) $params['recipient']);
        if ($recipient === '') {
            $errors['recipient'] = 'Enter the recipient.';
        } elseif ($this->recipientIsPhone($params['service'], $params['service_id']) && ! preg_match('/^0\d{10}$/', $recipient)) {
            $errors['recipient'] = 'Enter a valid 11-digit phone number.';
        }

        if (! $this->recipientIsPhone($params['service'], $params['service_id']) && ! preg_match('/^0\d{10}$/', trim((string) $params['phone']))) {
            $errors['phone'] = 'Enter a valid 11-digit contact phone.';
        }

        if ($this->needsVerify($params['service'], $params['service_id']) && blank($params['meta']['customer_name'] ?? null)) {
            $errors['recipient'] = 'Verify the recipient before paying (call /vas/verify first, then pass customer_name).';
        }

        if (! isset($errors['amount']) && ! isset($errors['variation_code'])) {
            $check = $this->policy->check($user, $params['wallet'], (float) $params['amount_ngn']);
            if (! $check['allowed']) {
                $errors['amount'] = $check['reason'];
            } elseif ($this->currency->toUsd((float) $params['amount_ngn'], 'NG') > (float) $user->{User::WALLET_COLUMNS[$params['wallet']] ?? 'bills_balance'}) {
                $errors['amount'] = 'That is more than your '.(User::WALLET_LABELS[$params['wallet']] ?? 'wallet').' balance.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, mixed> */
    private function orderParams(VtuPurchaseRequest $request): array
    {
        $category = (string) $request->validated('category');
        $serviceId = (string) $request->validated('service_id');
        $recipient = trim((string) $request->validated('recipient'));

        $recipientIsPhone = $this->recipientIsPhone($category, $serviceId);
        $phone = $recipientIsPhone ? $recipient : trim((string) ($request->validated('phone') ?? ''));
        $billers = $recipientIsPhone
            ? ($category === 'data' ? $recipient : null)
            : $recipient;
        $variation = $this->entersAmount($category)
            ? ($category === 'electricity' ? ($request->validated('meter_type') ?? 'prepaid') : null)
            : $request->validated('variation_code');

        $ngn = $this->entersAmount($category)
            ? round((float) $request->validated('amount'), 2)
            : (float) ($this->selectedPlan($category, $serviceId, (string) $variation)['amount'] ?? 0);

        return [
            'service' => $category,
            'service_id' => $serviceId,
            'wallet' => User::BILLS_WALLET,
            'amount_ngn' => $ngn,
            'recipient' => $recipient,
            'phone' => $phone,
            'billers_code' => $billers,
            'variation_code' => $variation,
            'extra' => $category === 'tv' ? ['subscription_type' => $request->validated('subscription_type') ?? 'renew'] : [],
            'meta' => array_filter([
                'provider_label' => $this->providerEntry($category, $serviceId)['label'] ?? $serviceId,
                'plan_label' => $this->selectedPlan($category, $serviceId, (string) $variation)['label'] ?? null,
                'customer_name' => $request->validated('customer_name') ?? null,
            ], fn ($v) => $v !== null),
        ];
    }

    /** @return array<string, mixed>|null */
    private function providerEntry(string $category, string $serviceId): ?array
    {
        foreach ($this->providers->active()->services()[$category]['providers'] ?? [] as $provider) {
            if (($provider['id'] ?? null) === $serviceId) {
                return $provider;
            }
        }

        return null;
    }

    private function entersAmount(string $category): bool
    {
        return ((VtuCatalog::category($category) ?? [])['pricing'] ?? 'amount') === 'amount';
    }

    private function needsVerify(string $category, string $serviceId): bool
    {
        $provider = $this->providerEntry($category, $serviceId) ?? [];

        return (bool) ($provider['verify'] ?? (VtuCatalog::category($category) ?? [])['verify'] ?? false);
    }

    private function recipientIsPhone(string $category, string $serviceId): bool
    {
        $provider = $this->providerEntry($category, $serviceId) ?? [];
        $label = (string) ($provider['recipient'] ?? (VtuCatalog::category($category) ?? [])['recipient'] ?? 'Phone number');

        return str_contains(strtolower($label), 'phone');
    }

    /** @return array{code: string, label: string, amount: float}|null */
    private function selectedPlan(string $category, string $serviceId, string $variationCode): ?array
    {
        foreach ($this->providers->active()->variations($category, $serviceId) as $plan) {
            if ($plan['code'] === $variationCode) {
                return $plan;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function usableWallets(): array
    {
        $user = auth()->user();

        return collect([User::BILLS_WALLET])
            ->filter(fn (string $key): bool => $this->policy->isWalletUsable($key))
            ->map(fn (string $key): array => [
                'wallet' => $key,
                'label' => User::WALLET_LABELS[$key] ?? $key,
                'balance_usd' => (float) $user->{User::WALLET_COLUMNS[$key]},
                'balance_ngn' => $this->currency->toNgn((float) $user->{User::WALLET_COLUMNS[$key]}),
                'min_ngn' => $this->policy->minNgn($key),
                'max_ngn' => $this->policy->maxNgn($key),
            ])
            ->values()
            ->all();
    }
}
