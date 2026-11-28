<?php

namespace App\Console\Commands;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Application\Actions\Moderation\DecideAdShare;
use App\Console\Support\TaskRewardCalculator;
use App\Models\Admin;
use App\Models\AdShareEvent;
use App\Models\AdShareTask;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoApproveAdShares extends Command
{
    protected $signature = 'app:auto-approve-ad-shares
        {--limit=100 : Maximum pending submissions to process}';

    protected $description = 'Auto-approve eligible ad shares after their configured wait time';

    public function handle(
        ListingEligibility $eligibility,
        TaskRewardCalculator $rewards,
        DecideAdShare $decide
    ): int {
        $startedAt = microtime(true);

        $report = [
            'started_at' => now()->toDateTimeString(),
            'status' => 'running',
            'delay_minutes' => null,
            'limit' => null,
            'total_checked' => 0,
            'approved' => 0,
            'skipped' => 0,
            'failed' => 0,
            'events' => [],
            'failures' => [],
        ];

        $admin = Admin::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
        if (! $admin) {
            $message = 'Automatic moderation requires at least one active admin account for its audit trail.';

            $this->error($message);

            $report['status'] = 'failed';
            $report['message'] = $message;

            $this->writeReport($report, $startedAt);

            return self::FAILURE;
        }

        $delay = $this->delayMinutes(
            'app.auto_approve_ads.delay_minutes'
        );

        $cutoff = now()->subMinutes($delay);
        $limit = min(max((int) $this->option('limit'), 1), 1000);

        $report['delay_minutes'] = $delay;
        $report['limit'] = $limit;

        $events = AdShareEvent::query()
            ->with(['task.user', 'task.ad'])
            ->where('status', 'pending_validation')
            ->whereHas('task.user', fn ($query) =>
                $query->where('status', 'active')
            )
            ->whereRaw(
                'COALESCE(shared_at, created_at) <= ?',
                [$cutoff]
            )
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $report['total_checked'] = $events->count();

        foreach ($events as $event) {
            $task = $event->task;

            $baseDetails = [
                'event_id' => $event->id,
                'task_id' => $event->task_id,
                'user_id' => $task?->user_id,
            ];

            try {
                $subscription = $task
                    ? $eligibility->subscriptionForTask(
                        $task->user,
                        'ad'
                    )
                    : null;

                if (! $task || ! $subscription || ! $task->ad) {
                    $report['skipped']++;

                    $report['events'][] = [
                        ...$baseDetails,
                        'status' => 'skipped',
                        'reason' => 'Task, eligible subscription or advertisement unavailable.',
                    ];

                    continue;
                }

                $rewardMinor = $rewards->forAdShare(
                    $task->user,
                    $task,
                    $subscription->plan
                );

                if ($rewardMinor === null || $rewardMinor < 1) {
                    $report['skipped']++;

                    $report['events'][] = [
                        ...$baseDetails,
                        'status' => 'skipped',
                        'reason' => 'Package reward unavailable, currency conversion unavailable, or daily cap exhausted.',
                    ];

                    $this->warn(
                        "Skipped share event {$event->id}: reward unavailable or daily cap exhausted."
                    );

                    continue;
                }

                $result = DB::transaction(function () use (
                    $task,
                    $event,
                    $rewardMinor,
                    $admin,
                    $decide
                ): string {
                    $lockedTask = AdShareTask::query()
                        ->lockForUpdate()
                        ->findOrFail($task->id);

                    if ($lockedTask->status->value !== 'submitted') {
                        return 'skipped';
                    }

                    $lockedTask->update([
                        'reserved_reward_minor' => $rewardMinor,
                    ]);

                    $decide->handle(
                        $admin,
                        $event->fresh(),
                        'approved',
                        'app_auto_approved',
                        'Automatically approved after the configured review delay.',
                    );

                    return 'approved';
                });

                if ($result === 'approved') {
                    $report['approved']++;
                } else {
                    $report['skipped']++;
                }

                $report['events'][] = [
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
                    'exception' => get_class($exception),
                    'error' => $exception->getMessage(),
                ];

                $this->warn(
                    "Could not process ad-share event {$event->id}: {$exception->getMessage()}"
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
                ['Skipped', $report['skipped']],
                ['Failed', $report['failed']],
                ['Execution time (seconds)', $report['execution_seconds']],
            ]
        );

        $this->info(
            "Approved {$report['approved']} ad share(s); " .
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

        Log::channel('adshare')->info(
            'Ad share report',
            $report
        );

        $this->line('Ad-share auto-approval report saved.');
    }
}
