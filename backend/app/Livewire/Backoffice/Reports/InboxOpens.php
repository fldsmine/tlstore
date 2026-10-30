<?php

namespace App\Livewire\Backoffice\Reports;

use App\Models\CloudInboxMessage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Users who opened their SMS — the Nextel Cloud Inbox — in the dashboard.
 *
 * "Opened" means the message was actually read in the dashboard: read_at is only stamped when the
 * user opens the message (CloudInboxService::markRead), so a delivered-but-ignored sponsored SMS
 * never counts. Each row aggregates that user's inbox, and the detail page lists the messages.
 *
 * ── Why this page is written the way it is ───────────────────────────────────────────────
 * cloud_inbox_messages is fan-out: one row per recipient per campaign, so it is one of the
 * biggest tables in the app. The first version of this page asked the database for that table's
 * totals *per user*: seven correlated subqueries (withCount ×4, withMin, withMax, withSum) that
 * the ORDER BY forced the database to evaluate for EVERY user before it could apply the LIMIT,
 * plus six further full-table aggregates for the tiles — including two COUNT(DISTINCT user_id)
 * passes. On a large table that is minutes, and a timeout.
 *
 * Now:
 *   - the per-user columns come from ONE grouped aggregate, joined to users once, so the database
 *     scans the table a single time for the page instead of once per column per user;
 *   - the tiles come from two aggregate queries (read-scoped and delivery-scoped) instead of six;
 *   - the tiles are memoised briefly, because they are whole-table figures that nobody needs to
 *     the second, and the table only grows.
 *
 * The visible numbers are unchanged: the per-user columns stay all-time (the range/claim filters
 * choose WHICH users are listed, exactly as before), and the tiles keep their old meanings.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Cloud Inbox opens — Backoffice')]
class InboxOpens extends Component
{
    use WithPagination;

    /** Rows per page. */
    private const PER_PAGE = 15;

    /**
     * How long the headline tiles may be reused, in seconds.
     *
     * They are whole-table aggregates; a minute of staleness is invisible on a report that spans
     * the lifetime of the platform, and it turns the second visit (and every filter click back)
     * into a cache hit. "Refresh" forces a recalculation.
     */
    private const TILE_TTL = 60;

    #[Url]
    public string $search = '';

    /** all | today | 7 | 30 — the window the OPENS are counted in. */
    #[Url]
    public string $range = 'all';

