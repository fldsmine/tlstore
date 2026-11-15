<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table): void {
            $table->foreignId('user_campaign_order_id')->nullable()->unique()
                ->after('owner_id')->constrained('user_campaign_orders')->cascadeOnDelete();
        });

        // Normalize the first draft's nested per-action price representation
        // into platform rates plus a single platform minimum.
        $settings = DB::table('system_settings')->where('setting_key', 'campaign.services')->first();
        if ($settings) {
            $services = is_array($settings->value) ? $settings->value : json_decode((string) $settings->value, true);
            $changed = false;
            if (is_array($services)) {
                foreach ($services as $platform => $definition) {
                    if (! is_array($definition)) continue;
                    $minimum = $definition['min'] ?? null;
                    $normalized = [];
                    foreach ($definition as $action => $rate) {
                        if ($action === 'min') continue;
                        if (is_array($rate) && array_key_exists('amount', $rate)) {
                            $normalized[$action] = $rate['amount'];
                            $minimum ??= $rate['min'] ?? null;
                            $changed = true;
                        } else {
                            $normalized[$action] = $rate;
                        }
                    }
                    if ($minimum !== null) $normalized['min'] = (int) $minimum;
                    $services[$platform] = $normalized;
                }
            }
            if ($changed) {
                DB::table('system_settings')->where('id', $settings->id)->update([
                    'value' => json_encode($services, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }
        }

        // Publish any campaign orders that admins approved before this link
        // existed, so those already-paid requests are not stranded.
        foreach (DB::table('user_campaign_orders')->where('status', 'approved')->orderBy('id')->get() as $order) {
            if (DB::table('ads')->where('user_campaign_order_id', $order->id)->exists()) {
                continue;
            }
            $currency = strtoupper($order->currency ?: 'NGN');
            $precision = (int) config('trustlens.currency_minor_units.'.$currency, 2);
            $factor = 10 ** $precision;
            $description = match ($order->action) {
                'followers', 'follower', 'subscribers' => 'Follow or subscribe to the campaign target.',
                'likes', 'page_like' => 'Like the campaign page or target content.',
                'post_share' => 'Share the target content publicly.',
                'join' => 'Join the campaign target on Telegram.',
                'channel' => 'Follow the WhatsApp channel.',
                default => 'Complete the requested social action.',
            };
            if (filled($order->instructions)) {
                $description .= "\n\nCampaign instructions: {$order->instructions}";
            }
            DB::table('ads')->insert([
                'campaign_key' => 'user-campaign-'.Str::lower(Str::random(24)),
                'user_campaign_order_id' => $order->id,
                'title' => $order->campaign_name,
                'type' => $order->platform,
                'description' => $description,
                'share_message' => $description,
                'owner_id' => $order->user_id,
                'destination_url' => $order->target_url,
                'paid_amount' => number_format($order->subtotal_minor / $factor, 2, '.', ''),
                'currency' => $currency,
                'share_reward' => number_format($order->unit_price_minor / $factor, 2, '.', ''),
                'maximum_share' => $order->quantity,
                'status' => 'active',
                'starts_at' => $order->reviewed_at ?? $order->created_at,
                'featured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ads', 'user_campaign_order_id')) {
            DB::table('ads')->whereNotNull('user_campaign_order_id')->delete();
        }
        Schema::table('ads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_campaign_order_id');
        });
    }
};
