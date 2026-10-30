<?php

namespace App\Jobs;

use App\Enums\MailingCampaignStatus;
use App\Enums\MailingRecipientStatus;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Services\Mailing\MailingDispatchSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueMailingCampaignRecipients implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Queue-window saturation is a normal wait, not a failed attempt. Database/cache errors remain bounded.
    public int $tries = 0;

    public int $maxExceptions;

    public int $uniqueFor = 86400;

    public function __construct(public string $campaignId)
    {
        $this->maxExceptions = max(1, (int) config('mailing.max_attempts', 3));
    }

    public function uniqueId(): string
    {
        return $this->campaignId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(MailingDispatchSchedule $schedule): void
    {
        $deliveries = Cache::lock('mailing.dispatch.window_lock', 120)->block(10, function () use ($schedule): array {
            return DB::transaction(function () use ($schedule): array {
                $campaign = MailingCampaign::query()->whereKey($this->campaignId)->lockForUpdate()->first();
                if (! $campaign || ! in_array($campaign->status, [MailingCampaignStatus::QUEUED, MailingCampaignStatus::SENDING], true)) {
                    return [];
                }

                $inFlight = [
                    MailingRecipientStatus::QUEUED->value,
                    MailingRecipientStatus::SENDING->value,
                    MailingRecipientStatus::RETRYING->value,
                ];
                $globalLimit = max(1, min(200, (int) config('mailing.max_inflight_recipients', 200)));
                $campaignLimit = max(1, min(20, (int) config('mailing.per_campaign_inflight', 20)));
                $globalInFlight = MailingCampaignRecipient::query()->whereIn('status', $inFlight)->count();
                $campaignInFlight = $campaign->recipients()->whereIn('status', $inFlight)->count();
                $chunkSize = max(1, min(1000, (int) config('mailing.dispatch_chunk_size', 200)));
                $capacity = min($chunkSize, $globalLimit - $globalInFlight, $campaignLimit - $campaignInFlight);
                if ($capacity < 1) {
                    return [];
                }

                $recipients = $campaign->recipients()
                    ->where('status', MailingRecipientStatus::PENDING->value)
                    ->orderBy('id')
                    ->limit($capacity)
                    ->lockForUpdate()
                    ->get(['id']);
                if ($recipients->isEmpty()) {
                    return [];
                }

                $slots = $schedule->reserveSlots($recipients->count());
                $deliveries = [];
                foreach ($recipients as $index => $recipient) {
                    $availableAt = $slots[$index];
                    $recipient->status = MailingRecipientStatus::QUEUED;
                    $recipient->available_at = $availableAt;
                    $recipient->save();
                    $deliveries[] = [
                        'recipient_id' => (string) $recipient->id,
                        'available_at' => $availableAt->getTimestamp(),
                    ];
                }

                return $deliveries;
            });
        });

        foreach ($deliveries as $index => $delivery) {
            $delay = max(0, (int) $delivery['available_at'] - now()->timestamp);
            $uniqueFor = max(300, $delay + 300);

            try {
                DeliverMailingCampaignRecipient::dispatch($delivery['recipient_id'], $uniqueFor)->delay($delay);
            } catch (Throwable $exception) {
                $notDispatchedIds = array_column(array_slice($deliveries, $index), 'recipient_id');
                MailingCampaignRecipient::query()
                    ->whereIn('id', $notDispatchedIds)
                    ->where('status', MailingRecipientStatus::QUEUED->value)
                    ->update([
                        'status' => MailingRecipientStatus::PENDING->value,
                        'available_at' => null,
                        'updated_at' => now(),
                    ]);

                throw $exception;
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Mailing campaign dispatch coordinator failed; the scheduler will retry it.', [
            'campaign_id' => $this->campaignId,
            'error_type' => class_basename($exception),
        ]);
    }
}
