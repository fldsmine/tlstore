<?php

namespace App\Application\Actions\Campaigns;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Money\CurrencyConverter;
use App\Models\LedgerEntry;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserCampaignOrder;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateCampaignOrder
{
    public function __construct(private readonly CurrencyConverter $converter) {}

    public function handle(User $user, array $data): UserCampaignOrder
    {
        $services = SystemSetting::query()->where('setting_key', 'campaign.services')->first()?->value ?? [];
        $currency = strtoupper((string) (SystemSetting::query()->where('setting_key', 'campaign.currency')->first()?->value ?? 'NGN'));
        $feePercent = (float) (SystemSetting::query()->where('setting_key', 'campaign.service_fee_percent')->first()?->value ?? 5);
        $platform = strtolower($data['platform']);
        $action = strtolower($data['action']);
        $platformConfig = $services[$platform] ?? [];
        $amount = $platformConfig[$action] ?? null;
        if (! is_numeric($amount) || $action === 'min') {
            throw ValidationException::withMessages(['action' => 'That campaign service is not available.']);
        }

        $minimum = (int) ($platformConfig['min'] ?? 1);
        if ((int) $data['quantity'] < $minimum) {
            throw ValidationException::withMessages(['quantity' => "The minimum quantity for this service is {$minimum}."]);
        }
        $unitPriceMinor = CurrencyMinorUnits::toMinor($amount, $currency);
        $subtotal = $unitPriceMinor * (int) $data['quantity'];
        $fee = (int) round($subtotal * $feePercent / 100);
        $total = $subtotal + $fee;
        $key = $user->id.':'.$data['idempotency_key'];

        return DB::transaction(function () use ($user, $data, $platform, $action, $currency, $feePercent, $unitPriceMinor, $subtotal, $fee, $total, $key): UserCampaignOrder {
            $existing = UserCampaignOrder::query()->where('idempotency_key', $key)->first();
            if ($existing) return $existing;

            $wallet = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')
                ->where('currency', strtoupper($user->wallet_currency ?: $user->preferred_currency ?: 'USD'))
                ->where('status', 'active')->lockForUpdate()->first();
            if (! $wallet) {
                throw ValidationException::withMessages(['wallet' => 'Your top-up funds wallet is not available.']);
            }
            $debitQuote = $this->converter->convertMinor($total, $currency, $wallet->currency);
            $debit = (int) $debitQuote['amount_minor'];
            if ((int) $wallet->balance_minor < $debit) {
                throw ValidationException::withMessages(['wallet' => 'Your top-up funds are not enough. Top up your funds wallet to continue.']);
            }

            $order = UserCampaignOrder::query()->create([
                'user_id' => $user->id,
                'campaign_name' => $data['campaign_name'],
                'platform' => $platform,
                'action' => $action,
                'quantity' => $data['quantity'],
                'target_url' => $data['target_url'],
                'instructions' => $data['instructions'] ?? null,
                'currency' => $currency,
                'unit_price_minor' => $unitPriceMinor,
                'subtotal_minor' => $subtotal,
                'service_fee_percent' => $feePercent,
                'service_fee_minor' => $fee,
                'total_minor' => $total,
                'debited_amount_minor' => $debit,
                'debited_currency' => $wallet->currency,
                'status' => 'pending',
                'idempotency_key' => $key,
            ]);

            LedgerEntry::query()->create([
                'wallet_id' => $wallet->id,
                'entry_type' => 'campaign_order_debit',
                'direction' => 'debit',
                'amount_minor' => $debit,
                'status' => 'posted',
                'source_type' => 'user_campaign_order',
                'source_id' => $order->id,
                'idempotency_key' => 'campaign-order:'.$order->id.':debit',
                'description' => 'Campaign order '.$order->campaign_name,
                'metadata' => [
                    'campaign_total_minor' => $total, 'campaign_currency' => $currency,
                    'exchange_rate' => $debitQuote['rate'], 'quantity' => $order->quantity,
                ],
                'available_at' => now(), 'posted_at' => now(),
            ]);

            return $order;
        });
    }
}
