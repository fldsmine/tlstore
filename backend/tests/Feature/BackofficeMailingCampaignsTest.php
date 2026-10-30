<?php

use App\Enums\MailingCampaignStatus;
use App\Enums\MailingRecipientStatus;
use App\Jobs\BuildMailingCampaignRecipients;
use App\Jobs\DeliverMailingCampaignRecipient;
use App\Jobs\QueueMailingCampaignRecipients;
use App\Jobs\SendMailingTestEmail;
use App\Livewire\Backoffice\Mailing\ManageMailingCampaigns;
use App\Mail\MailingCampaignMail;
use App\Models\Admin;
use App\Models\MailingCampaign;
use App\Models\MailingCampaignRecipient;
use App\Models\Package;
use App\Models\ShareSubscription;
use App\Models\User;
use App\Services\Mailing\MailingCampaignContent;
use App\Services\Mailing\MailingCampaignDeliveryState;
use App\Services\Mailing\MailingCampaignManager;
use App\Services\Mailing\MailingContentRenderer;
use App\Services\Mailing\MailingDispatchSchedule;
use App\Services\Mailing\MailingConfigurationException;
use App\Services\Mailing\MailingInfrastructureHealth;
use App\Services\Mailing\MailingRecipientAudience;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;

function configureMailingTestInfrastructure(): void
{
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => 'mail.example.test',
        'mail.from.address' => 'campaigns@example.test',
        'queue.default' => 'database',
        'queue.connections.database.driver' => 'database',
        'queue.connections.database.table' => 'jobs',
        'mailing.rate_per_minute' => 60,
    ]);
}

function makeMailingDraft(Admin $admin, array $overrides = []): MailingCampaign
{
    $filters = app(MailingRecipientAudience::class)->campaignFilters([], 'filters');

    return MailingCampaign::query()->create(array_merge([
        'admin_id' => $admin->id,
        'name' => 'Service update',
        'subject' => 'Hello {{first_name}}',
        'html_body' => '<p>Hello {{first_name}}</p>',
        'text_body' => null,
        'reply_to' => null,
        'filters' => $filters,
        'status' => MailingCampaignStatus::DRAFT,
    ], $overrides));
}

function createCurrentMailingShare(User $user): ShareSubscription
{
    return ShareSubscription::query()->create([
        'user_id' => $user->id,
        'package_id' => null,
        'call_type_id' => null,
        'region' => 'nigeria',
        'allocated_minutes' => 60,
        'daily_earning_cap' => 10,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDays(10),
        'status' => 'active',
    ]);
}

beforeEach(function () {
    configureMailingTestInfrastructure();
});

test('mailing campaign page requires an admin and is readable to support admins', function () {
    $this->get(route('backoffice.mailing.index'))->assertRedirect(route('backoffice.login'));

    $support = Admin::factory()->support()->create();
    $this->actingAs($support, 'admin')
        ->get(route('backoffice.mailing.index'))
        ->assertOk()
        ->assertSee('Mailing campaigns');
});

test('support admins can view campaigns but cannot open the composer or mutate one', function () {
    $support = Admin::factory()->support()->create();
    $this->actingAs($support, 'admin');

    Livewire::test(ManageMailingCampaigns::class)
        ->call('openComposer')
        ->assertForbidden();
});

test('recipient query applies real account, package, role, email and service filters', function () {
    $activePackage = Package::factory()->create(['is_active' => true]);
    $inactivePackage = Package::factory()->create(['is_active' => false]);
    $activeMember = User::factory()->create([
        'package_id' => $activePackage->id,
        'status' => 'active',
        'is_agent' => false,
        'email_verified_at' => now(),
    ]);
    $suspendedMember = User::factory()->create([
        'package_id' => $activePackage->id,
        'status' => 'suspended',
        'is_agent' => false,
    ]);
    $inactivePlanAgent = User::factory()->agent()->create([
        'package_id' => $inactivePackage->id,
        'is_agent' => true,
        'is_special' => true,
        'email_verified_at' => null,
    ]);
    $shareOnly = User::factory()->create(['package_id' => null]);
    createCurrentMailingShare($shareOnly);
    $system = User::factory()->create(['username' => User::SYSTEM_USERNAMES[0]]);

    $audience = app(MailingRecipientAudience::class);

    expect($audience->query([
        'status' => 'active',
        'role' => 'member',
        'activation' => 'activated',
        'email_status' => 'verified',
        'package' => 'active',
        'share' => 'inactive',
        'website' => 'inactive',
    ])->pluck('users.id')->all())->toBe([$activeMember->id])
        ->and($audience->query([
            'status' => 'suspended',
            'role' => 'member',
            'package' => 'active',
        ])->pluck('users.id')->all())->toBe([$suspendedMember->id])
        ->and($audience->query([
            'role' => 'special',
            'email_status' => 'unverified',
            'package' => 'inactive',
        ])->pluck('users.id')->all())->toBe([$inactivePlanAgent->id])
        ->and($audience->query(['share' => 'active', 'package' => 'none'])->pluck('users.id')->all())->toBe([$shareOnly->id])
        ->and($audience->query([])->whereKey($system->id)->exists())->toBeFalse();
});