    /** all | claimed | unclaimed — whether the opened messages earned anything. */
    #[Url]
    public string $claim = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['all', 'today', '7', '30'], true) ? $range : 'all';
        $this->resetPage();
    }

    public function setClaim(string $claim): void
    {
        $this->claim = in_array($claim, ['all', 'claimed', 'unclaimed'], true) ? $claim : 'all';
        $this->resetPage();
    }

    /** Recompute the headline tiles now, instead of waiting out the memo. */
    public function refreshTiles(): void
    {
        Cache::forget($this->tilesCacheKey());

        $this->dispatch('toast', message: 'Headline figures refreshed.', type: 'success');
    }

    /** The window's start, or null for "all time". */
    protected function rangeStart(): ?Carbon
    {
        return match ($this->range) {
            'today' => today()->startOfDay(),
            '7' => now()->subDays(7)->startOfDay(),
            '30' => now()->subDays(30)->startOfDay(),
            default => null,
        };
    }

    /**
     * The SQL (plus bindings) for "this message counts as opened in the current view".
     *
     * Kept as a fragment because the same predicate has to appear twice in the list query: in the
     * HAVING that chooses the users, and nowhere else — the per-user totals stay all-time.
     *
     * @param  array<int, mixed>  $bindings  filled in with the values the fragment needs
     */
    protected function qualifyingSql(array &$bindings): string
    {
        $sql = 'read_at is not null';

        if (($start = $this->rangeStart()) !== null) {
            $sql .= ' and read_at >= ?';
            $bindings[] = $start;
        }

        if ($this->claim === 'claimed') {
            $sql .= ' and claimed_at is not null';
        } elseif ($this->claim === 'unclaimed') {
            $sql .= ' and claimed_at is null';
        }

        return $sql;
    }

    /**
     * One row per user, computed in a single grouped pass over cloud_inbox_messages.
     *
     * The window/claim filter lives in the HAVING, so it decides which users are listed while the
     * per-user columns (received / opened / unread / claimed / earned) stay their lifetime totals —
     * which is what the table has always shown.
     */
    protected function listQuery(): Builder
    {
        $bindings = [];
        $qualifying = $this->qualifyingSql($bindings);

        $aggregate = CloudInboxMessage::query()
            ->selectRaw('user_id')
            ->selectRaw('count(*) as received_count')
            ->selectRaw('sum(case when read_at is not null then 1 else 0 end) as opened_count')
            ->selectRaw('sum(case when read_at is null then 1 else 0 end) as unread_count')
            ->selectRaw('sum(case when claimed_at is not null then 1 else 0 end) as claimed_count')
            ->selectRaw('coalesce(sum(case when claimed_at is not null then earn_rate else 0 end), 0) as claimed_earnings')
            ->selectRaw('min(case when read_at is not null then read_at end) as first_opened_at')
            ->selectRaw('max(case when read_at is not null then read_at end) as last_opened_at')
            ->groupBy('user_id')
            ->havingRaw("sum(case when {$qualifying} then 1 else 0 end) > 0", $bindings);

        return User::query()
            ->joinSub($aggregate, 'inbox', 'inbox.user_id', '=', 'users.id')
            ->select('users.*')
            ->addSelect([
                'inbox.received_count',
                'inbox.opened_count',
                'inbox.unread_count',
                'inbox.claimed_count',
                'inbox.claimed_earnings',
                'inbox.first_opened_at',
                'inbox.last_opened_at',
            ])
            ->when(trim($this->search) !== '', fn (Builder $query) => $this->applySearch($query))
            ->with('package')
            ->orderByDesc('inbox.last_opened_at')
            ->orderByDesc('inbox.user_id');
    }

    protected function applySearch(Builder $query): Builder
    {
        $term = '%'.trim($this->search).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('username', 'like', $term)
            ->orWhere('email', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->orWhere('first_name', 'like', $term)
            ->orWhere('last_name', 'like', $term));
    }

    /**
     * How many users match the current view — needed only when a search narrows the list, because
     * without one the count IS the "Users who opened" tile, which is already calculated.
     */
    protected function matchingUserCount(): int
    {
        $bindings = [];
        $qualifying = $this->qualifyingSql($bindings);

        return CloudInboxMessage::query()
            ->whereRaw($qualifying, $bindings)
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->whereIn(
                'user_id',
                $this->applySearch(User::query())->select('id'),
            ))
            ->distinct()
            ->count('user_id');
    }

    /**
     * The four headline tiles, in two queries instead of six.
     *
     * The first is read-scoped and carries openers, opened messages, today's openers, claims and
     * the money earned on them; the second is delivery-scoped (a message counts as delivered when
     * it arrived, whether or not it was ever opened).
     *
     * @return array{openers: int, openersToday: int, openedMessages: int, deliveredMessages: int, claimedMessages: int, earned: float}
     */
    protected function tiles(): array
    {
        return Cache::remember($this->tilesCacheKey(), now()->addSeconds(self::TILE_TTL), function (): array {
            $bindings = [];
            $qualifying = $this->qualifyingSql($bindings);
            $start = $this->rangeStart();

            // Delivery baseline: created in the window, and (only when filtering by it) claimed.
            $delivery = CloudInboxMessage::query()
                ->when($start !== null, fn (Builder $q) => $q->where('created_at', '>=', $start))
                ->when($this->claim === 'claimed', fn (Builder $q) => $q->whereNotNull('claimed_at'))
                ->when($this->claim === 'unclaimed', fn (Builder $q) => $q->whereNull('claimed_at'));

            // Everything read-scoped in one pass. "Opened today" is a conditional distinct count
            // inside the same scan the rest of the tiles already need.
            $todayStart = today()->startOfDay();

            $read = CloudInboxMessage::query()
                ->whereRaw($qualifying, $bindings)
                ->selectRaw('count(*) as opened_messages')
                ->selectRaw('count(distinct user_id) as openers')
                ->selectRaw('count(distinct case when read_at >= ? then user_id end) as openers_today', [$todayStart])
                ->selectRaw('sum(case when claimed_at is not null then 1 else 0 end) as claimed_messages')
                ->selectRaw('coalesce(sum(case when claimed_at is not null then earn_rate else 0 end), 0) as earned')
                ->first();

            return [
                'openers' => (int) $read->openers,
                'openersToday' => (int) $read->openers_today,
                'openedMessages' => (int) $read->opened_messages,
                'claimedMessages' => (int) $read->claimed_messages,
                'earned' => (float) $read->earned,
                'deliveredMessages' => $delivery->count(),
            ];
        });
    }

    protected function tilesCacheKey(): string
    {
        return 'backoffice.inbox-opens.tiles.'.$this->range.'.'.$this->claim;
    }

    public function render(): View
    {
        $tiles = $this->tiles();

        // Without a search the list is exactly "the users who opened" — so the paginator's total
        // is the tile we already have, and the page needs no count query at all.
        $total = trim($this->search) === '' ? $tiles['openers'] : $this->matchingUserCount();

        $page = (int) $this->getPage();

        $users = new LengthAwarePaginator(
            $this->listQuery()->forPage($page, self::PER_PAGE)->get(),
            $total,
            self::PER_PAGE,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );

        $openedMessages = $tiles['openedMessages'];
        $delivered = $tiles['deliveredMessages'];

        // Both rates compare against the whole population, so neither is offered while a claim
        // filter is on (the numerator and denominator would be the filtered slice — 100% or 0%
        // by definition). The tiles fall back to their raw counts instead.
        $ratesMeaningful = $this->claim === 'all';

        return view('livewire.backoffice.reports.inbox-opens', [
            'users' => $users->withQueryString(),
            'openers' => $tiles['openers'],
            'openersToday' => $tiles['openersToday'],
            'openedMessages' => $openedMessages,
            'deliveredMessages' => $delivered,
            'claimedMessages' => $tiles['claimedMessages'],
            'earned' => $tiles['earned'],
            'openRate' => $ratesMeaningful && $delivered > 0 ? (int) round($openedMessages / $delivered * 100) : null,
            'claimRate' => $ratesMeaningful && $openedMessages > 0 ? (int) round($tiles['claimedMessages'] / $openedMessages * 100) : null,
            'rangeLabel' => match ($this->range) {
                'today' => 'today',
                '7' => 'the last 7 days',
                '30' => 'the last 30 days',
                default => 'all time',
            },
        ]);
    }
}
