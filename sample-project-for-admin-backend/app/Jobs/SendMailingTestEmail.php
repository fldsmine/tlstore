<?php

namespace App\Jobs;

use App\Mail\MailingCampaignMail;
use App\Models\Admin;
use App\Models\MailingCampaign;
use App\Services\Mailing\MailingContentRenderer;
use App\Services\Mailing\MailingInfrastructureHealth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMailingTestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $campaignId,
        public string $adminId,
        string $recipientAddress,
    ) {
        // Failed queue payloads never contain a plaintext test address.
        $this->encryptedRecipientAddress = Crypt::encryptString($recipientAddress);
    }

    private string $encryptedRecipientAddress;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MailingInfrastructureHealth $health, MailingContentRenderer $renderer): void
    {
        $campaign = MailingCampaign::query()->findOrFail($this->campaignId);
        $admin = Admin::query()->find($this->adminId);
        if (! $admin || ! $admin->canWrite()) {
            return;
        }

        $health->assertReady();
        $rendered = $renderer->renderCampaign($campaign, $renderer->sampleUser());
        $mailerName = (string) $health->report()['mailer_name'];
        $address = Crypt::decryptString($this->encryptedRecipientAddress);

        Mail::mailer($mailerName)
            ->to($address)
            ->send(new MailingCampaignMail(
                campaignSubject: $rendered['subject'],
                htmlBody: $rendered['html'],
                textBody: $rendered['text'],
                replyToAddress: $rendered['reply_to'],
            ));
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Mailing campaign test email failed after queue retries.', [
            'campaign_id' => $this->campaignId,
            'admin_id' => $this->adminId,
            'error_type' => class_basename($exception),
        ]);
    }
}