test('selected recipients are deduplicated and intersected with current eligible filters', function () {
    $active = User::factory()->create(['status' => 'active']);
    $suspended = User::factory()->create(['status' => 'suspended']);
    $audience = app(MailingRecipientAudience::class);
    $filters = $audience->campaignFilters(
        ['status' => 'active'],
        'selected',
        [$active->id, $active->id, $suspended->id],
    );

    expect($audience->campaignQuery($filters)->pluck('users.id')->all())->toBe([$active->id])
        ->and($audience->campaignQuery($filters)->count())->toBe(1);
});

test('recipient preview and delivery sanitizer strip executable markup and personalize safely', function () {
    $user = User::factory()->create([
        'first_name' => '<Ada>',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
    ]);

    $content = new MailingCampaignContent(
        subject: 'Hello {{first_name}}',
        html: '<p onclick="alert(1)">Hi {{first_name}}</p><script>alert(1)</script><a href="javascript:alert(1)">link</a>',
        text: '',
    );
    $rendered = app(MailingContentRenderer::class)->render($content, $user);

    expect($rendered['subject'])->toBe('Hello <Ada>')
        ->and($rendered['html'])->toContain('&lt;Ada&gt;')
        ->and($rendered['html'])->not->toContain('onclick')
        ->and($rendered['html'])->not->toContain('<script')
        ->and($rendered['html'])->not->toContain('javascript:')
        ->and($rendered['text'])->toContain('Hi <Ada>');
});

test('drafts store sanitized HTML and the template library can be reused', function () {
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManageMailingCampaigns::class)
        ->set('campaignName', 'Quarterly note')
        ->set('subject', 'Hello {{first_name}}')
        ->set('htmlBody', '<p onclick="bad()">Hello {{first_name}}</p><script>bad()</script>')
        ->call('saveDraft')
        ->assertHasNoErrors();

    $campaign = MailingCampaign::query()->firstOrFail();
    expect($campaign->status)->toBe(MailingCampaignStatus::DRAFT)
        ->and($campaign->html_body)->not->toContain('<script')
        ->and($campaign->html_body)->not->toContain('onclick');

    Livewire::test(ManageMailingCampaigns::class)
        ->set('campaignName', 'Another draft')
        ->set('subject', 'A saved template')
        ->set('htmlBody', '<p>Reusable</p>')
        ->set('templateName', 'Reusable update')
        ->call('saveAsTemplate')
        ->assertHasNoErrors();

    expect(\App\Models\MailingTemplate::query()->where('name', 'Reusable update')->exists())->toBeTrue();
});

test('campaign launch requires a real asynchronous queue and a deliverable mailer', function () {
    $admin = Admin::factory()->regular()->create();
    $user = User::factory()->create();
    $draft = makeMailingDraft($admin);
    $health = app(MailingInfrastructureHealth::class);

    config(['mail.default' => 'log', 'queue.default' => 'sync']);
    expect($health->report()['ready'])->toBeFalse();
    expect(fn () => app(MailingCampaignManager::class)->launch($draft))
        ->toThrow(MailingConfigurationException::class);

    expect($user->email)->not->toBeEmpty();
});

test('launch confirmation queues audience preparation instead of sending in the request', function () {
    Queue::fake();
    Mail::fake();
    $admin = Admin::factory()->regular()->create();
    User::factory()->count(2)->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManageMailingCampaigns::class)
        ->set('campaignName', 'Launch safely')
        ->set('subject', 'Hello {{first_name}}')
        ->set('htmlBody', '<p>Queued only</p>')
        ->call('requestLaunch')
        ->assertSet('showLaunchConfirmation', true)
        ->assertSet('confirmRecipientCount', 2)
        ->call('confirmLaunch')
        ->assertSet('showLaunchConfirmation', false);

    expect(MailingCampaign::query()->firstOrFail()->status)->toBe(MailingCampaignStatus::BUILDING);
    Queue::assertPushedTimes(BuildMailingCampaignRecipients::class, 1);
    Mail::assertNothingOutgoing();
});

