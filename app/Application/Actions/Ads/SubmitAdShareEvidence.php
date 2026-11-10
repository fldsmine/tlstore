<?php

namespace App\Application\Actions\Ads;

use App\Application\Actions\Marketplace\ListingEligibility;
use App\Models\AdShareEvent;
use App\Models\AdShareTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubmitAdShareEvidence
{
    public function __construct(private readonly ListingEligibility $eligibility) {}

    public function handle(User $user, AdShareTask $task, string $channel, string $postUrl, ?string $screenshotPath): AdShareEvent
    {
        return DB::transaction(function () use ($user, $task, $channel, $postUrl, $screenshotPath): AdShareEvent {
            $task = AdShareTask::query()->with('ad')->lockForUpdate()->findOrFail($task->id);
            if ($task->user_id !== $user->id || $task->status->value !== 'reserved' || ($task->expires_at && $task->expires_at->isPast())) {
                throw ValidationException::withMessages(['task' => 'This share task cannot accept evidence.']);
            }
            $subscription = $this->eligibility->subscriptionForTask($user, 'ad');
            if (! $subscription || ($subscription->plan?->max_ad_shares ?? 0) < 1) {
                throw ValidationException::withMessages(['task' => 'Your package is no longer eligible for this ad share.']);
            }
            $expected = strtolower(trim((string) $task->ad->type));
            if (str_contains($expected, 'twitter') || $expected === 'x') $expected = 'x';
            if (! in_array($expected, ['telegram', 'facebook', 'youtube', 'instagram', 'tiktok', 'x', 'whatsapp', 'others'], true)) $expected = 'others';
            $submitted = strtolower(trim($channel));
            if (str_contains($submitted, 'twitter') || $submitted === 'x') $submitted = 'x';
            if ($submitted === 'other') $submitted = 'others';
            if ($submitted !== $expected) {
                throw ValidationException::withMessages(['channel' => 'Submit this task using its assigned '.$task->ad->type.' channel.']);
            }

            $event = AdShareEvent::query()->create([
                'ad_share_task_id' => $task->id,
                'channel' => $channel,
                'tracking_token' => Str::random(48),
                'external_reference' => $postUrl,
                'status' => 'pending_validation',
                'verification_method' => 'admin_manual_review',
                'evidence' => ['post_url' => $postUrl, 'screenshot_path' => $screenshotPath],
                'shared_at' => now(),
            ]);
            $task->update(['status' => 'submitted', 'completed_at' => now()]);

            return $event->load('task.ad');
        });
    }
}
