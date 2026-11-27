<?php

namespace App\Application\Payments;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Support\PaymentGatewaySettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live merchant balances straight from each gateway's balance API.
 *
 * Provider shapes differ (and change), so parsing is defensive: the amount is
 * looked up under `available_balance`, `available`, `balance` or `amount`, and a
 * malformed payload becomes an error on that gateway rather than an exception
 * that takes the console down.
 *
 * Endpoints are configurable (`trustlens.payment_gateways.*.balance_path`) so an
 * operations change does not need a deployment.
 */
final class GatewayBalances
{
    public const PROVIDERS = ['korapay', 'paystack', 'flutterwave', 'bachs'];

    private const LABELS = [
        'korapay' => 'Korapay',
        'paystack' => 'Paystack',
        'flutterwave' => 'Flutterwave',
        'bachs' => 'Bachs Pay',
    ];

    /** Providers that already report balances in minor units. */
    private const MINOR_UNIT_PROVIDERS = ['paystack'];

    /**
     * @return array{fetched_at:string,gateways:array<int, array{
     *     gateway:string,label:string,configured:bool,
     *     balances:array<int, array{currency:string,available_minor:int}>,
     *     error:?string
     * }>}
     */
    public function fetch(): array
    {
        $gateways = [];

        foreach (self::PROVIDERS as $gateway) {
            $gateways[] = $this->forGateway($gateway);
        }

        return ['fetched_at' => now()->toIso8601String(), 'gateways' => $gateways];
    }

    /**
     * @return array{gateway:string,label:string,configured:bool,balances:array<int, array{currency:string,available_minor:int}>,error:?string}
     */
    public function forGateway(string $gateway): array
    {
        $config = (array) config('trustlens.payment_gateways.'.$gateway, []);
        $configured = in_array($gateway, (array) config('trustlens.implemented_payment_gateways', []), true)
            && filled($config['secret_key'] ?? null);

        $result = [
            'gateway' => $gateway,
            'label' => self::LABELS[$gateway] ?? ucfirst($gateway),
            'configured' => $configured,
            'balances' => [],
            'error' => null,
        ];

        if (! $configured) {
            $result['error'] = 'This gateway is not enabled (missing API credentials).';

            return $result;
        }

        try {
            $result['balances'] = $this->query($gateway, $config);
            if ($result['balances'] === []) {
                $result['error'] = 'The provider returned no balance rows.';
            }
        } catch (ConnectionException) {
            $result['error'] = 'The provider could not be reached.';
        } catch (Throwable $exception) {
            report($exception);
            $result['error'] = 'The provider rejected the balance request: '.mb_substr($exception->getMessage(), 0, 200);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array{currency:string,available_minor:int}>
     */
    private function query(string $gateway, array $config): array
    {
        $base = rtrim((string) ($config['base_url'] ?? ''), '/');
        $secret = (string) ($config['secret_key'] ?? '');
        $path = (string) ($config['balance_path'] ?? match ($gateway) {
            'paystack' => '/balance',
            'flutterwave' => '/balances/{currency}',
            default => '/balances',
        });

        $client = Http::acceptJson()->withToken($secret)->timeout(12);

        if ($gateway === 'flutterwave') {
            $rows = [];
            foreach ($this->flutterwaveCurrencies() as $currency) {
                $response = $client->get($base.str_replace('{currency}', $currency, $path));
                $response->throw();
                $payload = $response->json();
                $row = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
                if (is_array($row)) {
                    $rows[] = $row + ['currency' => $row['currency'] ?? $currency];
                }
            }

            return $this->normalise($rows, $this->reportsMinorUnits($gateway));
        }

        $response = $client->get($base.$path);
        $response->throw();
        $payload = $response->json();
        $data = $payload['data'] ?? $payload;

        // Either a list of currency rows or a single object.
        $rows = is_array($data) && array_is_list($data) ? $data : [$data];

        return $this->normalise(array_filter($rows, 'is_array'), $this->reportsMinorUnits($gateway));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{currency:string,available_minor:int}>
     */
    private function normalise(array $rows, bool $alreadyMinor): array
    {
        $balances = [];

        foreach ($rows as $row) {
            $currency = strtoupper(trim((string) ($row['currency'] ?? $row['currency_code'] ?? '')));
            $amount = $row['available_balance'] ?? $row['available'] ?? $row['balance'] ?? $row['amount'] ?? null;

            if ($currency === '' || ! is_numeric($amount)) {
                continue;
            }

            $balances[] = [
                'currency' => $currency,
                'available_minor' => $alreadyMinor
                    ? (int) round((float) $amount)
                    : CurrencyMinorUnits::toMinor((float) $amount, $currency),
            ];
        }

        return array_values($balances);
    }

    private function reportsMinorUnits(string $gateway): bool
    {
        return in_array($gateway, self::MINOR_UNIT_PROVIDERS, true);
    }

    /** @return array<int, string> */
    private function flutterwaveCurrencies(): array
    {
        $configured = PaymentGatewaySettings::all()['flutterwave'] ?? [];

        return $configured !== [] ? array_values($configured) : ['NGN', 'USD'];
    }
}
