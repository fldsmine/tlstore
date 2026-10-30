<?php

namespace App\Models;

use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Support\DisplayCurrency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasUuids, Notifiable;

    public const array SYSTEM_USERNAMES = ['system1', 'system2'];

    public const array WALLET_COLUMNS = [
        'shareholder' => 'shareholder_balance',
        'ads_revenue' => 'ads_revenue_balance',
        'referral_bonus' => 'referral_bonus_balance',
        'reward' => 'reward_balance',
        'agent' => 'agent_balance',
        'cloud_call' => 'cloud_call_balance',
        'cloud_sms' => 'cloud_sms_balance',
        'bills' => 'bills_balance',
    ];

    public const array SPENDABLE_WALLETS = ['shareholder', 'ads_revenue', 'referral_bonus', 'reward', 'cloud_call', 'cloud_sms'];

    public const string BILLS_WALLET = 'bills';

    public const array WALLET_LABELS = [
        'shareholder' => 'Nextel Shares',
        'ads_revenue' => 'Ads Revenue',
        'referral_bonus' => 'Connect Bonus',
        'reward' => 'Target Reward',
        'agent' => 'Vendor Float',
        'cloud_call' => 'N-Cloud Calls',
        'cloud_sms' => 'N-Cloud SMS',
        'bills' => 'VTU Balance',
    ];

    protected $hidden = ['password', 'remember_token', 'transaction_pin'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'transaction_pin' => 'hashed',
            'status' => UserStatus::class,
            'gender' => UserGender::class,
            'is_agent' => 'boolean',
            'is_special' => 'boolean',
            'withdrawals_disabled' => 'boolean',
            'show_on_agents_page' => 'boolean',
            'shareholder_balance' => 'decimal:6',
            'legacy_shareholder_balance' => 'decimal:6',
            'ads_revenue_balance' => 'decimal:6',
            'referral_bonus_balance' => 'decimal:6',
            'reward_balance' => 'decimal:6',
            'agent_balance' => 'decimal:6',
            'cloud_call_balance' => 'decimal:6',
            'cloud_sms_balance' => 'decimal:6',
            'bills_balance' => 'decimal:6',
            'lifetime_earnings' => 'decimal:6',
            'last_claim_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'website_expires_at' => 'datetime',
        ];
    }

    /** Whether the personalized website ("My Website") is still within its paid validity window. */
    public function websiteActive(): bool
    {
        return $this->website_expires_at !== null && $this->website_expires_at->isFuture();
    }

    /** Extend the personalized-website window: stack onto remaining time, else start from now. */
    public function extendWebsite(int $days): void
    {
        $base = $this->website_expires_at?->isFuture() ? $this->website_expires_at : now();

        $this->update(['website_expires_at' => $base->copy()->addDays($days)]);
    }

    public function getAvatarAttribute(?string $value): ?string
    {
        return media_url($value);
    }

    /**
     * Safe, linkable Telegram URL. Non-http values are coerced to https://t.me/<handle>, which
     * neutralises any javascript:/data: scheme a user might store via their profile.
     */
    public function getTelegramUrlAttribute(): ?string
    {
        $telegram = trim((string) $this->telegram);

        if ($telegram === '') {
            return null;
        }

        return str_starts_with($telegram, 'http') ? $telegram : 'https://t.me/'.ltrim($telegram, '@');
    }

    public function getCloudLineFormattedAttribute(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->cloud_line_number);
        if ($digits === null || strlen($digits) !== 11) {
            return $this->cloud_line_number;
        }

        return sprintf('+%s (%s) %s-%s', $digits[0], substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7, 4));
    }

    public function getCloudLineMaskedAttribute(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->cloud_line_number);
        if ($digits === null || strlen($digits) !== 11) {
            return $this->cloud_line_number ? '+1 (698) •••-••••' : null;
        }

        return sprintf('+%s (%s) •••-••••', $digits[0], substr($digits, 1, 3));
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referrer_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referrer_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function countryInfo(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country', 'code');
    }

    public function isNigerian(): bool
    {
        return strtoupper((string) $this->country) === 'NG';
    }

    public function scopeExcludingSystem(Builder $query): Builder
    {
        return $query->whereNotIn('username', self::SYSTEM_USERNAMES);
    }

    public function isSpecialVendor(): bool
    {
        return $this->is_agent && (bool) $this->is_special;
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function shareSubscriptions(): HasMany
    {
        return $this->hasMany(ShareSubscription::class);
    }

    public function cloudStorageTopUps(): HasMany
    {
        return $this->hasMany(CloudStorageTopUp::class);
    }

    public function cloudNumbers(): BelongsToMany
    {
        return $this->belongsToMany(CloudNumber::class, 'cloud_user_number')->withTimestamps();
    }

    public function cloudCalls(): HasMany
    {
        return $this->hasMany(CloudCall::class);
    }

    public function isOnline(): bool
    {
        $window = (int) config('settings.cloud.online_window_seconds', 60);

        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds($window));
    }

    /** An active cloud storage pack (e.g. granted by a special coupon) unlocks the Cloud
     *  features the package doesn't include — calling, the inbox and contact slots. */
    public function hasActiveCloudStorage(): bool
    {
        return $this->cloudStorageTopUps()->active()->exists();
    }

    public function canAccessCloud(): bool
    {
        return (bool) $this->package?->enable_cloud || $this->hasActiveCloudStorage();
    }

    public function canCloudCall(): bool
    {
        return $this->canAccessCloud() && ((bool) $this->package?->cloud_calls || $this->hasActiveCloudStorage());
    }

    public function canCloudSms(): bool
    {
        return $this->canAccessCloud() && ((bool) $this->package?->cloud_sms || $this->hasActiveCloudStorage());
    }

    public function cloudContactLimit(): int
    {
        if (! $this->canCloudCall()) {
            return 0;
        }

        return (int) $this->package?->cloud_contact_limit
            + (int) $this->cloudStorageTopUps()->active()->sum('extra_contacts');
    }

    /**
     * Daily sponsored-SMS claim limit: the package's base quota plus extra SMS granted by
     * active storage packs (extra_sms). 0 = no daily cap (matches the earning-cap
     * convention) — the seeded packages set a real base, and packs add on top of it.
     */
    public function cloudSmsLimit(): int
    {
        if (! $this->canCloudSms()) {
            return 0;
        }

        $base = (int) ($this->package?->cloud_sms_limit ?? 0);

        if ($base <= 0) {
            return 0;
        }

        return $base + (int) $this->cloudStorageTopUps()->active()->sum('extra_sms');
    }

    public function allocatedNumbers(): HasMany
    {
        return $this->hasMany(AllocatedNumber::class);
    }

    public function callClaims(): HasMany
    {
        return $this->hasMany(CallClaim::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function vtuOrders(): HasMany
    {
        return $this->hasMany(VtuOrder::class);
    }

    public function cloudInbox(): HasMany
    {
        return $this->hasMany(CloudInboxMessage::class)->latest();
    }

    /** Every recorded sign-in (website / mobile app / Telegram Mini App). */
    public function loginEvents(): HasMany
    {
        return $this->hasMany(UserLoginEvent::class);
    }

    public function minuteTopUps(): HasMany
    {
        return $this->hasMany(MinuteTopUp::class);
    }

    public function minuteCredits(): HasMany
    {
        return $this->hasMany(MinuteCredit::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function rewardTargets(): HasMany
    {
        return $this->hasMany(RewardTarget::class);
    }

    public function adShares(): HasMany
    {
        return $this->hasMany(AdShare::class);
    }

    public function activeMinuteBonus(): float
    {
        return (float) $this->minuteTopUps()->active()->sum('daily_call_minutes');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /** The user's bank account — where their withdrawals are paid. */
    public function bankAccount(): HasOne
    {
        return $this->hasOne(BankAccount::class);
    }

    /** Verified Telegram account linked for Mini App sign-in. */
    public function telegramIdentity(): HasOne
    {
        return $this->hasOne(TelegramIdentity::class);
    }

    public function couponBatches(): HasMany
    {
        return $this->hasMany(CouponBatch::class, 'agent_id');
    }

    public function issuedCoupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'agent_id');
    }

    public function debitCard(): HasOne
    {
        return $this->hasOne(DebitCard::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: (string) ($this->username ?? '');
    }

    public function getTotalBalanceAttribute(): float
    {
        return round((float) $this->shareholder_balance + (float) $this->ads_revenue_balance + (float) $this->referral_bonus_balance + (float) $this->reward_balance + (float) $this->cloud_call_balance + (float) $this->cloud_sms_balance, 6);
    }

    /**
     * Wallet rows for the balance switcher. The agent float and VTU balance are
     * intentionally excluded — they are neither lifetime earnings nor spendable balance.
     *
     * @param  bool  $withLifetime  prepend the Total Balance (lifetime earnings) row
     */
    public function walletBalances(bool $withLifetime = true): Collection
    {
        $todayByWallet = $this->transactions()
            ->where('direction', 'credit')
            ->whereDate('created_at', today())
            ->selectRaw('wallet, SUM(amount) as t')
            ->groupBy('wallet')
            ->pluck('t', 'wallet');

        // Current Balance inflow today = every credit except the agent float and bills wallet.
        $spendableToday = collect(self::SPENDABLE_WALLETS)->sum(fn (string $w): float => (float) ($todayByWallet[$w] ?? 0));

        $row = function (string $label, float $usd, string $wallet, bool $locked = false) use ($todayByWallet, $spendableToday): array {
            $converted = DisplayCurrency::convert($usd, $this);
            $symbol = DisplayCurrency::symbol($this);

            $todayUsd = match ($wallet) {
                'lifetime', 'legacy_shareholder' => null,
                'total' => $spendableToday,
                default => (float) ($todayByWallet[$wallet] ?? 0),
            };

            return [
                'label' => $label,
                'amount' => abs($converted) >= 100000 ? $symbol.abbreviate_number($converted) : DisplayCurrency::format($usd, 'auto', $this),
                'exact' => DisplayCurrency::format($usd, 'auto', $this),
                'today' => $todayUsd === null ? null : DisplayCurrency::format($todayUsd, 'auto', $this),
                'wallet' => $wallet,
                'locked' => $locked,
            ];
        };

        $balances = collect([
            $row('Current Balance', (float) $this->total_balance, 'total'),
            $row('Nextel Shares', (float) $this->shareholder_balance, 'shareholder'),
            $row('Ads Revenue', (float) $this->ads_revenue_balance, 'ads_revenue'),
            $row('Connect Bonus', (float) $this->referral_bonus_balance, 'referral_bonus'),
            $row('Target Reward', (float) $this->reward_balance, 'reward'),
        ]);

        if ((float) $this->legacy_shareholder_balance > 0) {
            $balances->push($row('Legacy Nextel Shares (locked)', (float) $this->legacy_shareholder_balance, 'legacy_shareholder', locked: true));
        }

        if ($this->canCloudCall() || (float) $this->cloud_call_balance > 0) {
            $balances->push($row('N-Cloud Calls', (float) $this->cloud_call_balance, 'cloud_call'));
        }
        if ($this->canCloudSms() || (float) $this->cloud_sms_balance > 0) {
            $balances->push($row('N-Cloud SMS', (float) $this->cloud_sms_balance, 'cloud_sms'));
        }

        if ($withLifetime) {
            $balances->prepend($row('Total Balance', (float) $this->lifetime_earnings, 'lifetime'));
        }

        return $balances;
    }

    public function walletBalance(string $wallet): float
    {
        $column = self::WALLET_COLUMNS[$wallet] ?? null;

        return $column ? (float) $this->{$column} : 0.0;
    }

    public function creditEarnings(string $wallet, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->increment($wallet, $amount);

        if (! in_array($wallet, ['agent_balance', 'bills_balance'], true)) {
            $this->increment('lifetime_earnings', $amount);
        }
    }
}
