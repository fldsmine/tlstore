<?php

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Livewire\Backoffice\Reports\InboxOpens;
use App\Livewire\Backoffice\Reports\ShowReports;
use App\Models\Admin;
use App\Models\CloudInboxMessage;
use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The reports that used to time out
|--------------------------------------------------------------------------
|
| Both pages aggregate over append-only tables that only grow. The failures
| they were fixed for were all query SHAPE, not missing hardware:
|
|   - InboxOpens asked for the inbox totals per user with seven correlated
|     subqueries (four withCount, withMin, withMax, withSum) and then sorted by
|     one of them, so the database evaluated all seven for every user before
|     the LIMIT; the tiles added six more whole-table aggregates.
|   - ShowReports ranked users by a correlated withSum/withCount alias, again
|     forcing an evaluation per user before the LIMIT.
|
| Query shape is exactly what a test can hold still, so that is what these
| assert — alongside the figures themselves, which must not change.
|
*/

function reportAdmin(): Admin
{
    return Admin::factory()->regular()->create();
}

function reportSubject(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

/** @return list<string> every SQL statement issued while the callback runs */
function captureSql(Closure $callback): array
{
    // Reading the connection's own query log rather than DB::listen(): the suite runs on an
    // in-memory SQLite database, so reconnecting to attach a listener would throw the schema away.
    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $callback();
    } finally {
        $sql = array_column($connection->getQueryLog(), 'query');
        $connection->disableQueryLog();
    }

    return $sql;
}

// ────────────────────────────────────────────────────────── Cloud Inbox opens

test('the Cloud Inbox report reads the inbox once per page, not once per user', function () {
    $admin = reportAdmin();

    // Two pages' worth of users, each with a few messages: enough that a per-user query shape
    // would be visible in the SQL.
    User::factory()->count(20)->create()->each(function (User $user): void {
        CloudInboxMessage::factory()->count(3)->create(['user_id' => $user->id, 'read_at' => now()]);
        CloudInboxMessage::factory()->create(['user_id' => $user->id, 'read_at' => null]);
    });

    $this->actingAs($admin, 'admin');

    $sql = captureSql(fn () => Livewire::test(InboxOpens::class)->assertSee('Cloud Inbox opens'));

    $listQuery = collect($sql)->first(fn (string $q): bool => str_contains($q, 'from "users"') || str_contains($q, 'from `users`'));

    expect($listQuery)->not->toBeNull('the user list query was never issued');

    // The list is ONE grouped pass over the inbox joined to users. The broken version referenced
    // cloud_inbox_messages seven times here (one subquery per column).
    expect(substr_count($listQuery, 'cloud_inbox_messages'))->toBe(1)
        ->and(strtolower($listQuery))->toContain('group by');

    // And no query on the page correlates the inbox back to users.
    foreach ($sql as $query) {
        expect($query)->not->toContain('where "users"."id" = "cloud_inbox_messages"."user_id"');
    }
});

test('the Cloud Inbox tiles follow the range and claim filters', function () {
    // today(): fixed dates would flake around midnight.
    $opener = reportSubject(['first_name' => 'Ada', 'last_name' => 'Okafor', 'username' => 'adaokafor']);
    $quiet = reportSubject(['first_name' => 'Bola', 'last_name' => 'Ibrahim', 'username' => 'bolaibrahim']);

    // Opened and claimed → counts as an opener, an open, and earnings.
    CloudInboxMessage::factory()->create([
        'user_id' => $opener->id,
        'read_at' => now(),
        'claimed_at' => now(),
        'earn_rate' => 0.25,
    ]);

    // Opened but never claimed.
    CloudInboxMessage::factory()->create([
        'user_id' => $opener->id,
        'read_at' => now(),
        'claimed_at' => null,
        'earn_rate' => 0.25,
    ]);

    // Delivered to someone who never opened it: delivered, not opened.
    CloudInboxMessage::factory()->create(['user_id' => $quiet->id, 'read_at' => null]);

    $this->actingAs(reportAdmin(), 'admin');

    Livewire::test(InboxOpens::class)
        ->assertViewHas('openers', 1)
        ->assertViewHas('openersToday', 1)
        ->assertViewHas('openedMessages', 2)
        ->assertViewHas('deliveredMessages', 3)
        ->assertViewHas('claimedMessages', 1)
        ->assertViewHas('earned', 0.25)
        ->assertViewHas('claimRate', 50)   // 1 of 2 opens claimed
        ->assertViewHas('openRate', 67)    // 2 of 3 delivered opened
        // Claimed-only: the unclaimed open drops out of every read-scoped figure.
        ->call('setClaim', 'claimed')
        ->assertViewHas('openers', 1)
        ->assertViewHas('openedMessages', 1)
        ->assertViewHas('earned', 0.25)
        ->assertViewHas('claimRate', fn ($rate): bool => $rate === null) // rates need the whole population
        ->call('setClaim', 'unclaimed')
        ->assertViewHas('openers', 1)
        ->assertViewHas('openedMessages', 1)
        ->assertViewHas('earned', 0.0);
});

