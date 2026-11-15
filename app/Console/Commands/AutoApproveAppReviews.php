<?php

namespace App\Console\Commands;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Actions\Moderation\DecideReviewSubmission;
use App\Console\Support\TaskRewardCalculator;
use App\Models\Admin;
use App\Models\ReviewSubmission;
use App\Models\ReviewTask;
use App\Models\SystemSetting;
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

        if (app()->isProduction()) {
            $message = 'Demo app-review auto-approval is disabled in production.';

            $this->error($message);

            $report['status'] = 'disabled';
            $report['message'] = $message;

            $this->writeReport($report, $startedAt);

            return self::FAILURE;
        }

        $admin = Admin::query()->where('email', 'admin@tlstore.test')->first();
        if (! $admin) {
            $message = 'The demo admin account is missing. Seed demo data first.';

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
            ->with(['task.reviewer', 'task.campaign.app'])
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
                'task_id' => $submission->task_id,
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
                ): string {
                    $lockedTask = ReviewTask::query()
                        ->lockForUpdate()
                        ->findOrFail($task->id);

                    if ($lockedTask->status->value !== 'submitted') {
                        return 'skipped';
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

                            return 'rejected';
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

                    return 'approved';
                });

                if ($result === 'approved') {
                    $report['approved']++;
                } elseif ($result === 'rejected') {
                    $report['rejected']++;
                } else {
                    $report['skipped']++;
                }

                $report['submissions'][] = [
                    ...$baseDetails,
                    'status' => $result,
                    'reward_minor' => $result === 'approved'
                        ? $rewardMinor
                        : null,
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
