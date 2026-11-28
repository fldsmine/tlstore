<?php

use App\Models\Ad;
use App\Models\Announcement;
use App\Models\App as StoreApp;
use App\Models\AppReviewCampaign;
use App\Models\AppReport;
use App\Models\AppVersion;
use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\Country;
use App\Models\ExchangeRateSnapshot;
use App\Models\Faq;
use App\Models\LegalDocument;
use App\Models\ManualDepositSubmission;
use App\Models\PackageAddon;
use App\Models\PaymentOption;
use App\Models\ReferralAttribution;
use App\Models\Region;
use App\Models\ReviewQuestion;
use App\Models\ReviewSubmission;
use App\Models\ReviewTemplate;
use App\Models\ReviewTemplateVersion;
use App\Models\ReviewTemplateVersionQuestion;
use App\Models\SocialHandle;
use App\Models\SubscriptionGroup;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\Testimonial;
use App\Models\UserCampaignOrder;
use App\Models\UserRankLevel;
use App\Models\WithdrawalOption;
use App\Models\WithdrawalRequest;
use App\Models\AdShareEvent;
use App\Models\Admin;
use App\Models\RegionCountry;
use App\Models\UserSocialFollow;
use App\Models\VtuOrder;

$field = static fn (string $name, string $label, string $type = 'text', bool $required = false, array $options = []): array => compact('name', 'label', 'type', 'required', 'options');
$status = ['draft' => 'Draft', 'pending' => 'Pending', 'pending_review' => 'Pending review', 'under_review' => 'Under review', 'active' => 'Active', 'paused' => 'Paused', 'exhausted' => 'Exhausted', 'ended' => 'Ended', 'rejected' => 'Rejected', 'archived' => 'Archived'];
$module = static fn (string $label, string $model, array $columns, array $fields = [], array $options = []): array => array_merge([
    'label' => $label,
    'model' => $model,
    'columns' => $columns,
    'fields' => $fields,
    'search' => array_values(array_intersect(['name', 'title', 'email', 'username', 'campaign_key'], $columns)),
    'deletable' => true,
    'can_create' => true,
], $options);