test('a searched Cloud Inbox report still pages correctly', function () {
    $target = reportSubject(['first_name' => 'Ngozi', 'last_name' => 'Eze', 'username' => 'ngozieze']);

    foreach (range(1, 18) as $i) {
        $other = reportSubject(['username' => 'opener'.$i]);
        CloudInboxMessage::factory()->create(['user_id' => $other->id, 'read_at' => now()]);
    }

    CloudInboxMessage::factory()->create(['user_id' => $target->id, 'read_at' => now()]);

    $this->actingAs(reportAdmin(), 'admin');

    // The paginator's total is the filtered count — it cannot lean on the "openers" tile here.
    Livewire::test(InboxOpens::class)
        ->set('search', 'Ngozi')
        ->assertViewHas('users', fn ($users): bool => $users->total() === 1
            && $users->first()?->id === $target->id);
});

// ───────────────────────────────────────────────────────────────── leaderboards

test('the top earner board ranks referral earnings and skips system accounts', function () {
    $big = reportSubject(['username' => 'bigearner']);
    $small = reportSubject(['username' => 'smallearner']);
    $system = reportSubject(['username' => User::SYSTEM_USERNAMES[0]]);

    foreach ([[$big, 9.0], [$small, 2.0], [$system, 50.0]] as [$user, $amount]) {
        Transaction::factory()->create([
            'user_id' => $user->id,
            'type' => TransactionType::REFERRAL_BONUS->value,
            'direction' => 'credit',
            'amount' => $amount,
        ]);
    }

    $this->actingAs(reportAdmin(), 'admin');

    Livewire::test(ShowReports::class)
        ->assertViewHas('topEarners', function (array $earners) use ($big): bool {
            $usernames = array_column($earners, 'username');

            return $usernames[0] === 'bigearner'
                && $usernames === ['bigearner', 'smallearner']
                && $earners[0]['value'] === 9.0
                && ! in_array(User::SYSTEM_USERNAMES[0], $usernames, true)
                && $earners[0]['id'] === $big->id;
        });
});

test('the top agent board counts only redeemed tokens', function () {
    $busy = reportSubject(['username' => 'busyagent', 'is_agent' => true]);
    $idle = reportSubject(['username' => 'idleagent', 'is_agent' => true]);

    foreach (range(1, 3) as $i) {
        Coupon::factory()->create([
            'agent_id' => $busy->id,
            'used_at' => now(),
            'user_id' => reportSubject()->id,
        ]);
    }

    // Sold but never redeemed, and sold by an agent who is not an agent any more.
    Coupon::factory()->create(['agent_id' => $idle->id, 'used_at' => null]);
    Coupon::factory()->create([
        'agent_id' => reportSubject(['username' => 'plainuser'])->id,
        'used_at' => now(),
        'type' => CouponType::ACTIVATION->value,
    ]);

    $this->actingAs(reportAdmin(), 'admin');

    Livewire::test(ShowReports::class)
        ->assertViewHas('topAgents', function (array $agents) use ($busy): bool {
            $usernames = array_column($agents, 'username');

            return $usernames === ['busyagent']
                && $agents[0]['count'] === 3
                && $agents[0]['id'] === $busy->id;
        });
});

test('the top referrer board counts the users each referrer brought in', function () {
    $popular = reportSubject(['username' => 'popular']);
    $occasional = reportSubject(['username' => 'occasional']);

    // Nobody writes a `referrals` row here on purpose: this board has always counted referred
    // USERS (User::referrals() is the self-referential relation), not ledger rows.
    foreach (range(1, 2) as $i) {
        reportSubject(['username' => 'referee'.$i, 'referrer_id' => $popular->id]);
    }

    reportSubject(['username' => 'referee3', 'referrer_id' => $occasional->id]);
    reportSubject(['username' => 'noreferrer']);

    $this->actingAs(reportAdmin(), 'admin');

    Livewire::test(ShowReports::class)
        ->assertViewHas('topReferrers', function (array $referrers) use ($popular): bool {
            $usernames = array_column($referrers, 'username');

            return $usernames === ['popular', 'occasional']
                && $referrers[0]['count'] === 2
                && $referrers[0]['id'] === $popular->id;
        });
});

// ───────────────────────────────────────────────────────────────── the memo

test('the reports page is memoised, and refreshing recomputes it', function () {
    Cache::flush();

    User::factory()->count(3)->create();

    $this->actingAs(reportAdmin(), 'admin');

    $first = Livewire::test(ShowReports::class)->viewData('usersSnapshot');
    $cachedAt = Livewire::test(ShowReports::class)->viewData('generatedAt');

    // A new signup inside the memo window does not move the figures…
    User::factory()->create();

    $second = Livewire::test(ShowReports::class)->viewData('usersSnapshot');

    expect($second[0])->toBe($first[0])
        ->and(Livewire::test(ShowReports::class)->viewData('generatedAt'))->toBe($cachedAt);

    // …until Refresh discards the payload.
    $fresh = Livewire::test(ShowReports::class)->call('hardRefresh')->viewData('usersSnapshot');

    expect($fresh[0])->not->toBe($first[0]);

    Cache::flush();
});
