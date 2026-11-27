<?php

namespace Tests\Feature;

use App\Mail\Support\GuestSupportAcknowledgement;
use App\Mail\Support\GuestSupportInboxNotification;
use App\Mail\Support\GuestSupportReply;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The public contact form: `POST /api/v1/guest-support` (used by the mobile app's
 * landing page) and the storefront `/contact` page.
 *
 * Guest tickets are deliberately not attached to accounts, replies leave by
 * email, and spam protections (honeypot, per-IP/per-address throttle, duplicate
 * submissions) must not open tickets.
 */
class GuestSupportContactTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_EMAIL = 'ada@example.com';

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ada Lovelace',
            'email' => self::GUEST_EMAIL,
            'subject' => 'Cannot withdraw my wallet balance',
            'message' => 'I tried twice today and the withdrawal stayed pending.',
        ], $overrides);
    }

    public function test_guest_submission_is_stored_and_acknowledged(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/guest-support', $this->payload(['email' => 'Ada@Example.com']));

        $response->assertCreated()
            ->assertJsonPath('data.email', self::GUEST_EMAIL)
            ->assertJsonPath('data.status', 'open');

        $number = (string) $response->json('data.ticket_number');
        $this->assertStringStartsWith('TKT-', $number);

        $ticket = SupportTicket::query()->where('ticket_number', $number)->firstOrFail();
        $this->assertNull($ticket->user_id, 'A public submission must never be attached to an account.');
        $this->assertTrue($ticket->isGuest());
        $this->assertSame(SupportTicket::SOURCE_PUBLIC_FORM, $ticket->source);
        $this->assertSame(self::GUEST_EMAIL, $ticket->guest_email);
        $this->assertSame('Ada Lovelace', $ticket->guest_name);

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'guest',
            'message' => 'I tried twice today and the withdrawal stayed pending.',
        ]);

        Mail::assertSent(GuestSupportAcknowledgement::class, fn (GuestSupportAcknowledgement $mail): bool => $mail->hasTo(self::GUEST_EMAIL));
        Mail::assertSent(GuestSupportInboxNotification::class, fn (GuestSupportInboxNotification $mail): bool => $mail->hasTo((string) config('trustlens.support_email')));
    }

    public function test_a_registered_email_does_not_leak_into_that_users_ticket_list(): void
    {
        Mail::fake();

        $user = $this->user(['email' => self::GUEST_EMAIL]);

        $this->postJson('/api/v1/guest-support', $this->payload())->assertCreated();

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertNull($ticket->user_id);
        $this->assertSame(0, SupportTicket::query()->where('user_id', $user->id)->count());

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/support-tickets')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_incomplete_submissions_are_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/guest-support', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);

        $this->postJson('/api/v1/guest-support', $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/guest-support', $this->payload(['message' => 'help']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->assertDatabaseCount('support_tickets', 0);
        Mail::assertNothingSent();
    }

    public function test_honeypot_submissions_are_discarded_quietly(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/guest-support', $this->payload(['website' => 'https://spam.example']))
            ->assertCreated()
            ->assertJsonPath('data', null);

        $this->assertDatabaseCount('support_tickets', 0);
        Mail::assertNothingSent();
    }

    public function test_a_double_submit_reuses_the_ticket_that_was_just_created(): void
    {
        Mail::fake();

        $first = $this->postJson('/api/v1/guest-support', $this->payload())->assertCreated()->json('data.ticket_number');
        $second = $this->postJson('/api/v1/guest-support', $this->payload())->assertCreated()->json('data.ticket_number');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('support_ticket_messages', 1);
    }

    public function test_public_submissions_are_rate_limited_per_address(): void
    {
        Mail::fake();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/guest-support', $this->payload([
                'subject' => 'Question number '.$i.' about my withdrawal',
                'email' => 'guest-'.$i.'@example.com',
            ]))->assertCreated();
        }

        $this->postJson('/api/v1/guest-support', $this->payload([
            'subject' => 'Question number 5 about my withdrawal',
            'email' => 'guest-5@example.com',
        ]))->assertStatus(429);

        $this->assertDatabaseCount('support_tickets', 4);
    }

    public function test_the_storefront_contact_page_accepts_a_submission(): void
    {
        Mail::fake();

        $this->get('/contact')
            ->assertOk()
            ->assertSee('Contact support')
            ->assertSee('Send message');

        $this->post('/contact', $this->payload())
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('contact_status');

        $this->get('/contact')
            ->assertOk()
            ->assertSee('your reference is', false);

        $this->assertDatabaseCount('support_tickets', 1);
        Mail::assertSent(GuestSupportAcknowledgement::class);
    }

    public function test_the_storefront_contact_page_shows_validation_errors(): void
    {
        $this->from('/contact')
            ->post('/contact', $this->payload(['email' => 'nope']))
            ->assertRedirect('/contact')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('support_tickets', 0);
    }

    public function test_administrators_see_guest_tickets_and_their_reply_is_emailed(): void
    {
        Mail::fake();

        $number = $this->postJson('/api/v1/guest-support', $this->payload())->assertCreated()->json('data.ticket_number');
        $ticket = SupportTicket::query()->where('ticket_number', $number)->firstOrFail();
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.index'))
            ->assertOk()
            ->assertSee($number)
            ->assertSee('Ada Lovelace')
            ->assertSee(self::GUEST_EMAIL)
            ->assertSee('Guest form');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.index', ['q' => self::GUEST_EMAIL]))
            ->assertOk()
            ->assertSee($number);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.support-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Public contact form (no account)')
            ->assertSee('Reply by email');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.reply', $ticket), ['message' => 'We have released the funds — sorry for the wait.'])
            ->assertRedirect(route('admin.support-tickets.show', $ticket))
            ->assertSessionHas('success');

        $this->assertSame('waiting_on_user', $ticket->fresh()->status);
        Mail::assertSent(GuestSupportReply::class, fn (GuestSupportReply $mail): bool => $mail->hasTo(self::GUEST_EMAIL));

        // A guest has no account inbox to notify.
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_admin_api_lists_and_replies_to_guest_tickets(): void
    {
        Mail::fake();

        $number = $this->postJson('/api/v1/guest-support', $this->payload())->assertCreated()->json('data.ticket_number');
        $ticket = SupportTicket::query()->where('ticket_number', $number)->firstOrFail();
        $admin = $this->admin('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/support-tickets?search='.urlencode(self::GUEST_EMAIL))
            ->assertOk()
            ->assertJsonPath('data.data.0.ticket_number', $number)
            ->assertJsonPath('data.data.0.guest.email', self::GUEST_EMAIL)
            ->assertJsonPath('data.data.0.source', SupportTicket::SOURCE_PUBLIC_FORM);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/support-tickets/'.$ticket->id.'/messages', ['message' => 'Thanks for the detail — we are on it.'])
            ->assertOk()
            ->assertJsonPath('data.messages.1.sender_type', 'admin');

        Mail::assertSent(GuestSupportReply::class, fn (GuestSupportReply $mail): bool => $mail->hasTo(self::GUEST_EMAIL));
    }

    public function test_signed_in_tickets_still_notify_the_user(): void
    {
        Mail::fake();

        $user = $this->user();
        $admin = $this->admin('super_admin');

        $ticketId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/support-tickets', [
                'subject' => 'Where is my reward?',
                'category' => 'payments',
                'message' => 'My review reward is still pending after two days.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.support-tickets.reply', $ticketId), ['message' => 'The reward was credited just now.'])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'support_ticket_reply',
        ]);
        Mail::assertNotSent(GuestSupportReply::class);
    }

    public function test_support_emails_render(): void
    {
        $data = [
            'ticket_number' => 'TKT-261127-ABC123',
            'subject' => 'Cannot withdraw & <script>alert(1)</script>',
            'ticket_message' => "Line one\nLine two",
            'guest_name' => 'Ada',
            'guest_email' => self::GUEST_EMAIL,
            'support_email' => 'support@tlstore.test',
            'app_name' => 'TLStore',
            'from_address' => 'no-reply@tlstore.test',
            'from_name' => 'TLStore',
            'admin_url' => 'https://tlstore.test/admin/support-tickets/1',
        ];

        $acknowledgement = (new GuestSupportAcknowledgement($data))->render();
        $this->assertStringContainsString('TKT-261127-ABC123', $acknowledgement);
        $this->assertStringContainsString('Ada', $acknowledgement);

        $inbox = (new GuestSupportInboxNotification($data))->render();
        $this->assertStringContainsString(self::GUEST_EMAIL, $inbox);
        $this->assertStringContainsString('&lt;script&gt;', $inbox, 'User input must be escaped in emails.');
        $this->assertStringNotContainsString('<script>', $inbox);

        $reply = (new GuestSupportReply($data))->render();
        $this->assertStringContainsString('Line two', $reply);

        // A subject typed into the form must not be able to inject mail headers.
        $injected = (new GuestSupportInboxNotification($data + ['subject' => "Evil\r\nBcc: victim@example.com"]))->envelope();
        $this->assertStringNotContainsString("\n", (string) $injected->subject);
        $this->assertStringNotContainsString("\r", (string) $injected->subject);
    }

    public function test_a_broken_mailer_does_not_lose_the_submission(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', '127.0.0.1');
        config()->set('mail.mailers.smtp.port', 1);

        $this->postJson('/api/v1/guest-support', $this->payload())->assertCreated();

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertSame(1, DB::table('support_ticket_messages')->count());
    }

    /** @param  array<string, mixed>  $overrides */
    private function user(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'full_name' => 'Registered User',
            'username' => 'user-'.Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'preferred_currency' => 'NGN',
            'wallet_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ], $overrides));
    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'full_name' => ucfirst($role).' Admin',
            'email' => $role.'-'.Str::lower(Str::random(6)).'@tlstore.test',
            'password' => 'StrongPassword!2345',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
