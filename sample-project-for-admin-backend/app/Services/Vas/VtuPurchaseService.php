<?php

namespace App\Services\Vas;

use App\Enums\TransactionType;
use App\Enums\VtuStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VtuOrder;
use App\Services\CurrencyService;
use App\Services\Vas\DTO\BillFulfilmentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class VtuPurchaseService
{
    public function __construct(
        private BillProviderManager $providers,
        private VtuPolicy $policy,
        private CurrencyService $currency,
    ) {}

    public function purchase(User $user, array $params): VtuOrder
    {
        $wallet = (string) $params['wallet'];
        $ngn = round((float) $params['amount_ngn'], 2);

        $check = $this->policy->check($user, $wallet, $ngn);
        if (! $check['allowed']) {
            throw ValidationException::withMessages(['amount' => $check['reason']]);
        }

        $provider = $this->providers->active();
        if (! $provider->configured()) {
            throw ValidationException::withMessages(['amount' => 'Bill payments are temporarily unavailable.']);
        }

        $column = $wallet === User::BILLS_WALLET ? User::WALLET_COLUMNS[$wallet] : null;
        if ($column === null) {
            throw ValidationException::withMessages(['wallet' => 'That wallet cannot be used for bill payments.']);
        }

        $rate = $this->currency->rate('NG');
        if ($rate <= 0) {
            throw ValidationException::withMessages(['amount' => 'Bill payments are temporarily unavailable (no exchange rate set).']);
        }

        $usd = $this->currency->toUsd($ngn, 'NG');
        if ($usd <= 0) {
            throw ValidationException::withMessages(['amount' => 'That amount is too small to charge.']);
        }

        $requestId = VtuOrder::generateRequestId();

        $order = DB::transaction(function () use ($user, $wallet, $column, $ngn, $usd, $requestId, $provider, $params): VtuOrder {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            $remaining = $this->policy->remainingPurchases($locked, $wallet);
            if ($remaining !== null && $remaining < 1) {
                throw ValidationException::withMessages(['amount' => 'You have reached the bill-payment limit for this wallet.']);
            }

            if ((float) $locked->{$column} < $usd) {
                throw ValidationException::withMessages(['amount' => 'Insufficient balance in this wallet.']);
            }

            $locked->decrement($column, $usd);

            $transaction = Transaction::create([
                'user_id' => $locked->id,
                'type' => TransactionType::VTU_PURCHASE,
                'direction' => 'debit',
                'amount' => $usd,
                'fee' => 0,
                'wallet' => $wallet,
                'status' => 'pending',
                'reference' => $requestId,
                'description' => $this->describe($params),
                'meta' => array_filter([
                    'service' => $params['service'],
                    'service_id' => $params['service_id'],
                    'amount_ngn' => $ngn,
                    'recipient' => $params['billers_code'] ?? $params['phone'] ?? null,
                    'provider' => $provider->name(),
                    'network' => $this->networkName($params['service'], $params['service_id']),
                    'wallet_label' => User::WALLET_LABELS[$wallet] ?? null,
                ], fn ($v) => $v !== null),
            ]);

            return VtuOrder::create([
                'user_id' => $locked->id,
                'service' => $params['service'],
                'service_id' => $params['service_id'],
                'provider' => $provider->name(),
                'wallet' => $wallet,
                'variation_code' => $params['variation_code'] ?? null,
                'billers_code' => $params['billers_code'] ?? null,
                'phone' => $params['phone'] ?? null,
                'amount_ngn' => $ngn,
                'amount_usd' => $usd,
                'fee' => 0,
                'request_id' => $requestId,
                'status' => VtuStatus::PENDING,
                'transaction_id' => $transaction->id,
                'meta' => array_merge(
                    $params['meta'] ?? [],
                    filled($params['extra'] ?? null) ? ['extra' => $params['extra']] : [],
                ),
            ]);
        });

        $payResult = $provider->pay($order);
        $order = $this->settle($order, $payResult);

        // Flutterwave accepts-then-confirms: when the provider ACCEPTED the bill (reached) but left it
        // pending, resolve once now for an immediate result. A transport failure (unreachable) is left
        // pending for the delayed sweep, so we never refund a bill that may still be in flight.
        if ($payResult->reached && $order->status->isPending()) {
            $order = $this->requery($order);
        }

        return $order;
    }

    public function requery(VtuOrder $order): VtuOrder
    {
        if (! $order->status->isPending()) {
            return $order;
        }

        $provider = $this->providers->driver($order->provider ?: 'vtpass');

        return $this->settle($order, $provider->requery($order));
    }

    private function settle(VtuOrder $order, BillFulfilmentResult $result): VtuOrder
    {
        // Couldn't reach the provider — leave the order pending for the requery sweep.
        if (! $result->reached) {
            return $order;
        }

        // Settle under an order-row lock and re-check the status: any concurrent settler (webhook,
        // sweep, inline requery, backoffice) that arrives after the terminal transition sees a
        // non-pending row and returns without touching money — so a failed bill refunds exactly once.
        return DB::transaction(function () use ($order, $result): VtuOrder {
            $locked = VtuOrder::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->status->isPending()) {
                return $locked ?? $order;
            }

            $locked->fill([
                'status' => $result->status,
                'external_id' => $result->externalId ?? $locked->external_id,
                'token' => $result->token ?? $locked->token,
                'meta' => array_merge((array) $locked->meta, ['provider_response' => $result->raw]),
            ]);

            if ($result->status->isRefunded()) {
                $column = User::WALLET_COLUMNS[$locked->wallet] ?? null;
                if ($column !== null) {
                    User::whereKey($locked->user_id)->lockForUpdate()->first()?->increment($column, (float) $locked->amount_usd);
                    Transaction::whereKey($locked->transaction_id)->update(['status' => 'failed']);
                    $locked->refunded = true;
                }
            }

            if ($result->status === VtuStatus::DELIVERED) {
                $txn = Transaction::find($locked->transaction_id);
                if ($txn) {
                    $txn->status = 'completed';

                    if (filled($locked->token)) {
                        $txn->meta = array_merge((array) $txn->meta, ['token' => $locked->token]);
                    }
                    $txn->save();
                }
            }

            $locked->save();

            return $locked;
        });
    }

    private function networkName(string $service, string $serviceId): ?string
    {
        if (! in_array($service, [VtuOrder::SERVICE_AIRTIME, VtuOrder::SERVICE_DATA], true)) {
            return null;
        }

        $stem = str_replace('-data', '', strtolower($serviceId));

        return match ($stem) {
            'mtn' => 'MTN',
            'glo' => 'Glo',
            'airtel' => 'Airtel',
            '9mobile', 'etisalat' => '9mobile',
            default => null,
        };
    }

    private function describe(array $params): string
    {
        // Prefer the human provider name (e.g. "9mobile", "DSTV") over the raw service id / biller code.
        $provider = trim((string) ($params['meta']['provider_label'] ?? '')) ?: strtoupper((string) $params['service_id']);

        return match ($params['service']) {
            VtuOrder::SERVICE_AIRTIME => 'Airtime purchase ('.$provider.') '.($params['phone'] ?? ''),
            VtuOrder::SERVICE_DATA => 'Data purchase ('.$provider.') '.($params['phone'] ?? ''),
            VtuOrder::SERVICE_TV => 'TV subscription ('.$provider.')',
            VtuOrder::SERVICE_ELECTRICITY => 'Electricity ('.$provider.')',
            VtuOrder::SERVICE_EDUCATION => 'Education ('.$provider.')',
            default => 'Bill payment',
        };
    }
}
