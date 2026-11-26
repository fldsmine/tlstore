<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Notifications\PasswordResetTokenNotification;

class User extends Authenticatable
{
    use HasApiTokens;
    use SoftDeletes;
    use Notifiable;

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordResetTokenNotification($token));
    }

    protected $table = 'users';

    protected $fillable = [
        'full_name', 'username', 'email', 'phone', 'email_verified_at', 'password', 'has_addon',
        'country_code', 'preferred_currency', 'wallet_currency', 'role', 'status', 'avatar', 'cover_image',
        'is_banned', 'ban_reason', 'subscription_questionnaire_answers', 'referral_code', 'is_member',
        'subscription_questionnaire_completed_groups', 'subscription_questionnaire_completed_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'security_pin', 'two_factor_secret',
        'two_factor_setup_started_at', 'two_factor_enabled_at',
        'two_factor_last_counter', 'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'subscription_questionnaire_answers' => 'array',
        'subscription_questionnaire_completed_groups' => 'array',
        'subscription_questionnaire_completed_at' => 'datetime',
        'two_factor' => 'boolean',
        'two_factor_secret' => 'encrypted',
        'two_factor_setup_started_at' => 'datetime',
        'two_factor_enabled_at' => 'datetime',
        'two_factor_last_counter' => 'integer',
        'two_factor_recovery_codes' => 'encrypted:array',
        'is_banned' => 'boolean',
        'role' => UserRole::class,
        'status' => UserStatus::class,
        'password' => 'hashed',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function socialIdentities(): HasMany
    {
        return $this->hasMany(SocialIdentity::class);
    }

    public function socialFollows(): HasMany
    {
        return $this->hasMany(UserSocialFollow::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    public function payoutProfile(): HasOne
    {
        return $this->hasOne(UserPayoutProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function ownedAds(): HasMany
    {
        return $this->hasMany(Ad::class, 'owner_id');
    }

    public function appReviewCampaigns(): HasMany
    {
        return $this->hasMany(AppReviewCampaign::class, 'owner_id');
    }

    public function campaignOrders(): HasMany
    {
        return $this->hasMany(UserCampaignOrder::class);
    }

    public function reviewTasks(): HasMany
    {
        return $this->hasMany(ReviewTask::class, 'reviewer_id');
    }

    public function reviewSubmissions(): HasMany
    {
        return $this->hasMany(ReviewSubmission::class, 'reviewer_id');
    }

    public function appReports(): HasMany
    {
        return $this->hasMany(AppReport::class, 'reporter_user_id');
    }

    public function adShareTasks(): HasMany
    {
        return $this->hasMany(AdShareTask::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function packageAddons(): HasMany
    {
        return $this->hasMany(UserPackageAddon::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function vtuOrders(): HasMany
    {
        return $this->hasMany(VtuOrder::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function referralCodes(): HasMany
    {
        return $this->hasMany(ReferralCode::class);
    }

    public function sentReferrals(): HasMany
    {
        return $this->hasMany(ReferralAttribution::class, 'referrer_user_id');
    }

    public function referralAttribution(): HasOne
    {
        return $this->hasOne(ReferralAttribution::class, 'referred_user_id');
    }

    public function manualDeposits(): HasMany
    {
        return $this->hasMany(ManualDepositSubmission::class);
    }

    public function reviewedManualDeposits(): HasMany
    {
        return $this->hasMany(ManualDepositSubmission::class, 'reviewed_by');
    }

    public function moderationDecisions(): HasMany
    {
        return $this->hasMany(ModerationDecision::class, 'decided_by');
    }

    public function processedWithdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'processed_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function announcementDismissals(): HasMany
    {
        return $this->hasMany(UserAnnouncementDismissal::class);
    }

    public function rankQualifications(): HasMany
    {
        return $this->hasMany(UserRankQualification::class);
    }
}