test('high-impact campaign actions require server-issued confirmation state', function () {
    Queue::fake();
    $admin = Admin::factory()->regular()->create();
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::QUEUED,
        'recipient_count' => 1,
        'queued_count' => 1,
    ]);
    $this->actingAs($admin, 'admin');

    Livewire::test(ManageMailingCampaigns::class)
        ->set('showActionConfirmation', true)
        ->set('confirmAction', 'cancel')
        ->set('confirmActionCampaignId', $campaign->id)
        ->call('confirmAction');

    expect($campaign->fresh()->status)->toBe(MailingCampaignStatus::QUEUED);
    Queue::assertNothingPushed();
});

test('audience builder snapshots only unique valid email addresses and dispatches a bounded fan-out', function () {
    Queue::fake();
    $admin = Admin::factory()->regular()->create();
    $validOne = User::factory()->create();
    $validTwo = User::factory()->create();
    User::factory()->create(['email' => 'not-an-address']);
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::BUILDING,
    ]);

    (new BuildMailingCampaignRecipients($campaign->id))->handle(app(MailingRecipientAudience::class));

    $campaign->refresh();
    expect($campaign->status)->toBe(MailingCampaignStatus::QUEUED)
        ->and($campaign->recipient_count)->toBe(2)
        ->and($campaign->queued_count)->toBe(2)
        ->and($campaign->recipients()->count())->toBe(2)
        ->and($campaign->recipients()->pluck('user_id')->all())->toContain($validOne->id, $validTwo->id);
    Queue::assertPushedTimes(QueueMailingCampaignRecipients::class, 1);
});

test('recipient dispatch uses a shared rate schedule and bounded in-flight windows', function () {
    Queue::fake();
    Cache::forget('mailing.dispatch.next_slot');
    $admin = Admin::factory()->regular()->create();
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::QUEUED,
        'recipient_count' => 25,
        'queued_count' => 25,
    ]);
    foreach (User::factory()->count(25)->create() as $user) {
        MailingCampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'status' => MailingRecipientStatus::PENDING,
        ]);
    }

    $coordinator = new QueueMailingCampaignRecipients($campaign->id);
    $coordinator->handle(app(MailingDispatchSchedule::class));
    $coordinator->handle(app(MailingDispatchSchedule::class));

    expect($campaign->recipients()->where('status', MailingRecipientStatus::QUEUED->value)->count())->toBe(20)
        ->and($campaign->recipients()->where('status', MailingRecipientStatus::PENDING->value)->count())->toBe(5)
        ->and($campaign->recipients()->whereNotNull('available_at')->count())->toBe(20);
    Queue::assertPushedTimes(DeliverMailingCampaignRecipient::class, 20);
});

test('a delivered recipient is not sent again when a duplicate queue job is handled', function () {
    Mail::fake();
    $admin = Admin::factory()->regular()->create();
    $user = User::factory()->create(['first_name' => 'Nia']);
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::QUEUED,
        'recipient_count' => 1,
        'queued_count' => 1,
    ]);
    $recipient = MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'status' => MailingRecipientStatus::PENDING,
    ]);
    $job = new DeliverMailingCampaignRecipient($recipient->id);

    $job->handle(app(MailingCampaignDeliveryState::class), app(MailingInfrastructureHealth::class), app(MailingContentRenderer::class));
    $job->handle(app(MailingCampaignDeliveryState::class), app(MailingInfrastructureHealth::class), app(MailingContentRenderer::class));

    expect($recipient->fresh()->status)->toBe(MailingRecipientStatus::SENT)
        ->and($campaign->fresh()->sent_count)->toBe(1)
        ->and($campaign->fresh()->queued_count)->toBe(0);
    Mail::assertSentCount(1);
});

test('delivery jobs apply the configured provider throttle and retry backoff', function () {
    $job = new DeliverMailingCampaignRecipient('00000000-0000-4000-8000-000000000001');

    expect($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and($job->backoff())->toBe([60, 300, 900])
        ->and($job->tries)->toBe(0)
        ->and($job->maxExceptions)->toBe(3);
});

test('final queue failure is logged per recipient and moves campaign progress to error state', function () {
    $admin = Admin::factory()->regular()->create();
    $user = User::factory()->create();
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::SENDING,
        'recipient_count' => 1,
        'queued_count' => 0,
        'sending_count' => 1,
    ]);
    $recipient = MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'status' => MailingRecipientStatus::SENDING,
        'attempts' => 3,
    ]);

    app(MailingCampaignDeliveryState::class)->failed($recipient->id, new RuntimeException('transport refused'));

    expect($recipient->fresh()->status)->toBe(MailingRecipientStatus::FAILED)
        ->and($recipient->fresh()->last_error_code)->toBe('RuntimeException')
        ->and($campaign->fresh()->failed_count)->toBe(1)
        ->and($campaign->fresh()->status)->toBe(MailingCampaignStatus::COMPLETED_WITH_ERRORS);
});