return [
    'modules' => [
        'admins' => $module('Admin accounts', Admin::class, ['id', 'full_name', 'email', 'role', 'status', 'last_login_at'], [
            $field('full_name', 'Full name', 'text', true), $field('email', 'Email', 'email', true),
            $field('password', 'Password (minimum 14 characters)', 'password'),
            $field('role', 'Role', 'select', true, ['super_admin' => 'Super administrator', 'admin' => 'Administrator', 'content_admin' => 'Content administrator', 'moderator' => 'Moderator', 'finance' => 'Finance']),
            $field('status', 'Status', 'select', true, ['active' => 'Active', 'inactive' => 'Inactive']),
        ], ['deletable' => false, 'search' => ['full_name', 'email']]),
        'apps' => $module('App catalog', StoreApp::class, ['id', 'name', 'store', 'developer', 'status', 'featured', 'maximum_review'], [
            $field('name', 'App name', 'text', true), $field('short_code', 'Short code', 'text', true),
            $field('store', 'Store', 'select', true, ['Google Play' => 'Google Play', 'Apple App Store' => 'Apple App Store', 'Samsung Galaxy Store' => 'Samsung Galaxy Store']),
            $field('brand_color', 'Brand color'), $field('developer', 'Developer', 'text', true), $field('category', 'Category', 'text', true),
            $field('install_count_label', 'Install count label'), $field('description', 'Description', 'textarea', true),
            $field('store_link', 'Store link', 'url'), $field('app_icon', 'Upload app icon', 'file'), $field('screenshots', 'Upload screenshots', 'images'), $field('status', 'Status', 'select', true, $status),
            $field('featured', 'Featured', 'boolean'), $field('paid_amount', 'Paid amount', 'decimal'), $field('location', 'Location'),
            $field('currency', 'Currency'), $field('rating', 'Store rating', 'decimal'), $field('total_reviews', 'Store review count', 'integer'),
            $field('review_reward', 'Campaign display reward', 'decimal'), $field('maximum_review', 'Maximum reviews', 'integer'),
        ], ['deletable' => false, 'search' => ['name', 'developer', 'store', 'category']]),
        'review-campaigns' => $module('Review campaigns', AppReviewCampaign::class, ['id', 'campaign_key', 'app_id', 'currency', 'maximum_review', 'completed_reviews', 'status'], [
            $field('campaign_key', 'Campaign key', 'text', true), $field('app_id', 'App ID', 'integer', true), $field('owner_id', 'Owner ID', 'integer'), $field('review_template_version_id', 'Published question version ID', 'integer'),
            $field('paid_amount_minor', 'Budget (minor units)', 'integer', true), $field('currency', 'Currency', 'text', true),
            $field('location', 'Location'), $field('review_reward_minor', 'Listing reward (minor units)', 'integer', true),
            $field('maximum_review', 'Maximum reviews', 'integer'), $field('status', 'Status', 'select', true, $status),
            $field('starts_at', 'Starts at', 'datetime'), $field('ends_at', 'Ends at', 'datetime'),
        ], ['deletable' => false, 'search' => ['campaign_key', 'currency', 'status']]),
        'ads' => $module('Ads listings', Ad::class, ['id', 'title', 'type', 'currency', 'share_reward', 'maximum_share', 'status', 'featured'], [
            $field('campaign_key', 'Campaign key', 'text', true), $field('title', 'Title', 'text', true),
            $field('type', 'Platform / type', 'text', true), $field('description', 'Instructions', 'textarea'),
            $field('share_message', 'Share message', 'textarea'), $field('owner_id', 'Owner ID', 'integer'),
            $field('creative_path', 'Upload creative', 'file'), $field('destination_url', 'Target URL', 'url'),
            $field('paid_amount', 'Reward pool', 'decimal', true), $field('location', 'Location'), $field('currency', 'Currency', 'text', true),
            $field('total_share', 'Approved shares', 'integer'), $field('share_reward', 'Reward per share', 'decimal', true),
            $field('maximum_share', 'Maximum shares', 'integer'), $field('status', 'Status', 'select', true, $status),
            $field('starts_at', 'Starts at', 'datetime'), $field('ends_at', 'Ends at', 'datetime'), $field('featured', 'Featured', 'boolean'),
        ], ['search' => ['title', 'campaign_key', 'type', 'destination_url']]),
        'subscription-groups' => $module('Subscription groups', SubscriptionGroup::class, ['id', 'group_key', 'name', 'is_active', 'sort_order'], [
            $field('group_key', 'Group key', 'text', true), $field('name', 'Name', 'text', true), $field('description', 'Description', 'textarea'),
            $field('is_active', 'Active', 'boolean'), $field('sort_order', 'Order', 'integer'),
        ]),
        'subscription-plans' => $module('Subscription plans', SubscriptionPlan::class, ['id', 'name', 'subscription_group_id', 'price_minor', 'currency', 'addons', 'is_active'], [
            $field('plan_key', 'Plan key', 'text', true), $field('subscription_group_id', 'Group ID', 'integer'), $field('product_type', 'Product type', 'select', true, ['marketplace_access' => 'Marketplace access']),
            $field('name', 'Name', 'text', true), $field('tier_key', 'Tier key'), $field('addons', 'Requires addon', 'boolean'),
            $field('billing_interval', 'Billing interval', 'select', true, ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly']), $field('duration_count', 'Duration count', 'integer', true),
            $field('price_minor', 'Price (minor units)', 'integer', true), $field('currency', 'Currency', 'text', true),
            $field('discount', 'Discount', 'decimal'), $field('bonus', 'Welcome bonus', 'decimal'), $field('is_special', 'Featured', 'boolean'),
            $field('max_review_tasks', 'Review limit', 'integer'), $field('max_ad_shares', 'Ad share limit', 'integer'),
            $field('eligible_app_store', 'Eligible store'), $field('eligible_app_category', 'Eligible category'),
            $field('minimum_withdrawal_minor', 'Minimum withdrawal (minor)', 'integer'), $field('withdrawal_days', 'Withdrawal days', 'json'),
            $field('daily_revenue_minor', 'Daily earnings limit (minor)', 'integer'), $field('task_reward_minor', 'Per review reward (minor)', 'integer'),
            $field('daily_task_limit', 'Daily task limit', 'integer'), $field('subscription_referral_percent', 'Direct legacy subscription percent', 'decimal'),
            $field('task_referral_percent', 'Direct legacy task percent', 'decimal'), $field('subscription_referral_tiers', 'Subscription tier rates', 'json'),
            $field('task_referral_tiers', 'Task tier rates', 'json'), $field('features', 'Features', 'json'), $field('is_active', 'Active', 'boolean'),
        ], ['deletable' => false, 'search' => ['name', 'plan_key', 'product_type']]),
        'package-addons' => $module('Package addons', PackageAddon::class, ['id', 'name', 'addon_key', 'price_minor', 'currency', 'discount', 'is_active'], [
            $field('subscription_plan_id', 'Subscription plan ID', 'integer', true), $field('addon_key', 'Addon key', 'text', true), $field('name', 'Name', 'text', true), $field('price_minor', 'Price (minor units)', 'integer', true),
            $field('currency', 'Currency', 'text', true), $field('billing_interval', 'Renewal interval', 'text', true),
            $field('discount', 'Discount percent', 'decimal'), $field('is_active', 'Active', 'boolean'),
        ], ['deletable' => false]),
        'review-questions' => $module('Review questions', ReviewQuestion::class, ['id', 'question_key', 'prompt', 'type', 'is_attention_check'], [
            $field('question_key', 'Question key', 'text', true), $field('step_name', 'Step name', 'text', true), $field('step_order', 'Step order', 'integer', true), $field('question_order', 'Question order', 'integer', true),
            $field('prompt', 'Question prompt', 'textarea', true), $field('hint', 'Helper text', 'textarea'), $field('type', 'Question type', 'select', true, ['single_choice' => 'Single choice', 'multiple_choice' => 'Multiple choice', 'rating' => 'Rating', 'long_text' => 'Long text', 'confirmation' => 'Confirmation']),
            $field('options', 'Options (JSON)', 'json'), $field('settings', 'Settings (JSON)', 'json'), $field('is_attention_check', 'Attention check', 'boolean'),
            $field('attention_check_answer', 'Required attention answer (JSON)', 'json'),
        ]),
        'review-templates' => $module('Question templates', ReviewTemplate::class, ['id', 'template_key', 'name', 'status', 'created_at'], [
            $field('template_key', 'Template key', 'text', true), $field('name', 'Name', 'text', true), $field('description', 'Description', 'textarea'), $field('status', 'Status', 'select', true, ['draft' => 'Draft', 'archived' => 'Archived']),
        ], ['deletable' => false]),
        'review-template-versions' => $module('Questionnaire versions', ReviewTemplateVersion::class, ['id', 'review_template_id', 'version', 'status', 'published_at'], [], ['deletable' => false]),
        'template-question-links' => $module('Question assignments', ReviewTemplateVersionQuestion::class, ['id', 'review_template_version_id', 'review_question_id', 'step_order', 'step_name', 'question_order'], [
            $field('review_template_version_id', 'Questionnaire version ID', 'integer', true), $field('review_question_id', 'Question ID', 'integer', true),
            $field('step_order', 'Step order', 'integer', true), $field('step_name', 'Step name', 'text', true), $field('question_order', 'Question order', 'integer', true),
        ]),
        'announcements' => $module('Announcements', Announcement::class, ['id', 'title', 'type', 'display_type', 'is_active', 'starts_at', 'ends_at'], [
            $field('title', 'Title', 'text', true), $field('body', 'Body', 'textarea', true), $field('action_url', 'Action URL', 'url'),
            $field('action_label', 'Action label'), $field('is_dismissible', 'Dismissible', 'boolean'), $field('type', 'Type', 'select', true, ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger']),
            $field('display_type', 'Display type', 'select', true, ['1' => 'Pop-up', '2' => 'Inline', '3' => 'Pinned']),
            $field('is_active', 'Active', 'boolean'), $field('starts_at', 'Starts at', 'datetime'), $field('ends_at', 'Ends at', 'datetime'),
        ]),
        'faqs' => $module('FAQs', Faq::class, ['id', 'question', 'category', 'is_active', 'order'], [
            $field('question', 'Question', 'textarea', true), $field('answer', 'Answer', 'textarea', true), $field('category', 'Category'),
            $field('is_active', 'Published', 'boolean'), $field('order', 'Order', 'integer'),
        ]),
        'testimonials' => $module('Testimonials', Testimonial::class, ['id', 'user_id', 'role', 'rating', 'status', 'is_featured'], [
            $field('user_id', 'User ID', 'integer'), $field('role', 'Role'), $field('content', 'Testimonial', 'textarea', true),
            $field('rating', 'Rating', 'integer'), $field('status', 'Status', 'select', true, ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
            $field('is_featured', 'Featured', 'boolean'), $field('admin_notes', 'Admin notes', 'textarea'),
        ]),
        'legal-documents' => $module('Legal documents', LegalDocument::class, ['id', 'slug', 'title', 'version', 'is_published'], [
            $field('slug', 'Document key', 'text', true), $field('title', 'Title', 'text', true), $field('eyebrow', 'Eyebrow'), $field('subtitle', 'Subtitle'),
            $field('hero_text', 'Hero text', 'textarea'), $field('sections', 'Sections (JSON)', 'json'), $field('version', 'Version'), $field('last_updated', 'Last updated', 'date'), $field('is_published', 'Published', 'boolean'),
        ], ['deletable' => false]),
        'rank-levels' => $module('Ranking levels', UserRankLevel::class, ['id', 'level', 'name', 'monthly_reward_usd', 'reward_duration_months', 'is_active'], [
            $field('level', 'Level number', 'integer', true), $field('name', 'Name', 'text', true),
            $field('minimum_subscribed_referrals', 'Minimum subscribed referrals', 'integer'),
            $field('minimum_referral_cash_inflow_usd', 'Minimum referred inflow USD', 'decimal'),
            $field('minimum_user_cash_inflow_usd', 'Minimum user inflow USD', 'decimal'),
            $field('monthly_reward_usd', 'Monthly reward (USD)', 'decimal'), $field('reward_duration_months', 'Reward months', 'integer'), $field('icon', 'Icon URL/path'), $field('is_active', 'Active', 'boolean'), $field('sort_order', 'Sort order', 'integer'),
        ], ['deletable' => false]),
        'countries' => $module('Countries', Country::class, ['code', 'name', 'default_currency', 'is_available'], [
            $field('code', 'ISO code', 'text', true), $field('name', 'Country name', 'text', true),
            $field('default_currency', 'Default currency'), $field('is_available', 'Available', 'boolean'),
        ], ['deletable' => false]),
        'regions' => $module('Regions', Region::class, ['id', 'code', 'name', 'is_available'], [
            $field('code', 'Region code', 'text', true), $field('name', 'Name', 'text', true), $field('is_available', 'Available', 'boolean'),
        ], ['deletable' => false]),
        'region-countries' => $module('Region coverage', RegionCountry::class, ['id', 'region_id', 'country_code', 'created_at'], [
            $field('region_id', 'Region ID', 'integer', true), $field('country_code', 'Country code', 'text', true),
        ]),
        'withdrawal-options' => $module('Withdrawal methods', WithdrawalOption::class, ['id', 'option_id', 'name', 'currency', 'tax', 'min_withdrawal', 'is_available', 'order'], [
            $field('option_id', 'Method key', 'text', true), $field('name', 'Name', 'text', true), $field('icon', 'Icon'),
            $field('description', 'Description', 'textarea'), $field('fields', 'Required fields', 'json', true), $field('currency', 'Base currency', 'text', true), $field('tax', 'Fee percent', 'decimal'),
            $field('min_withdrawal', 'Minimum withdrawal', 'decimal'), $field('is_available', 'Available', 'boolean'), $field('order', 'Order', 'integer'),
        ], ['deletable' => false]),
        'payment-options' => $module('Payment gateways', PaymentOption::class, ['id', 'name', 'currency', 'method_code', 'gateway_alias', 'status'], [
            $field('name', 'Name', 'text', true), $field('currency', 'Currency', 'text', true), $field('symbol', 'Symbol'), $field('method_code', 'Method code', 'text', true),
            $field('gateway_alias', 'Gateway alias', 'text', true), $field('percent_charge', 'Percent charge', 'decimal'), $field('fixed_charge', 'Fixed charge', 'decimal'), $field('rate', 'Exchange rate', 'decimal', true),
            $field('description', 'Description', 'textarea'), $field('addresses', 'Deposit addresses / instructions (JSON)', 'json', true), $field('logo', 'Logo URL'), $field('status', 'Active', 'boolean'), $field('order', 'Display order', 'integer'),
        ], ['deletable' => false]),
        'banks' => $module('Banks', Bank::class, ['id', 'country', 'name', 'code', 'type', 'is_active'], [
            $field('country', 'Country code', 'text', true), $field('name', 'Bank name', 'text', true), $field('code', 'Bank code', 'text', true), $field('paystack_code', 'Paystack code'),
            $field('flutterwave_code', 'Flutterwave code'), $field('institution_code', 'Institution code'), $field('tag', 'Tag'), $field('type', 'Type'), $field('is_active', 'Active', 'boolean'),
        ], ['deletable' => false]),
        'social-handles' => $module('Social platforms', SocialHandle::class, ['id', 'handle_key', 'name', 'reward_amount_minor', 'currency', 'is_active'], [
            $field('handle_key', 'Platform key', 'text', true), $field('name', 'Name', 'text', true), $field('channel_url', 'Channel URL', 'url'), $field('icon', 'Icon'),
            $field('reward_amount_minor', 'Reward amount (minor)', 'integer'), $field('currency', 'Currency'), $field('is_active', 'Active', 'boolean'), $field('sort_order', 'Order', 'integer'),
        ], ['deletable' => false]),
        'app-versions' => $module('App versions', AppVersion::class, ['id', 'platform', 'version_name', 'build_number', 'minimum_supported_build', 'force_update', 'is_active'], [
            $field('platform', 'Platform', 'text', true), $field('version_name', 'Version', 'text', true), $field('build_number', 'Build number', 'integer'),
            $field('minimum_supported_build', 'Minimum supported build', 'integer'), $field('title', 'Release title'), $field('release_notes', 'Release notes', 'textarea'),
            $field('direct_download_path', 'Download path'), $field('direct_download_url', 'Download URL', 'url'), $field('play_store_url', 'Play Store URL', 'url'), $field('app_store_url', 'App Store URL', 'url'),
            $field('force_update', 'Force update', 'boolean'), $field('is_active', 'Active', 'boolean'), $field('published_at', 'Published at', 'datetime'),
        ], ['deletable' => false]),
        'app-reports' => $module('App reports', AppReport::class, ['id', 'app.name', 'reporter.username', 'reason_code', 'details', 'status', 'created_at'], [], ['deletable' => false, 'with' => ['app:id,name', 'reporter:id,username'], 'search' => ['reason_code', 'details']]),
        'referrals' => $module('Referral attributions', ReferralAttribution::class, ['id', 'referrer.username', 'referredUser.username', 'status', 'attributed_at'], [], ['deletable' => false, 'with' => ['referrer:id,username', 'referredUser:id,username'], 'search' => ['referrer_user_id', 'referred_user_id']]),
        'review-moderation' => $module('App review moderation', ReviewSubmission::class, ['id', 'reviewer.username', 'task.campaign.app.name', 'task.reserved_reward_minor', 'answers_count', 'status', 'submitted_at'], [], ['deletable' => false, 'with' => ['reviewer:id,username', 'task.campaign:id,app_id', 'task.campaign.app:id,name', 'answers.question'], 'with_count' => ['answers'], 'search' => ['id']]),
        'share-moderation' => $module('Ad share moderation', AdShareEvent::class, ['id', 'task.user.username', 'task.ad.title', 'channel', 'evidence', 'status', 'shared_at'], [], ['deletable' => false, 'with' => ['task.user:id,username', 'task.ad:id,title'], 'search' => ['id', 'channel']]),
        'social-follow-activity' => $module('Social follow rewards', UserSocialFollow::class, ['id', 'user.username', 'socialHandle.name', 'status', 'reward_amount_minor', 'currency', 'followed_at'], [], ['deletable' => false, 'search' => ['user_id', 'social_handle_id'], 'with' => ['user:id,username', 'socialHandle:id,name']]),
        'deposit-moderation' => $module('Manual deposit approvals', ManualDepositSubmission::class, ['id', 'user.username', 'amount_minor', 'currency', 'bank_reference', 'status', 'submitted_at'], [], ['deletable' => false, 'with' => ['user:id,username'], 'search' => ['bank_reference']]),
        'withdrawal-moderation' => $module('Withdrawal approvals', WithdrawalRequest::class, ['id', 'user.username', 'amount_minor', 'fee_minor', 'currency', 'option.name', 'status', 'created_at'], [], ['deletable' => false, 'with' => ['user:id,username', 'option:id,name'], 'search' => ['id']]),
        'campaign-orders' => $module('User campaign approvals', UserCampaignOrder::class, ['id', 'user.username', 'campaign_name', 'platform', 'quantity', 'total_minor', 'currency', 'status', 'created_at'], [], ['deletable' => false, 'with' => ['user:id,username'], 'search' => ['campaign_name', 'platform']]),
        'audit-logs' => $module('Audit log', AuditLog::class, ['id', 'adminActor.full_name', 'action', 'entity_type', 'entity_id', 'created_at'], [], ['deletable' => false, 'with' => ['adminActor:id,full_name,email']]),
        'system-settings' => $module('System settings', SystemSetting::class, ['id', 'setting_key', 'value', 'is_public', 'updated_at'], [
            $field('setting_key', 'Setting key', 'text', true), $field('value', 'JSON value', 'json'), $field('is_public', 'Public', 'boolean'),
        ], ['deletable' => false, 'search' => ['setting_key']]),
        'vtu-orders' => $module('VAS orders', VtuOrder::class, ['id', 'user_id', 'service', 'service_id', 'provider', 'charged_amount_minor', 'currency', 'status', 'refunded', 'created_at'], [], ['deletable' => false, 'search' => ['service', 'service_id', 'provider', 'status']]),
        'exchange-rates' => $module('Exchange rate snapshots', ExchangeRateSnapshot::class, ['id', 'base_currency', 'quote_currency', 'rate', 'source', 'is_fallback', 'fetched_at'], [], ['deletable' => false, 'search' => ['base_currency', 'quote_currency', 'source']]),
    ],
];
