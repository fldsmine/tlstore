<?php

namespace App\Models;

use App\Enums\SubscriptionProductType;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    protected $table = 'user_subscriptions';
    protected $fillable = [
        'user_id', 'subscription_plan_id', 'payment_intent_id', 'product_type', 'status',
        'provider_subscription_reference', 'starts_at', 'ends_at', 'cancelled_at', 'plan_snapshot',
    ];
    protected $casts = [
        'product_type' => SubscriptionProductType::class,
        'status' => SubscriptionStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'plan_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

	protected static function booted(): void
	{
		static::creating(function (UserSubscription $subscription): void {

			$plan = SubscriptionPlan::find($subscription->subscription_plan_id);

			if ($plan) {
				$user = User::find($subscription->user_id);

				if (!$user) {
					return;
				}

				if ($user->is_member != 1) {
					$user->is_member = 1;
				}

				if ($plan->addons == 1 && !$user->has_addon) {
					$user->has_addon = 1;
					$user->is_member = 2;
				}

				$user->save();
			}
		});
	}
}
