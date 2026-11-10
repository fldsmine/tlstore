<?php

namespace App\Application\Actions\Wallets;

use App\Models\Bank;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

final class ResolvePayoutAccount
{
    /** @return array{provider:string,account_name:string,account_number:string} */
    public function handle(User $user, Bank $bank, string $accountNumber): array
    {
        abort_unless($bank->is_active && $bank->country === strtoupper((string) $user->country_code), 404);
        $setting = SystemSetting::query()->where('setting_key', 'account_validation.provider')->value('value');
        $provider = is_array($setting) ? ($setting['provider'] ?? 'paystack') : ($setting ?: 'paystack');
        $provider = strtolower((string) $provider);
        if (! in_array($provider, ['paystack', 'flutterwave', 'korapay', 'bachs'], true)) {
            $provider = 'paystack';
        }
        $config = config('trustlens.payment_gateways.'.$provider, []);
        $secret = $config['secret_key'] ?? null;
        if (! is_string($secret) || $secret === '') {
            throw ValidationException::withMessages(['account_number' => ucfirst($provider).' account validation is not configured on the server.']);
        }
        if ($bank->isMobileMoney()) {
            throw ValidationException::withMessages(['bank_id' => 'Choose a bank account to validate. Mobile money details are verified by the payout provider when the withdrawal is processed.']);
        }

        $cacheKey = 'payout-account-resolve:'.hash('sha256', implode('|', [$user->id, $provider, $bank->id, $accountNumber]));
        try {
            $resolvedData = Cache::remember($cacheKey, now()->addMinutes(4), function () use ($provider, $config, $secret, $bank, $accountNumber): array {
                $base = rtrim((string) ($config['base_url'] ?? ''), '/');
                $response = match ($provider) {
                    'paystack' => Http::acceptJson()->withToken($secret)->timeout(15)->get($base.'/bank/resolve', ['account_number' => $accountNumber, 'bank_code' => $bank->paystack_code ?: $bank->code]),
                    'flutterwave' => Http::acceptJson()->withToken($secret)->timeout(15)->post($base.'/accounts/resolve', ['account_number' => $accountNumber, 'account_bank' => $bank->flutterwave_code ?: $bank->code, 'country' => $bank->country]),
                    'korapay' => Http::acceptJson()->withToken($secret)->timeout(15)->post($base.'/misc/banks/resolve', ['bank' => $bank->institution_code ?: $bank->code, 'account' => $accountNumber, 'currency' => $bank->country]),
                    'bachs' => Http::acceptJson()->withToken($secret)->timeout(15)->post($base.'/misc/bank-accounts/resolve', ['account_number' => $accountNumber, 'bank_code' => $bank->code, 'country' => $bank->country]),
                };
                if (! $response->successful()) return ['resolved' => false, 'account_name' => null];
                $body = $response->json();
                $accountName = data_get($body, $provider === 'bachs' ? 'account_name' : 'data.account_name');
                $resolved = match ($provider) {
                    'paystack', 'korapay' => data_get($body, 'status') === true,
                    'flutterwave' => data_get($body, 'status') === 'success',
                    'bachs' => data_get($body, 'resolved') === true,
                };
                return ['resolved' => $resolved && is_string($accountName) && trim($accountName) !== '', 'account_name' => is_string($accountName) ? trim($accountName) : null];
            });
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['account_number' => 'The account validation service could not be reached. Please try again.']);
        }
        if (! ($resolvedData['resolved'] ?? false) || ! is_string($resolvedData['account_name'] ?? null)) {
            throw ValidationException::withMessages(['account_number' => 'The selected bank and account number did not resolve to an account name. Check the details and try again.']);
        }

        return ['provider' => $provider, 'account_name' => $resolvedData['account_name'], 'account_number' => $accountNumber];
    }
}
