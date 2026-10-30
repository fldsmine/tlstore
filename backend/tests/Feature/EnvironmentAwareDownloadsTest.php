<?php

use App\Livewire\Dashboard\Agent\ExportBatchModal;
use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\DownloadLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/**
 * Downloads have to work in three places the dashboard runs: a normal browser, the Android app's
 * WebView (which hands the URL to the app's native downloader) and the Telegram Mini App (whose
 * downloadFile fetches the URL from the Telegram client, with NO session cookie). That last one is
 * why every download link is a temporary SIGNED url carrying the user it was minted for.
 */
function signedDownload(string $route, array $parameters = [], int $minutes = 10): string
{
    return URL::temporarySignedRoute($route, now()->addMinutes($minutes), $parameters);
}

test('a receipt downloads with a signature and no session', function () {
    $user = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create(['user_id' => $user->id, 'reference' => 'WD-SIGNED01']);

    $response = $this->get(signedDownload('withdrawal.receipt', [
        'u' => $user->id,
        'reference' => $withdrawal->reference,
        'download' => 1,
    ]))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->headers->get('content-disposition'))->toContain($withdrawal->reference);
});

test('a signed receipt link cannot reach somebody else’s receipt', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create(['user_id' => $owner->id, 'reference' => 'WD-OWNER01']);

    // Valid signature, but minted for someone who does not own the receipt.
    $this->get(signedDownload('withdrawal.receipt', ['u' => $stranger->id, 'reference' => $withdrawal->reference]))
        ->assertNotFound();
});

test('receipt links are rejected without a signature or a session', function () {
    $withdrawal = Withdrawal::factory()->create(['reference' => 'WD-GUEST01']);

    $this->get(route('withdrawal.receipt', ['reference' => $withdrawal->reference]))
        ->assertForbidden();

    // An appended query parameter changes the signed string, so it must be rejected.
    $tampered = signedDownload('withdrawal.receipt', ['u' => $withdrawal->user_id, 'reference' => $withdrawal->reference]).'&download=1';

    $this->get($tampered)->assertForbidden();

    // …and an expired one too.
    $expired = signedDownload('withdrawal.receipt', ['u' => $withdrawal->user_id, 'reference' => $withdrawal->reference], -5);

    $this->get($expired)->assertForbidden();
});

test('the owner still downloads their own receipt from a live session, signature or not', function () {
    $user = User::factory()->create();
    $withdrawal = Withdrawal::factory()->create(['user_id' => $user->id, 'reference' => 'WD-SESSION1']);

    $this->actingAs($user)
        ->get(route('withdrawal.receipt', ['reference' => $withdrawal->reference]))
        ->assertOk();

    User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('withdrawal.receipt', ['reference' => $withdrawal->reference]))
        ->assertNotFound();
});

test('an agent token export streams their own codes through a signed link', function () {
    $agent = User::factory()->create(['is_agent' => true]);
    $other = User::factory()->create(['is_agent' => true]);

    Coupon::factory()->create(['agent_id' => $agent->id, 'code' => 'AGENTOWNCODE0001']);
    Coupon::factory()->create(['agent_id' => $other->id, 'code' => 'OTHERAGENTCODE01']);

    $response = $this->get(signedDownload('agent.tokens.export', ['u' => $agent->id]))->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('AGENTOWNCODE0001')
        ->and($csv)->not->toContain('OTHERAGENTCODE01')
        ->and($response->headers->get('content-disposition'))->toContain('nextel-tokens-');
});

test('a token export link cannot be used by a non-agent', function () {
    $member = User::factory()->create(['is_agent' => false]);

    $this->get(signedDownload('agent.tokens.export', ['u' => $member->id]))->assertForbidden();
    $this->get(route('agent.tokens.export'))->assertForbidden();
});

test('a coupon batch exports through a signed link, scoped to the format asked for', function () {
    $agent = User::factory()->create(['is_agent' => true]);
    $batch = CouponBatch::create(['agent_id' => $agent->id, 'type' => 'activation', 'region' => 'nigeria', 'quantity' => 2]);

    Coupon::factory()->create(['agent_id' => $agent->id, 'batch_id' => $batch->id, 'code' => 'BATCHCODEAAA001']);
    Coupon::factory()->create(['agent_id' => $agent->id, 'batch_id' => $batch->id, 'code' => 'BATCHCODEBBB002', 'used_at' => now()]);

    $text = $this->get(signedDownload('agent.batches.export', [
        'u' => $agent->id,
        'batch' => $batch->id,
        'scope' => 'active',
        'format' => 'txt',
    ]))->assertOk();

    expect($text->streamedContent())->toContain('BATCHCODEAAA001')
        ->and($text->streamedContent())->not->toContain('BATCHCODEBBB002')
        ->and($text->headers->get('content-type'))->toContain('text/plain');

    $csv = $this->get(signedDownload('agent.batches.export', [
        'u' => $agent->id,
        'batch' => $batch->id,
        'scope' => 'all',
        'format' => 'csv',
    ]))->assertOk();

    expect($csv->streamedContent())->toContain('BATCHCODEBBB002');
});

test('another agent cannot export a batch through a signature minted for them', function () {
    $owner = User::factory()->create(['is_agent' => true]);
    $stranger = User::factory()->create(['is_agent' => true]);

    $batch = CouponBatch::create(['agent_id' => $owner->id, 'type' => 'activation', 'region' => 'nigeria', 'quantity' => 1]);
    Coupon::factory()->create(['agent_id' => $owner->id, 'batch_id' => $batch->id, 'code' => 'PRIVATECODE00001']);

    $this->get(signedDownload('agent.batches.export', ['u' => $stranger->id, 'batch' => $batch->id]))
        ->assertNotFound();
});

test('minted download links carry the user they belong to and verify', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $url = DownloadLinks::signed('agent.tokens.export', ['ids' => ['abc', 'def']]);

    // The link embeds the signed-in user, and carries its own expiry and signature.
    expect($url)->toContain('u='.$user->id)
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=')
        ->and($url)->toContain('ids');

    expect(Request::create($url, 'GET')->hasValidSignature())->toBeTrue();
});

test('the batch export modal renders a signed download link instead of a Livewire action', function () {
    $agent = User::factory()->create(['is_agent' => true]);
    $batch = CouponBatch::create(['agent_id' => $agent->id, 'type' => 'activation', 'region' => 'nigeria', 'quantity' => 1]);

    $this->actingAs($agent);

    Livewire::test(ExportBatchModal::class)
        ->call('openFor', $batch->id)
        ->assertViewHas('exportUrl', fn (?string $url): bool => is_string($url) && str_contains($url, 'signature=') && str_contains($url, 'u='.$agent->id))
        ->assertViewHas('exportFilename', fn (?string $name): bool => is_string($name) && str_starts_with($name, 'nextel-batch-'))
        ->assertSee('data-nextel-download', escape: false);
});
