<?php

namespace App\Jobs;

use App\Enums\MailingCampaignStatus;
use App\Mail\MailingCampaignMail;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\User;
use App\Services\Mailing\MailingCampaignDeliveryState;
use App\Services\Mailing\MailingContentRenderer;
use App\Services\Mailing\MailingInfrastructureHealth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverMailingCampaignRecipient implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Limiter releases do not count as transport failures. The bounded queue window keeps their
    // number finite, while maxExceptions separately caps genuine transport failures.
    public int $tries = 0;

    public int $maxExceptions;

    public int $uniqueFor;

    public function __construct(public string $recipientId, int $uniqueForSeconds = 300)
    {
        $this->maxExceptions = max(1, (int) config('mailing.max_attempts', 3));
        $this->uniqueFor = max(300, $uniqueForSeconds);
    }

    public function uniqueId(): string
    {
        return $this->recipientId;
    }

    public function backoff(): array
    {
        return (array) config('mailing.backoff_seconds', [60, 300, 900]);
    }

    public function middleware(): array
    {
        return [new RateLimited('mailing-campaign-emails')];
    }

    public function handle(
        MailingCampaignDeliveryState $state,
        MailingInfrastructureHealth $health,
        MailingContentRenderer $renderer,
    ): void {
        $attempt = $state->begin($this->recipientId);
        if ($attempt === null) {
            return;
        }
        if ($attempt['busy'] ?? false) {
            $this->release((int) ($attempt['retry_after'] ?? 30));

            return;
        }

        if ($attempt['user_id'] === null) {
            $state->failPermanently($this->recipientId, 'RecipientMissing');
            $this->queueNextRecipient($attempt['campaign_id']);

            return;
        }

        $campaign = MailingCampaign::query()->find($attempt['campaign_id']);
        $user = User::query()->with('package')->find($attempt['user_id']);
        if (! $campaign || ! $user) {
            $state->failPermanently($this->recipientId, 'RecipientMissing');
            $this->queueNextRecipient($attempt['campaign_id']);

            return;
        }

        $address = trim((string) $user->email);
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $state->failPermanently($this->recipientId, 'InvalidAddress');
            $this->queueNextRecipient($attempt['campaign_id']);

            return;
        }

        try {
            $health->assertReady();
            $rendered = $renderer->renderCampaign($campaign, $user);
            $mailerName = (string) $health->report()['mailer_name'];

            Mail::mailer($mailerName)
                ->to($address)
                ->send(new MailingCampaignMail(
                    campaignSubject: $rendered['subject'],
                    htmlBody: $rendered['html'],
                    textBody: $rendered['text'],
                    replyToAddress: $rendered['reply_to'],
                ));

            $state->succeeded($this->recipientId);
        } catch (Throwable $exception) {
            $backoff = array_values($this->backoff());
            $queueAttempt = max(1, $this->attempts());
            $retryAfter = (int) ($backoff[min($queueAttempt - 1, max(0, count($backoff) - 1))] ?? 60);
            $state->retrying($this->recipientId, $exception, $retryAfter);
            $this->queueNextRecipient($attempt['campaign_id']);

            throw $exception;
        }

        $this->queueNextRecipient($attempt['campaign_id']);
    }

    public function failed(Throwable $exception): void
    {
        app(MailingCampaignDeliveryState::class)->failed($this->recipientId, $exception);
        $recipient = MailingCampaignRecipient::query()->with('campaign')->find($this->recipientId);

        Log::warning('Mailing campaign recipient delivery failed after queue retries.', [
            'campaign_id' => $recipient?->campaign_id,
            'recipient_id' => $this->recipientId,
            'error_type' => class_basename($exception),
        ]);

        if ($recipient?->campaign_id !== null) {
            $this->queueNextRecipient((string) $recipient->campaign_id);
        }
    }

    private function queueNextRecipient(string $campaignId): void
    {
        $campaign = MailingCampaign::query()->find($campaignId);
        if (! $campaign || ! in_array($campaign->status, [MailingCampaignStatus::QUEUED, MailingCampaignStatus::SENDING], true)) {
            return;
        }

        try {
            QueueMailingCampaignRecipients::dispatch((string) $campaign->id);
        } catch (Throwable $exception) {
            Log::warning('Could not queue the next mailing recipient batch; scheduler recovery will retry.', [
                'campaign_id' => $campaignId,
                'error_type' => class_basename($exception),
            ]);
        }
    }
}
