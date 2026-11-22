<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Admin;
use App\Application\Money\CurrencyMinorUnits;

class DemoPlatformActivitySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $users = $this->usersByNumber();
            $adminId = (int) Admin::query()->where('email', 'admin@tlstore.test')->value('id');
            $plans = DB::table('subscription_plans')->pluck('id', 'plan_key')->all();
            $apps = DB::table('apps')->pluck('id', 'prototype_id')->all();
            $ownerId = $users[2];

            $this->seedAppStoreSnapshots($apps, $now);
            $templateVersionIds = $this->publishedTemplateVersionIds();
            $campaigns = $this->seedReviewCampaigns($apps, $ownerId, $now, $templateVersionIds);
            $paymentIntents = $this->seedSubscriptionPayments($users, $plans, $now);
            $subscriptions = $this->seedSubscriptions($users, $plans, $paymentIntents, $now);
            $this->seedLocationAddons($users, $plans, $now);
            $wallets = $this->walletIdsByUser($users);
            $reviewTasks = $this->seedReviewTasks($campaigns, $users, $now);
            $this->seedReviewSubmissions($reviewTasks, $users, $adminId, $wallets, $now);
            $this->seedAdShareActivity($users, $wallets, $now);
            $this->seedTopupsAndManualDeposits($users, $wallets, $now);
            $this->seedReferralActivity($users, $wallets, $now);
            $this->seedWithdrawals($users, $wallets, $now);
            $this->seedNotificationsAndOperations($users, $now);
        });
    }

    private function usersByNumber(): array
    {
        $result = [];
        $rows = DB::table('users')
            ->where('email', 'like', 'demo.user%@tlstore.test')
            ->get(['id', 'email']);

        foreach ($rows as $user) {
            $number = (int) substr($user->email, 9, 2);
            $result[$number] = (int) $user->id;
        }

        return $result;
    }

    private function seedAppStoreSnapshots(array $apps, $now): void
    {
        $ratings = [0 => ['rating' => '4.60', 'total_reviews' => 18200], 1 => ['rating' => '3.10', 'total_reviews' => 840], 2 => ['rating' => '4.30', 'total_reviews' => 6400]];
        $rows = [];
        foreach ($ratings as $prototypeId => $stats) {
            if (!isset($apps[$prototypeId])) {
                continue;
            }
            $rows[] = [
                'app_id' => $apps[$prototypeId],
                'store' => ['Google Play', 'Samsung Galaxy Store', 'Apple App Store'][$prototypeId],
                'external_listing_id' => 'listing-'.$prototypeId,
                'source_url' => 'https://example.com/store-listing/'.$prototypeId,
                'rating' => $stats['rating'],
                'total_reviews' => $stats['total_reviews'],
                'metadata' => json_encode(['source' => 'demo_seed_not_live'], JSON_THROW_ON_ERROR),
                'captured_at' => $now->copy()->startOfDay()->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('app_store_listing_snapshots')->upsert(
            $rows,
            ['app_id', 'store', 'captured_at'],
            ['external_listing_id', 'source_url', 'rating', 'total_reviews', 'metadata', 'updated_at']
        );
    }

    /** @return array<string, int> template key => latest published version ID */
    private function publishedTemplateVersionIds(): array
    {
        $versions = DB::table('review_templates as templates')
            ->join('review_template_versions as versions', 'versions.review_template_id', '=', 'templates.id')
            ->where('templates.status', 'published')
            ->where('versions.status', 'published')
            ->whereNotNull('versions.published_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('review_template_version_questions as version_questions')
                    ->whereColumn('version_questions.review_template_version_id', 'versions.id');
            })
            ->orderBy('templates.template_key')
            ->orderByDesc('versions.version')
            ->get(['templates.template_key', 'versions.id']);

        $result = [];
        foreach ($versions as $version) {
            $result[$version->template_key] ??= (int) $version->id;
        }

        return $result;
    }

    private function seedReviewCampaigns(array $apps, int $ownerId, $now, array $templateVersionIds): array
    {
        $appRows = DB::table('apps')->whereNotNull('prototype_id')->get()->keyBy('prototype_id');
        $listingModel = json_decode(
            file_get_contents(database_path('data/app-listings.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $templateKeysByPrototype = collect($listingModel['apps'])
            ->mapWithKeys(static fn (array $listing): array => [
                (int) $listing['prototype_id'] => $listing['review_template_key'] ?? null,
            ])
            ->all();
        $campaignKeys = [
            0 => 'review-fitpulse-ng',
            1 => 'review-cashbloom-global',
            2 => 'review-nairawise-global',
        ];
        $rows = [];
        foreach ($appRows as $prototypeId => $app) {
            if (! isset($apps[$prototypeId])) {
                continue;
            }
            $templateKey = $templateKeysByPrototype[$prototypeId] ?? null;
            $templateVersionId = $templateKey === null ? null : ($templateVersionIds[$templateKey] ?? null);
            if ($templateVersionId === null) {
                throw new \RuntimeException('App listing "'.$app->name.'" must reference a published review-question template with questions.');
            }
            $currency = strtoupper($app->currency ?: 'USD');
            $maximum = $app->maximum_review === null ? null : (int) $app->maximum_review;
            $paidAmountMinor = CurrencyMinorUnits::toMinor($app->paid_amount, $currency);
            $rows[] = [
                'campaign_key' => $campaignKeys[$prototypeId] ?? 'review-app-'.$prototypeId,
                'app_id' => $apps[$prototypeId],
                'review_template_version_id' => $templateVersionId,
                'owner_id' => $ownerId,
                'paid_amount_minor' => $paidAmountMinor,
                'currency' => $currency,
                // App locations are descriptive metadata; review eligibility is
                // controlled by subscription store/category, not country.
                'location' => $app->location,
                'review_reward_minor' => CurrencyMinorUnits::toMinor($app->review_reward, $currency),
                'maximum_review' => $maximum,
                'reserved_reviews' => 0,
                'completed_reviews' => 0,
                'status' => 'active',
                'starts_at' => $now->copy()->subDays(3),
                'ends_at' => $now->copy()->addDays(60),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('app_review_campaigns')->upsert(
            $rows,
            ['campaign_key'],
            ['app_id', 'review_template_version_id', 'owner_id', 'paid_amount_minor', 'currency', 'location', 'review_reward_minor',
                'maximum_review', 'status', 'starts_at', 'ends_at', 'updated_at']
        );

        // Keep historical task records linked to their campaign, but stop
        // exposing the duplicate regional copy of NairaWise as a second listing.
        DB::table('app_review_campaigns')
            ->where('campaign_key', 'review-nairawise-gb')
            ->update(['status' => 'draft', 'location' => null, 'updated_at' => $now]);

        return DB::table('app_review_campaigns')->pluck('id', 'campaign_key')->all();
    }

    private function seedSubscriptionPayments(array $users, array $plans, $now): array
    {
        $records = [];
        foreach ($users as $number => $userId) {
            if ($number === 2 || $number === 15) {
                continue;
            }
            $planKey = $this->planKeyForUser($number);
            $plan = DB::table('subscription_plans')->where('id', $plans[$planKey])->first();
            $key = 'subscription-user-'.$number;
            $records[$number] = $key;
            DB::table('payment_intents')->upsert([[
                'user_id' => $userId,
                'purpose' => 'subscription',
                'source_type' => 'subscription_plan',
                'source_id' => $plan->id,
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'provider' => 'demo',
                'provider_reference' => 'PAY-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'idempotency_key' => $key,
                'status' => 'paid',
                'provider_metadata' => json_encode(['seed' => true, 'not_a_real_payment' => true], JSON_THROW_ON_ERROR),
                'expires_at' => null,
                'paid_at' => $now->copy()->subDays(2),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now,
            ]], ['idempotency_key'], ['amount_minor', 'currency', 'provider', 'status', 'provider_metadata', 'paid_at', 'updated_at']);
        }

        foreach ($records as $number => $key) {
            $intent = DB::table('payment_intents')->where('idempotency_key', $key)->first();
            DB::table('payment_transactions')->upsert([[
                'payment_intent_id' => $intent->id,
                'provider_transaction_id' => 'TXN-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'transaction_type' => 'capture',
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency,
                'status' => 'succeeded',
                'provider_response' => json_encode(['seed' => true, 'provider' => 'demo'], JSON_THROW_ON_ERROR),
                'occurred_at' => $intent->paid_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['provider_transaction_id'], ['payment_intent_id', 'amount_minor', 'currency', 'status', 'provider_response', 'updated_at']);

            DB::table('payment_webhook_events')->upsert([[
                'provider' => 'demo',
                'provider_event_id' => 'EVENT-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'event_type' => 'payment.succeeded',
                'payload' => json_encode(['seed' => true, 'payment_intent_id' => $intent->id], JSON_THROW_ON_ERROR),
                'signature_valid' => true,
                'processing_status' => 'processed',
                'processing_error' => null,
                'received_at' => $intent->paid_at,
                'processed_at' => $intent->paid_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['provider', 'provider_event_id'], ['event_type', 'payload', 'processing_status', 'processed_at', 'updated_at']);
        }

        return $records;
    }

    private function seedSubscriptions(array $users, array $plans, array $paymentKeys, $now): array
    {
        $result = [];
        foreach ($paymentKeys as $number => $paymentKey) {
            $planKey = $this->planKeyForUser($number);
            $planId = $plans[$planKey];
            $plan = DB::table('subscription_plans')->where('id', $planId)->first();
            $paymentId = DB::table('payment_intents')->where('idempotency_key', $paymentKey)->value('id');
            $startsAt = $now->copy()->subDays(($number % 7) + 1);
            $endsAt = $now->copy()->addDays(30 - ($number % 5));
            $status = $number === 14 ? 'expired' : 'active';
            $providerRef = 'SUB-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            DB::table('user_subscriptions')->upsert([[
                'user_id' => $users[$number],
                'subscription_plan_id' => $planId,
                'payment_intent_id' => $paymentId,
                'product_type' => 'marketplace_access',
                'status' => $status,
                'provider_subscription_reference' => $providerRef,
                'starts_at' => $startsAt,
                'ends_at' => $status === 'expired' ? $now->copy()->subDay() : $endsAt,
                'cancelled_at' => null,
                'plan_snapshot' => json_encode($plan, JSON_THROW_ON_ERROR),
                'created_at' => $startsAt,
                'updated_at' => $now,
            ]], ['provider_subscription_reference'], ['subscription_plan_id', 'payment_intent_id', 'status', 'starts_at', 'ends_at', 'plan_snapshot', 'updated_at']);

            $result[$number] = (int) DB::table('user_subscriptions')
                ->where('provider_subscription_reference', $providerRef)
                ->value('id');
        }

        return $result;
    }

    private function seedLocationAddons(array $users, array $plans, $now): void
    {
        $fixtures = [
            [3, 'location-starter-weekly', 2],
            [4, 'location-basic-monthly', 10],
            [5, 'location-premium-quarterly', 20],
        ];

        foreach ($fixtures as [$userNumber, $planKey, $elapsedDays]) {
            if (!isset($users[$userNumber], $plans[$planKey])) {
                continue;
            }

            $planId = (int) $plans[$planKey];
            $plan = DB::table('subscription_plans')->where('id', $planId)->first();
            $startsAt = $now->copy()->subDays($elapsedDays);
            $endsAt = match ($plan->billing_interval) {
                'weekly' => $startsAt->copy()->addWeek(),
                'monthly' => $startsAt->copy()->addMonth(),
                'quarterly' => $startsAt->copy()->addMonths(3),
                default => $startsAt->copy()->addMonth(),
            };
            $numberKey = str_pad((string) $userNumber, 2, '0', STR_PAD_LEFT);
            $paymentKey = 'location-payment-u'.$numberKey;

            DB::table('payment_intents')->upsert([[
                'user_id' => $users[$userNumber],
                'purpose' => 'location_addon',
                'source_type' => 'subscription_plan',
                'source_id' => $planId,
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'provider' => 'demo',
                'provider_reference' => 'LOC-PAY-'.$numberKey,
                'idempotency_key' => $paymentKey,
                'status' => 'paid',
                'provider_metadata' => json_encode(['seed' => true, 'not_a_real_payment' => true], JSON_THROW_ON_ERROR),
                'expires_at' => null,
                'paid_at' => $startsAt,
                'created_at' => $startsAt,
                'updated_at' => $now,
            ]], ['idempotency_key'], ['amount_minor', 'currency', 'status', 'provider_metadata', 'paid_at', 'updated_at']);

            $paymentId = (int) DB::table('payment_intents')->where('idempotency_key', $paymentKey)->value('id');
            $transactionKey = 'LOC-TXN-'.$numberKey;
            DB::table('payment_transactions')->upsert([[
                'payment_intent_id' => $paymentId,
                'provider_transaction_id' => $transactionKey,
                'transaction_type' => 'capture',
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'status' => 'succeeded',
                'provider_response' => json_encode(['seed' => true, 'not_a_real_payment' => true], JSON_THROW_ON_ERROR),
                'occurred_at' => $startsAt,
                'created_at' => $startsAt,
                'updated_at' => $now,
            ]], ['provider_transaction_id'], ['payment_intent_id', 'amount_minor', 'currency', 'status', 'updated_at']);

            DB::table('payment_webhook_events')->upsert([[
                'provider' => 'demo',
                'provider_event_id' => 'LOC-EVENT-'.$numberKey,
                'event_type' => 'location_addon.payment_succeeded',
                'payload' => json_encode(['seed' => true, 'payment_intent_id' => $paymentId], JSON_THROW_ON_ERROR),
                'signature_valid' => true,
                'processing_status' => 'processed',
                'processing_error' => null,
                'received_at' => $startsAt,
                'processed_at' => $startsAt,
                'created_at' => $startsAt,
                'updated_at' => $now,
            ]], ['provider', 'provider_event_id'], ['event_type', 'payload', 'processing_status', 'processed_at', 'updated_at']);

            $providerSubscriptionReference = 'LOC-SUB-'.$numberKey;

            $planSnapshot = [
                'plan_key' => $planKey,
                'name' => $plan->name,
                'product_type' => $plan->product_type,
                'billing_interval' => $plan->billing_interval,
                'price_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'demo' => true,
            ];

            DB::table('user_subscriptions')->upsert([[
                'user_id' => $users[$userNumber],
                'subscription_plan_id' => $planId,
                'payment_intent_id' => $paymentId,
                'product_type' => 'demo_subscription',
                'status' => 'active',
                'provider_subscription_reference' => $providerSubscriptionReference,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'cancelled_at' => null,
                'plan_snapshot' => json_encode($planSnapshot, JSON_THROW_ON_ERROR),
                'created_at' => $startsAt,
                'updated_at' => $now,
            ]], ['provider_subscription_reference'], ['subscription_plan_id', 'payment_intent_id', 'status', 'starts_at', 'ends_at', 'plan_snapshot', 'updated_at']);
        }
    }

    private function walletIdsByUser(array $users): array
    {
        $ids = [];
        foreach ($users as $number => $userId) {
            $walletRows = DB::table('wallets')->where('user_id', $userId)->where('currency', 'USD')->pluck('id', 'wallet_type');
            $ids[$number] = $walletRows->all();
        }
        return $ids;
    }

    private function seedReviewTasks(array $campaigns, array $users, $now): array
    {
        $assignments = [
            ['campaign' => 'review-fitpulse-ng', 'user' => 1, 'status' => 'completed', 'result' => 'approved'],
            ['campaign' => 'review-cashbloom-global', 'user' => 3, 'status' => 'completed', 'result' => 'pending_review'],
            ['campaign' => 'review-cashbloom-global', 'user' => 4, 'status' => 'completed', 'result' => 'rejected'],
            ['campaign' => 'review-nairawise-gb', 'user' => 5, 'status' => 'completed', 'result' => 'approved'],
            ['campaign' => 'review-nairawise-global', 'user' => 6, 'status' => 'completed', 'result' => 'pending_review'],
            ['campaign' => 'review-nairawise-global', 'user' => 7, 'status' => 'reserved', 'result' => null],
        ];
        $campaignTemplateVersions = DB::table('app_review_campaigns')
            ->whereIn('id', array_values($campaigns))
            ->pluck('review_template_version_id', 'id')
            ->all();
        $rows = [];
        foreach ($assignments as $assignment) {
            if (!isset($campaigns[$assignment['campaign']], $users[$assignment['user']])) {
                continue;
            }
            $key = 'review-task-'.$assignment['campaign'].'-u'.$assignment['user'];
            $rows[] = [
                'app_review_campaign_id' => $campaigns[$assignment['campaign']],
                'reviewer_id' => $users[$assignment['user']],
                'review_template_version_id' => $campaignTemplateVersions[$campaigns[$assignment['campaign']]],
                'status' => $assignment['status'],
                'idempotency_key' => $key,
                'reserved_reward_minor' => (int) DB::table('app_review_campaigns')
                    ->where('id', $campaigns[$assignment['campaign']])
                    ->value('review_reward_minor'),
                'claimed_at' => $now->copy()->subDays(2),
                'expires_at' => $assignment['status'] === 'reserved' ? $now->copy()->addDay() : $now->copy()->subDay(),
                'completed_at' => $assignment['status'] === 'completed' ? $now->copy()->subDay() : null,
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now,
            ];
        }
        DB::table('review_tasks')->upsert(
            $rows,
            ['idempotency_key'],
            ['status', 'expires_at', 'completed_at', 'updated_at']
        );

        foreach ($campaigns as $campaignId) {
            DB::table('app_review_campaigns')->where('id', $campaignId)->update([
                'completed_reviews' => DB::table('review_tasks')
                    ->where('app_review_campaign_id', $campaignId)
                    ->where('status', 'completed')
                    ->count(),
                'reserved_reviews' => DB::table('review_tasks')
                    ->where('app_review_campaign_id', $campaignId)
                    ->where('status', 'reserved')
                    ->count(),
            ]);
        }

        $tasks = [];
        foreach ($assignments as $assignment) {
            if ($assignment['status'] !== 'completed' || !isset($campaigns[$assignment['campaign']], $users[$assignment['user']])) {
                continue;
            }
            $key = 'review-task-'.$assignment['campaign'].'-u'.$assignment['user'];
            $tasks[] = [
                'id' => (int) DB::table('review_tasks')->where('idempotency_key', $key)->value('id'),
                'user_number' => $assignment['user'],
                'result' => $assignment['result'],
                'template_version_id' => (int) $campaignTemplateVersions[$campaigns[$assignment['campaign']]],
                'reward_minor' => (int) DB::table('app_review_campaigns')
                    ->where('id', $campaigns[$assignment['campaign']])
                    ->value('review_reward_minor'),
            ];
        }
        return $tasks;
    }

    private function seedReviewSubmissions(array $tasks, array $users, int $adminId, array $wallets, $now): void
    {
        $answerRows = [];
        foreach ($tasks as $task) {
            $taskStatus = $task['result'];
            $templateVersionId = (int) $task['template_version_id'];
            DB::table('review_submissions')->upsert([[
                'review_task_id' => $task['id'],
                'reviewer_id' => $users[$task['user_number']],
                'review_template_version_id' => $templateVersionId,
                'status' => $taskStatus,
                'duration_seconds' => 420,
                'submitted_at' => $now->copy()->subDay(),
                'decided_at' => in_array($taskStatus, ['approved', 'rejected'], true) ? $now->copy()->subHours(8) : null,
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now,
            ]], ['review_task_id'], ['status', 'decided_at', 'updated_at']);

            $submissionId = (int) DB::table('review_submissions')->where('review_task_id', $task['id'])->value('id');
            DB::table('review_answers')->where('review_submission_id', $submissionId)->delete();
            $questions = DB::table('review_template_version_questions as version_questions')
                ->join('review_questions as questions', 'questions.id', '=', 'version_questions.review_question_id')
                ->where('version_questions.review_template_version_id', $templateVersionId)
                ->orderBy('version_questions.step_order')
                ->orderBy('version_questions.question_order')
                ->get([
                    'questions.id', 'questions.type', 'questions.options', 'questions.is_attention_check',
                    'questions.attention_check_answer',
                ]);
            foreach ($questions as $question) {
                $answerRows[] = [
                    'review_submission_id' => $submissionId,
                    'review_question_id' => $question->id,
                    'answer_value' => json_encode($this->demoAnswerFor($question), JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (in_array($taskStatus, ['approved', 'rejected'], true)) {
                $decision = $taskStatus === 'approved' ? 'approved' : 'rejected';
                DB::table('moderation_decisions')->upsert([[
                    'decision_key' => 'review-decision-'.$submissionId,
                    'review_submission_id' => $submissionId,
                    'decided_by' => null,
                    'decided_by_admin_id' => $adminId,
                    'decision' => $decision,
                    'reason_code' => $decision === 'approved' ? 'demo_quality_pass' : 'demo_quality_fail',
                    'reason' => $decision === 'approved' ? 'Review accepted for workflow testing.' : 'Review rejected for workflow testing.',
                    'policy_version' => 1,
                    'decided_at' => $now->copy()->subHours(8),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['decision_key'], ['decided_by', 'decided_by_admin_id', 'decision', 'reason_code', 'reason', 'updated_at']);

                if ($decision === 'rejected') {
                    DB::table('risk_signals')->upsert([[
                        'signal_key' => 'review-risk-'.$submissionId,
                        'review_submission_id' => $submissionId,
                        'ad_share_event_id' => null,
                        'signal_type' => 'demo_attention_required',
                        'severity' => 'medium',
                        'source' => 'demo_seed',
                        'rule_version' => 'v1',
                        'details' => json_encode(['seed' => true], JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                    ]], ['signal_key'], ['severity', 'details']);
                }

                if ($decision === 'approved') {
                    $walletId = $wallets[$task['user_number']]['review'] ?? null;
                    if ($walletId) {
                        DB::table('ledger_entries')->upsert([[
                            'wallet_id' => $walletId,
                            'entry_type' => 'review_reward',
                            'direction' => 'credit',
                            'amount_minor' => $task['reward_minor'],
                            'status' => 'posted',
                            'source_type' => 'review_submission',
                            'source_id' => $submissionId,
                            'idempotency_key' => 'review-reward-'.$submissionId,
                            'description' => 'Approved app review reward',
                            'available_at' => $now->copy()->subHours(8),
                            'posted_at' => $now->copy()->subHours(8),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]], ['idempotency_key'], ['status', 'available_at', 'posted_at', 'updated_at']);
                    }
                }
            }
        }

        if ($answerRows !== []) {
            DB::table('review_answers')->upsert(
                $answerRows,
                ['review_submission_id', 'review_question_id'],
                ['answer_value', 'updated_at']
            );
        }
    }

    private function demoAnswerFor(object $question): mixed
    {
        if ((bool) $question->is_attention_check && $question->attention_check_answer !== null) {
            $expected = json_decode($question->attention_check_answer, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($expected) && ($expected['mode'] ?? null) === 'contains') {
                return $expected['phrase'].' I tested the main app flow and checked its result myself.';
            }

            return $expected;
        }

        $options = json_decode($question->options ?: '[]', true, 512, JSON_THROW_ON_ERROR);

        return match ($question->type) {
            'single_choice' => $options[0] ?? 'The app worked as expected.',
            'multiple_choice' => [$options[0] ?? 'No issues found'],
            'rating' => 4,
            'long_text' => 'I opened the app and explored the main screens. The core purpose was clear, navigation was easy to follow, and I checked its key features before forming this opinion.',
            'boolean' => true,
            default => 'I opened the app and checked its main features.',
        };
    }

    private function seedAdShareActivity(array $users, array $wallets, $now): void
    {
        $adId = DB::table('ads')->where('campaign_key', 'energy-saving-global')->value('id');
        if (!$adId) {
            return;
        }
        $definitions = [
            [3, 'validated'], [4, 'pending_validation'], [5, 'rejected'], [6, 'validated'], [7, 'validated'],
        ];
        foreach ($definitions as [$number, $result]) {
            $userId = $users[$number] ?? null;
            if (!$userId) {
                continue;
            }
            $key = 'ad-share-energy-u'.$number;
            DB::table('ad_share_tasks')->upsert([[
                'ad_id' => $adId,
                'user_id' => $userId,
                'status' => $result === 'pending_validation' ? 'submitted' : 'completed',
                'idempotency_key' => $key,
                'reserved_reward_minor' => 50,
                'claimed_at' => $now->copy()->subDays(2),
                'expires_at' => $now->copy()->addDay(),
                'completed_at' => $result === 'pending_validation' ? null : $now->copy()->subDay(),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now,
            ]], ['idempotency_key'], ['status', 'completed_at', 'updated_at']);
            $taskId = (int) DB::table('ad_share_tasks')->where('idempotency_key', $key)->value('id');
            $token = 'track-energy-u'.$number;
            $eventStatus = $result;
            DB::table('ad_share_events')->upsert([[
                'ad_share_task_id' => $taskId,
                'channel' => 'copy_link',
                'tracking_token' => $token,
                'external_reference' => 'share-ref-'.$number,
                'status' => $eventStatus,
                'verification_method' => $result === 'validated' ? 'demo_attribution' : null,
                'evidence' => json_encode(['seed' => true, 'destination' => 'example.test'], JSON_THROW_ON_ERROR),
                'shared_at' => $now->copy()->subDay(),
                'validated_at' => $result === 'validated' ? $now->copy()->subHours(10) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['tracking_token'], ['status', 'verification_method', 'validated_at', 'updated_at']);

            $eventId = (int) DB::table('ad_share_events')->where('tracking_token', $token)->value('id');
            if ($result === 'rejected') {
                DB::table('risk_signals')->upsert([[
                    'signal_key' => 'ad-share-risk-'.$eventId,
                    'review_submission_id' => null,
                    'ad_share_event_id' => $eventId,
                    'signal_type' => 'demo_share_duplicate',
                    'severity' => 'low',
                    'source' => 'demo_seed',
                    'rule_version' => 'v1',
                    'details' => json_encode(['seed' => true], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]], ['signal_key'], ['severity', 'details']);
            }
            if (in_array($result, ['validated', 'pending_validation'], true)) {
                $walletId = $wallets[$number]['share'] ?? null;
                if ($walletId) {
                    DB::table('ledger_entries')->upsert([[
                        'wallet_id' => $walletId,
                        'entry_type' => 'share_reward',
                        'direction' => 'credit',
                        'amount_minor' => 50,
                        'status' => $result === 'validated' ? 'posted' : 'pending',
                        'source_type' => 'ad_share_event',
                        'source_id' => $eventId,
                        'idempotency_key' => 'share-reward-'.$eventId,
                        'description' => 'Ad share reward',
                        'available_at' => $result === 'validated' ? $now->copy()->subHours(10) : null,
                        'posted_at' => $result === 'validated' ? $now->copy()->subHours(10) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]], ['idempotency_key'], ['status', 'available_at', 'posted_at', 'updated_at']);
                }
            }
        }

        $validatedShares = DB::table('ad_share_events')
            ->join('ad_share_tasks', 'ad_share_tasks.id', '=', 'ad_share_events.ad_share_task_id')
            ->where('ad_share_tasks.ad_id', $adId)
            ->where('ad_share_events.status', 'validated')
            ->count();
        $reservedShares = DB::table('ad_share_tasks')
            ->where('ad_id', $adId)
            ->where('status', 'submitted')
            ->count();
        DB::table('ads')->where('id', $adId)->update([
            'total_share' => 125 + $validatedShares,
            'reserved_share' => $reservedShares,
        ]);
    }

    private function seedTopupsAndManualDeposits(array $users, array $wallets, $now): void
    {
        foreach ([3 => 2000, 2 => 4200, 4 => 5000] as $number => $amount) {
            $key = 'topup-user-'.$number;
            DB::table('payment_intents')->upsert([[
                'user_id' => $users[$number],
                'purpose' => 'account_topup',
                'source_type' => null,
                'source_id' => null,
                'amount_minor' => $amount,
                'currency' => 'USD',
                'provider' => 'demo',
                'provider_reference' => 'TOPUP-'.$number,
                'idempotency_key' => $key,
                'status' => 'paid',
                'provider_metadata' => json_encode(['seed' => true, 'not_a_real_payment' => true], JSON_THROW_ON_ERROR),
                'expires_at' => null,
                'paid_at' => $now->copy()->subDay(),
                'created_at' => $now->copy()->subDay(),
                'updated_at' => $now,
            ]], ['idempotency_key'], ['amount_minor', 'status', 'provider_metadata', 'paid_at', 'updated_at']);
            $intentId = DB::table('payment_intents')->where('idempotency_key', $key)->value('id');
            DB::table('payment_transactions')->upsert([[
                'payment_intent_id' => $intentId,
                'provider_transaction_id' => 'TOPUP-TXN-'.$number,
                'transaction_type' => 'capture',
                'amount_minor' => $amount,
                'currency' => 'USD',
                'status' => 'succeeded',
                'provider_response' => json_encode(['seed' => true, 'not_a_real_payment' => true], JSON_THROW_ON_ERROR),
                'occurred_at' => $now->copy()->subDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['provider_transaction_id'], ['payment_intent_id', 'amount_minor', 'status', 'updated_at']);
            DB::table('payment_webhook_events')->upsert([[
                'provider' => 'demo',
                'provider_event_id' => 'TOPUP-EVENT-'.$number,
                'event_type' => 'topup.succeeded',
                'payload' => json_encode(['seed' => true, 'payment_intent_id' => $intentId], JSON_THROW_ON_ERROR),
                'signature_valid' => true,
                'processing_status' => 'processed',
                'processing_error' => null,
                'received_at' => $now->copy()->subDay(),
                'processed_at' => $now->copy()->subDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['provider', 'provider_event_id'], ['event_type', 'payload', 'processing_status', 'processed_at', 'updated_at']);
            $walletId = $wallets[$number]['topup_fund'] ?? null;
            if ($walletId) {
                DB::table('ledger_entries')->upsert([[
                    'wallet_id' => $walletId,
                    'entry_type' => 'topup_credit',
                    'direction' => 'credit',
                    'amount_minor' => $amount,
                    'status' => 'posted',
                    'source_type' => 'payment_intent',
                    'source_id' => $intentId,
                    'idempotency_key' => 'topup-ledger-'.$number,
                    'description' => 'Users account top-up',
                    'available_at' => $now->copy()->subDay(),
                    'posted_at' => $now->copy()->subDay(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['idempotency_key'], ['status', 'updated_at']);
            }
        }

        $userNumber = 8;
        $key = 'manual-deposit-user-'.$userNumber;
        DB::table('payment_intents')->upsert([[
            'user_id' => $users[$userNumber],
            'purpose' => 'manual_deposit',
            'source_type' => null,
            'source_id' => null,
            'amount_minor' => 10000,
            'currency' => 'USD',
            'provider' => 'manual_bank',
            'provider_reference' => 'MANUAL-'.$userNumber,
            'idempotency_key' => $key,
            'status' => 'pending',
            'provider_metadata' => json_encode(['seed' => true, 'not_a_real_payment' => true], JSON_THROW_ON_ERROR),
            'expires_at' => null,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['idempotency_key'], ['amount_minor', 'status', 'updated_at']);
        $intentId = (int) DB::table('payment_intents')->where('idempotency_key', $key)->value('id');
        DB::table('manual_deposit_submissions')->upsert([[
            'payment_intent_id' => $intentId,
            'user_id' => $users[$userNumber],
            'amount_minor' => 10000,
            'currency' => 'USD',
            'bank_reference' => 'BANK-REF-008',
            'proof_storage_path' => 'prototype://deposit-proofs/user-08',
            'status' => 'submitted',
            'reviewed_by' => null,
            'review_note' => null,
            'submitted_at' => $now->copy()->subHours(3),
            'reviewed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['payment_intent_id'], ['status', 'proof_storage_path', 'updated_at']);
    }

    private function seedReferralActivity(array $users, array $wallets, $now): void
    {
        $pairs = [[3, 8], [4, 9]];
        foreach ($pairs as [$referrerNumber, $referredNumber]) {
            if (!isset($users[$referrerNumber], $users[$referredNumber])) {
                continue;
            }
            $code = sprintf('TLDEMO%02d', $referrerNumber);
            $codeId = DB::table('referral_codes')->where('code', $code)->value('id');
            DB::table('referral_attributions')->upsert([[
                'referral_code_id' => $codeId,
                'referrer_user_id' => $users[$referrerNumber],
                'referred_user_id' => $users[$referredNumber],
                'status' => $referredNumber === 9 ? 'qualified' : 'attributed',
                'attributed_at' => $now->copy()->subDays(4),
                'qualified_at' => $referredNumber === 9 ? $now->copy()->subDay() : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['referred_user_id'], ['referral_code_id', 'status', 'qualified_at', 'updated_at']);

            if ($referredNumber === 9 && isset($wallets[$referrerNumber]['referral'])) {
                $attributionId = (int) DB::table('referral_attributions')->where('referred_user_id', $users[$referredNumber])->value('id');
                $walletId = $wallets[$referrerNumber]['referral'];
                DB::table('ledger_entries')->upsert([[
                    'wallet_id' => $walletId,
                    'entry_type' => 'referral_reward',
                    'direction' => 'credit',
                    'amount_minor' => 100,
                    'status' => 'posted',
                    'source_type' => 'referral_attribution',
                    'source_id' => $attributionId,
                    'idempotency_key' => 'referral-ledger-'.$attributionId,
                    'description' => 'Qualified referral reward',
                    'available_at' => $now->copy()->subDay(),
                    'posted_at' => $now->copy()->subDay(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['idempotency_key'], ['status', 'updated_at']);
                $ledgerId = DB::table('ledger_entries')->where('idempotency_key', 'referral-ledger-'.$attributionId)->value('id');
                DB::table('referral_rewards')->upsert([[
                    'referral_attribution_id' => $attributionId,
                    'beneficiary_user_id' => $users[$referrerNumber],
                    'referral_level' => 1,
                    'reward_type' => 'task',
                    'source_type' => 'demo_referral_qualification',
                    'source_id' => $attributionId,
                    'wallet_id' => $walletId,
                    'ledger_entry_id' => $ledgerId,
                    'amount_minor' => 100,
                    'currency' => 'USD',
                    'status' => 'paid',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['referral_attribution_id', 'beneficiary_user_id', 'referral_level', 'reward_type', 'source_type', 'source_id'], ['wallet_id', 'ledger_entry_id', 'amount_minor', 'status', 'updated_at']);
            }
        }
    }

    private function seedWithdrawals(array $users, array $wallets, $now): void
    {
        $optionIds = DB::table('withdrawal_options')->pluck('id', 'option_id')->all();
        foreach ([
            [3, 'bank_transfer', 'pending', 300],
            [4, 'paypal', 'processing', 200],
            [5, 'crypto', 'paid', 100],
        ] as [$number, $optionKey, $status, $amount]) {
            if (!isset($users[$number], $wallets[$number]['review'], $optionIds[$optionKey])) {
                continue;
            }
            $key = 'withdrawal-u'.$number;

            // Add a clearly-labelled fixture credit so these sample requests have funds to draw from.
            if (in_array($number, [3, 4], true)) {
                DB::table('ledger_entries')->upsert([[
                    'wallet_id' => $wallets[$number]['review'],
                    'entry_type' => 'demo_opening_earnings',
                    'direction' => 'credit',
                    'amount_minor' => 1000,
                    'status' => 'posted',
                    'source_type' => 'demo_fixture',
                    'source_id' => $users[$number],
                    'idempotency_key' => 'withdrawal-funds-u'.$number,
                    'description' => 'only opening earnings for withdrawal testing',
                    'available_at' => $now->copy()->subDays(3),
                    'posted_at' => $now->copy()->subDays(3),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['idempotency_key'], ['status', 'updated_at']);
            }

            DB::table('withdrawal_requests')->upsert([[
                'user_id' => $users[$number],
                'wallet_id' => $wallets[$number]['review'],
                'withdrawal_option_id' => $optionIds[$optionKey],
                'amount_minor' => $amount,
                'fee_minor' => 10,
                'currency' => 'USD',
                'payout_details' => json_encode(['demo' => true, 'destination' => 'test-only'], JSON_THROW_ON_ERROR),
                'status' => $status,
                'idempotency_key' => $key,
                'provider_reference' => 'WD-'.$number,
                'processed_by' => null,
                'processed_by_admin_id' => $status === 'pending' ? null : (int) Admin::query()->where('email', 'admin@tlstore.test')->value('id'),
                'failure_reason' => null,
                'requested_at' => $now->copy()->subDays(2),
                'processed_at' => $status === 'pending' ? null : $now->copy()->subDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['idempotency_key'], ['status', 'processed_by', 'processed_by_admin_id', 'processed_at', 'updated_at']);

            DB::table('ledger_entries')->upsert([[
                'wallet_id' => $wallets[$number]['review'],
                'entry_type' => 'withdrawal_debit',
                'direction' => 'debit',
                'amount_minor' => $amount + 10,
                'status' => $status === 'pending' ? 'reserved' : 'posted',
                'source_type' => 'withdrawal_request',
                'source_id' => DB::table('withdrawal_requests')->where('idempotency_key', $key)->value('id'),
                'idempotency_key' => 'withdrawal-ledger-u'.$number,
                'description' => 'Withdrawal amount plus processing fee',
                'available_at' => $status === 'pending' ? null : $now->copy()->subDay(),
                'posted_at' => $status === 'pending' ? null : $now->copy()->subDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['idempotency_key'], ['status', 'available_at', 'posted_at', 'updated_at']);
        }
    }

    private function seedNotificationsAndOperations(array $users, $now): void
    {
        foreach ([3, 4, 5, 6, 8] as $number) {
            if (!isset($users[$number])) {
                continue;
            }
            $key = 'welcome-user-'.$number;
            DB::table('notifications')->upsert([[
                'user_id' => $users[$number],
                'notification_key' => $key,
                'type' => 'demo_welcome',
				'title' => 'Welcome to TrustLens 🎉',
				'body' => "Discover, review, and share your favorite apps and ads. Explore new apps, share your experiences, and help others make informed choices through honest reviews and recommendations.\n\nYour opinions matter. Let's discover and share together! 🚀",
                'data' => json_encode(['seed' => true], JSON_THROW_ON_ERROR),
                'read_at' => $number === 3 ? $now->copy()->subHours(2) : null,
                'created_at' => $now->copy()->subHours(4),
                'updated_at' => $now,
            ]], ['notification_key'], ['title', 'body', 'data', 'read_at', 'updated_at']);

            $notificationId = DB::table('notifications')->where('notification_key', $key)->value('id');
            $deviceId = DB::table('user_devices')->where('user_id', $users[$number])->value('id');
            if ($deviceId) {
                DB::table('notification_deliveries')->upsert([[
                    'notification_id' => $notificationId,
                    'user_device_id' => $deviceId,
                    'channel' => 'push',
                    'provider' => 'demo',
                    'status' => 'sent',
                    'provider_message_id' => 'PUSH-'.$number,
                    'failure_reason' => null,
                    'sent_at' => $now->copy()->subHours(4),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['notification_id', 'user_device_id', 'channel'], ['status', 'provider_message_id', 'sent_at', 'updated_at']);
            }
        }

        DB::table('audit_logs')->upsert([[
            'audit_key' => 'audit-campaign-publish',
            'actor_user_id' => null,
            'actor_admin_id' => (int) Admin::query()->where('email', 'admin@tlstore.test')->value('id'),
            'action' => 'campaign.demo_seeded',
            'entity_type' => 'app_review_campaign',
            'entity_id' => 'review-fitpulse-ng',
            'before_values' => null,
            'after_values' => json_encode(['status' => 'active', 'seed' => true], JSON_THROW_ON_ERROR),
            'request_id' => 'seed-run',
            'ip_hash' => null,
            'created_at' => $now,
        ]], ['audit_key'], ['actor_user_id', 'actor_admin_id', 'action', 'after_values']);

        DB::table('outbox_events')->upsert([[
            'event_key' => 'event-review-approved',
            'event_type' => 'review.submission.approved',
            'aggregate_type' => 'review_submission',
            'aggregate_id' => 'seed-sample',
            'payload' => json_encode(['seed' => true, 'purpose' => 'outbox workflow test'], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $now,
            'processed_at' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['event_key'], ['event_type', 'payload', 'status', 'updated_at']);
    }

    private function planKeyForUser(int $number): string
    {
        return match ($number % 3) {
            0 => 'market-gold',
            1 => 'market-lite',
            default => 'market-premium',
        };
    }
}