test('failed recipients can be safely retried without changing already-sent rows', function () {
    Queue::fake();
    $admin = Admin::factory()->regular()->create();
    $sentUser = User::factory()->create();
    $failedUser = User::factory()->create();
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::COMPLETED_WITH_ERRORS,
        'recipient_count' => 2,
        'queued_count' => 0,
        'sent_count' => 1,
        'failed_count' => 1,
    ]);
    $sent = MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $sentUser->id,
        'status' => MailingRecipientStatus::SENT,
        'attempts' => 1,
        'sent_at' => now(),
    ]);
    $failed = MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $failedUser->id,
        'status' => MailingRecipientStatus::FAILED,
        'attempts' => 3,
        'last_error_code' => 'TransportException',
    ]);

    expect(app(MailingCampaignManager::class)->retryFailed($campaign->id))->toBe(1);

    expect($sent->fresh()->status)->toBe(MailingRecipientStatus::SENT)
        ->and($failed->fresh()->status)->toBe(MailingRecipientStatus::PENDING)
        ->and($campaign->fresh()->status)->toBe(MailingCampaignStatus::QUEUED)
        ->and($campaign->fresh()->queued_count)->toBe(1);
    Queue::assertPushedTimes(QueueMailingCampaignRecipients::class, 1);
});

test('due scheduled campaigns are released by the scheduler through the queue only', function () {
    Queue::fake();
    Mail::fake();
    $admin = Admin::factory()->regular()->create();
    $user = User::factory()->create();
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::SCHEDULED,
        'scheduled_at' => now()->subMinute(),
        'recipient_count' => 1,
        'queued_count' => 1,
    ]);
    MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
        'status' => MailingRecipientStatus::PENDING,
    ]);

    $this->artisan('mailing:dispatch-scheduled')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(MailingCampaignStatus::QUEUED);
    Queue::assertPushedTimes(QueueMailingCampaignRecipients::class, 1);
    Mail::assertNothingOutgoing();
});

test('cancellation stops queued recipients but leaves an in-progress delivery visible', function () {
    $admin = Admin::factory()->regular()->create();
    $queuedUser = User::factory()->create();
    $sendingUser = User::factory()->create();
    $campaign = makeMailingDraft($admin, [
        'status' => MailingCampaignStatus::SENDING,
        'recipient_count' => 2,
        'queued_count' => 1,
        'sending_count' => 1,
    ]);
    $queued = MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $queuedUser->id,
        'status' => MailingRecipientStatus::PENDING,
    ]);
    $sending = MailingCampaignRecipient::query()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $sendingUser->id,
        'status' => MailingRecipientStatus::SENDING,
    ]);

    app(MailingCampaignManager::class)->cancel($campaign->id);

    expect($campaign->fresh()->status)->toBe(MailingCampaignStatus::CANCELLED)
        ->and($campaign->fresh()->queued_count)->toBe(0)
        ->and($campaign->fresh()->cancelled_count)->toBe(1)
        ->and($campaign->fresh()->sending_count)->toBe(1)
        ->and($queued->fresh()->status)->toBe(MailingRecipientStatus::CANCELLED)
        ->and($sending->fresh()->status)->toBe(MailingRecipientStatus::SENDING);
});

test('test-email action queues a protected test job and never sends inline', function () {
    Queue::fake();
    Mail::fake();
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManageMailingCampaigns::class)
        ->set('campaignName', 'Try this')
        ->set('subject', 'Test for {{first_name}}')
        ->set('htmlBody', '<p>It works</p>')
        ->set('testEmail', 'qa@example.test')
        ->call('sendTestEmail')
        ->assertHasNoErrors();

    Queue::assertPushed(SendMailingTestEmail::class, function (SendMailingTestEmail $job): bool {
        return $job->campaignId === MailingCampaign::query()->firstOrFail()->id
            && ! str_contains(serialize($job), 'qa@example.test');
    });
    Mail::assertNothingOutgoing();
});
