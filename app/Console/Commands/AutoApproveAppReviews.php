<?php

namespace App\Console\Commands;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Actions\Moderation\DecideReviewSubmission;
use App\Application\Money\CurrencyMinorUnits;
use App\Console\Support\TaskRewardCalculator;
use App\Models\Admin;
use App\Models\LedgerEntry;
use App\Models\ReferralReward;
use App\Models\ReviewSubmission;
use App\Models\ReviewTask;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\UserPackageAddon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoApproveAppReviews extends Command
{
    protected $signature = 'app:auto-approve-app-reviews
        {--limit=100 : Maximum pending submissions to process}';

    protected $description = 'Auto-approve eligible app reviews after their configured wait time';

    public function handle(
        ListingEligibility $eligibility,
        TaskRewardCalculator $rewards,
        DecideReviewSubmission $decide
    ): int {
        $startedAt = microtime(true);

        $report = [
            'started_at' => now()->toDateTimeString(),
            'status' => 'running',
            'delay_minutes' => null,
            'limit' => null,
            'total_checked' => 0,
            'approved' => 0,
            'rejected' => 0,
            'skipped' => 0,
            'failed' => 0,
            'submissions' => [],
            'failures' => [],
        ];

        $admin = Admin::query()->where('status', 'active')->orderBy('id')->first();
        if (! $admin) {
            $message = 'Automatic moderation requires at least one active admin account for its audit trail.';

            $this->error($message);

            $report['status'] = 'failed';
            $report['message'] = $message;

            $this->writeReport($report, $startedAt);

            return self::FAILURE;
        }

        $delay = $this->delayMinutes(
            'app.auto_approve_reviews.delay_minutes'
        );

        $cutoff = now()->subMinutes($delay);
        $limit = min(max((int) $this->option('limit'), 1), 1000);

        $report['delay_minutes'] = $delay;
        $report['limit'] = $limit;

        $submissions = ReviewSubmission::query()
            ->with([
                'answers.question',
                'task.reviewer',
                'task.campaign.app',
                'task.templateVersion.questions',
            ])
            ->where('status', 'pending_review')
            ->whereHas('reviewer', fn ($query) =>
                $query->where('status', 'active')
            )
            ->where('submitted_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $report['total_checked'] = $submissions->count();

        foreach ($submissions as $submission) {
            $task = $submission->task;
            $campaign = $task?->campaign;
            $app = $campaign?->app;

            $baseDetails = [
                'submission_id' => $submission->id,
                'reviewer_id' => $submission->reviewer_id,
                'task_id' => $submission->review_task_id,
            ];

            try {
                $subscription = $task && $app
                    ? $eligibility->subscriptionForTask(
                        $submission->reviewer,
                        'review',
                        $app->store,
                        $app->category,
                        true,
                    )
                    : null;

                if (! $task || ! $campaign || ! $subscription) {
                    $report['skipped']++;

                    $report['submissions'][] = [
                        ...$baseDetails,
                        'status' => 'skipped',
                        'reason' => 'Task, campaign or eligible subscription unavailable.',
                    ];

                    continue;
                }

                $rewardMinor = $rewards->forReview(
                    (int) $task->reserved_reward_minor,
                    $campaign->currency,
                    $subscription->plan,
                );

                if ($rewardMinor === null || $rewardMinor < 1) {
                    $report['skipped']++;

                    $report['submissions'][] = [
                        ...$baseDetails,
                        'status' => 'skipped',
                        'reason' => 'Package reward or currency conversion unavailable.',
                    ];

                    continue;
                }

                $result = DB::transaction(function () use (
                    $task,
                    $submission,
                    $rewardMinor,
                    $admin,
                    $subscription,
                    $decide
                ): array {
                    $lockedTask = ReviewTask::query()
                        ->lockForUpdate()
                        ->findOrFail($task->id);

                    if ($lockedTask->status->value !== 'submitted') {
                        return ['status' => 'skipped'];
                    }

                    $failedAttentionChecks = $this->failedAttentionChecks($submission);
                    if ($failedAttentionChecks !== []) {
                        $questionKeys = implode(', ', array_column($failedAttentionChecks, 'question_key'));
                        $reason = 'The review did not pass all attention checks. Required response instructions were missed for: '.$questionKeys.'.';
                        $decide->handle(
                            $admin,
                            $submission->fresh(),
                            'rejected',
                            'attention_check_failed',
                            $reason,
                            $subscription->plan,
                        );

                        return [
                            'status' => 'rejected',
                            'reason' => $reason,
                            'attention_check_failures' => $failedAttentionChecks,
                        ];
                    }

                    if ($subscription->plan->addons) {
                        $addon = $subscription->plan->packageAddon;

                        $hasActiveAddon = $addon !== null
                            && UserPackageAddon::query()
                                ->where('user_id', $submission->reviewer_id)
                                ->where('package_addon_id', $addon->id)
                                ->where('status', 'active')
                                ->where('starts_at', '<=', now())
                                ->where('ends_at', '>', now())
                                ->exists();

                        if (! $hasActiveAddon) {
                            $decide->handle(
                                $admin,
                                $submission->fresh(),
                                'rejected',
                                'package_addon_required',
                                'This review package requires an active package add-on. The submission was rejected because no active add-on was found.',
                                $subscription->plan,
                            );

                            return [
                                'status' => 'rejected',
                                'reason' => 'This review package requires an active package add-on. The submission was rejected because no active add-on was found.',
                            ];
                        }
                    }

                    $lockedTask->update([
                        'reserved_reward_minor' => $rewardMinor,
                    ]);

                    $decide->handle(
                        $admin,
                        $submission->fresh(),
                        'approved',
                        'app_auto_approved',
                        'Automatically approved after the configured review delay.',
                        $subscription->plan,
                    );

                    return ['status' => 'approved'];
                });

                $resultStatus = $result['status'];
                if ($resultStatus === 'approved') {
                    $report['approved']++;
                } elseif ($resultStatus === 'rejected') {
                    $report['rejected']++;
                } else {
                    $report['skipped']++;
                }

                $referralReward = null;
                if ($resultStatus === 'approved') {
                    $referralReward = $this->ensureTaskReferralTransaction($submission);
                }

                $report['submissions'][] = [
                    ...$baseDetails,
                    'status' => $resultStatus,
                    'reason' => $result['reason'] ?? null,
                    'attention_check_failures' => $result['attention_check_failures'] ?? [],
                    'reward_minor' => $resultStatus === 'approved'
                        ? $rewardMinor
                        : null,
                    'task_referral_reward' => $referralReward,
                ];

            } catch (Throwable $exception) {
                $report['failed']++;

                $report['failures'][] = [
                    ...$baseDetails,
                    'error' => $exception->getMessage(),
                    'exception' => get_class($exception),
                ];

                $this->warn(
                    "Could not process review submission {$submission->id}: {$exception->getMessage()}"
                );
            }
        }

        $report['status'] = $report['failed'] > 0
            ? 'completed_with_errors'
            : 'completed';

        $this->writeReport($report, $startedAt);
        $report['execution_seconds'] = round(
            microtime(true) - $startedAt,
            2
        );

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total checked', $report['total_checked']],
                ['Approved', $report['approved']],
                ['Rejected', $report['rejected']],
                ['Skipped', $report['skipped']],
                ['Failed', $report['failed']],
                ['Execution time (seconds)', $report['execution_seconds']],
            ]
        );

        $this->info(
            "Approved {$report['approved']} app review(s); " .
            "rejected {$report['rejected']}; " .
            "skipped {$report['skipped']}; " .
            "failed {$report['failed']}. " .
            "Wait time: {$delay} minute(s)."
        );

        return $report['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function delayMinutes(string $key): int
    {
        $value = SystemSetting::query()
            ->where('setting_key', $key)
            ->value('value');

        if (is_array($value)) {
            $value = $value['minutes']
                ?? $value['delay_minutes']
                ?? 60;
        }

        return min(
            max(is_numeric($value) ? (int) $value : 60, 0),
            525600
        );
    }

    /** @return array<int, array{question_key:string}> */
    private function failedAttentionChecks(ReviewSubmission $submission): array
    {
        $answers = $submission->answers->keyBy('review_question_id');
        $failures = [];

        foreach ($submission->task?->templateVersion?->questions ?? [] as $question) {
            if (! $question->is_attention_check) {
                continue;
            }

            $questionAnswer = $answers->get($question->id);
            $actual = is_array($questionAnswer?->answer_value)
                ? ($questionAnswer->answer_value['value'] ?? null)
                : null;
            if (! $questionAnswer || ! $this->matchesAttentionAnswer($actual, $question->attention_check_answer)) {
                $failures[] = ['question_key' => $question->question_key];
            }
        }

        return $failures;
    }

    private function matchesAttentionAnswer(mixed $actual, mixed $expected): bool
    {
        if (is_array($expected) && ($expected['mode'] ?? null) === 'contains') {
            $phrase = (string) ($expected['phrase'] ?? '');
            if (! is_string($actual) || $phrase === '') {
                return false;
            }

            if (($expected['case_sensitive'] ?? false) === true) {
                return str_contains($actual, $phrase);
            }

            return str_contains(mb_strtolower($actual), mb_strtolower($phrase));
        }

        if (is_array($actual) && is_array($expected)) {
            $actualValues = array_map('strval', $actual);
            $expectedValues = array_map('strval', $expected);
            sort($actualValues);
            sort($expectedValues);

            return $actualValues === $expectedValues;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $actual === $expected;
    }

    /**
     * Ensure a task referral reward appears in account history even if a
     * ledger observer was unavailable while the reward was posted.
     *
     * @return array<string, mixed>|null
     */
    private function ensureTaskReferralTransaction(ReviewSubmission $submission): ?array
    {
        $reward = ReferralReward::query()
            ->where('reward_type', 'task')
            ->where('source_type', 'review_submission')
            ->where('source_id', $submission->id)
            ->where('status', 'paid')
            ->first();

        if (! $reward) {
            return null;
        }

        $ledger = LedgerEntry::query()->with('wallet')->find($reward->ledger_entry_id);
        if (! $ledger?->wallet) {
            return [
                'reward_id' => $reward->id,
                'status' => 'paid',
                'transaction_recorded' => false,
            ];
        }

        $currency = strtoupper($ledger->wallet->currency);
        $transaction = Transaction::query()->updateOrCreate(
            ['reference' => 'ledger:'.$ledger->id],
            [
                'user_id' => $ledger->wallet->user_id,
                'type' => 'referral_bonus',
                'direction' => 'credit',
                'amount' => CurrencyMinorUnits::fromMinor((int) $ledger->amount_minor, $currency),
                'fee' => 0,
                'wallet' => $ledger->wallet->wallet_type->value,
                'status' => $ledger->status->value === 'posted' ? 'completed' : $ledger->status->value,
                'description' => $ledger->description ?: 'Referral reward for approved app review',
                'meta' => array_merge($ledger->metadata ?? [], [
                    'currency' => $currency,
                    'currency_minor_units' => CurrencyMinorUnits::precision($currency),
                    'amount_minor' => (int) $ledger->amount_minor,
                    'ledger_entry_id' => $ledger->id,
                    'referral_reward_id' => $reward->id,
                    'reward_type' => 'task',
                    'source_type' => 'review_submission',
                    'source_id' => $submission->id,
                ]),
            ],
        );

        return [
            'reward_id' => $reward->id,
            'transaction_id' => $transaction->id,
            'amount_minor' => (int) $reward->amount_minor,
            'currency' => strtoupper($reward->currency),
            'status' => $reward->status,
            'transaction_recorded' => true,
        ];
    }

    private function writeReport(array $report, float $startedAt): void
    {
        $report['finished_at'] = now()->toDateTimeString();

        Log::channel('review')->info(
            'App review report',
            $report
        );

        $this->line('Auto-approval report saved to application logs.');
    }
}
