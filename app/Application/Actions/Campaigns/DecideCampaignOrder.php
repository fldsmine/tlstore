<?php

namespace App\Application\Actions\Campaigns;

use App\Application\Money\CurrencyMinorUnits;
use App\Models\Admin;
use App\Models\Ad;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\UserCampaignOrder;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DecideCampaignOrder
{
    public function handle(Admin $admin, UserCampaignOrder $order, string $decision, ?string $note = null): UserCampaignOrder
    {
        return DB::transaction(function () use ($admin, $order, $decision, $note): UserCampaignOrder {
            $order = UserCampaignOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== 'pending') {
                throw ValidationException::withMessages(['order' => 'This campaign order has already been reviewed.']);
            }

            if ($decision === 'rejected') {
                $wallet = Wallet::query()->where('user_id', $order->user_id)->where('wallet_type', 'topup_fund')
                    ->where('currency', $order->debited_currency)->lockForUpdate()->firstOrFail();
                LedgerEntry::query()->firstOrCreate(
                    ['idempotency_key' => 'campaign-order:'.$order->id.':refund'],
                    [
                        'wallet_id' => $wallet->id,
                        'entry_type' => 'campaign_order_refund',
                        'direction' => 'credit',
                        'amount_minor' => $order->debited_amount_minor,
                        'status' => 'posted',
                        'source_type' => 'user_campaign_order',
                        'source_id' => $order->id,
                        'description' => 'Refund for rejected campaign '.$order->campaign_name,
                        'metadata' => ['campaign_order_id' => $order->id, 'campaign_total_minor' => $order->total_minor],
                        'available_at' => now(), 'posted_at' => now(),
                    ],
                );
                $order->refunded_at = now();
            } else {
                $description = ucfirst(match ($order->action) {
                    'followers', 'follower', 'subscribers' => 'Follow or subscribe to the campaign target.',
                    'likes', 'page_like' => 'Like the campaign page or target content.',
                    'post_share' => 'Share the target content publicly.',
                    'join' => 'Join the campaign target on Telegram.',
                    'channel' => 'Follow the WhatsApp channel.',
                    default => 'Complete the requested social action.',
                });
                if (filled($order->instructions)) {
                    $description .= "\n\nCampaign instructions: {$order->instructions}";
                }
                Ad::query()->firstOrCreate(
                    ['user_campaign_order_id' => $order->id],
                    [
                        'campaign_key' => 'user-campaign-'.Str::lower(Str::random(24)),
                        'title' => $order->campaign_name,
                        'type' => $order->platform,
                        'description' => $description,
                        'share_message' => $description,
                        'owner_id' => $order->user_id,
                        'destination_url' => $order->target_url,
                        // The service fee is retained by the platform; the
                        // action subtotal funds the task rewards.
                        'paid_amount' => CurrencyMinorUnits::fromMinor($order->subtotal_minor, $order->currency),
                        'currency' => $order->currency,
                        'total_share' => 0,
                        'reserved_share' => 0,
                        'share_reward' => CurrencyMinorUnits::fromMinor($order->unit_price_minor, $order->currency),
                        'maximum_share' => $order->quantity,
                        'status' => 'active',
                        'starts_at' => now(),
                        'featured' => false,
                    ],
                );
            }

            $order->status = $decision;
            $order->admin_note = $note;
            $order->reviewed_by_admin_id = $admin->id;
            $order->reviewed_at = now();
            $order->save();
            Transaction::query()->where('reference', 'campaign-order:'.$order->id)->update([
                'status' => $decision,
                'updated_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_admin_id' => $admin->id,
                'action' => 'campaign_order.'.$decision,
                'entity_type' => 'user_campaign_order',
                'entity_id' => (string) $order->id,
                'before_values' => ['status' => 'pending'],
                'after_values' => ['status' => $decision, 'note' => $note, 'refunded_at' => $order->refunded_at?->toISOString()],
                'created_at' => now(),
            ]);

            return $order->fresh(['user:id,full_name,username,email', 'reviewedBy:id,full_name,email', 'ad:id,user_campaign_order_id,status,title']);
        });
    }
}
