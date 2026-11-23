<?php

namespace App\Livewire\Backoffice\Reports;

use App\Enums\LoginChannel;
use App\Models\User;
use App\Models\UserLoginEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Who signed in, day by day.
 *
 * Opens on TODAY — the report the client asked for — with arrows to walk back
 * through earlier days. One row is one sign-in (see UserLoginEvent), so a user who
 * signed in three times appears three times, which is the point: users.last_login_at
 * alone can only ever show the most recent sign-in.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Sign-ins — Backoffice')]
class SignIns extends Component
{
    use WithPagination;

    /**
     * Selected day (Y-m-d). Blank means today.
     *
     * Named `date`, not `day`: Livewire exposes public properties to the view, so a property
     * called `$day` would shadow the Carbon instance render() passes to the template as `$day`.
     */
    #[Url]
    public string $date = '';

    #[Url]
    public string $channel = 'all';

    #[Url]
    public string $search = '';

    public function setChannel(string $channel): void
    {
        $this->channel = in_array($channel, ['all', ...LoginChannel::values()], true) ? $channel : 'all';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Move the report one day back (or forward, never past today). */
    public function shiftDay(int $offset): void
    {
        $target = $this->selectedDay()->addDays($offset);

        $this->date = $target->isFuture() ? today()->toDateString() : $target->toDateString();
        $this->resetPage();
    }

    /** Jump straight to a day (Y-m-d), never past today. */
    public function selectDay(string $day): void
    {
        $parsed = Carbon::parse($day);

        $this->date = $parsed->isFuture() ? today()->toDateString() : $parsed->toDateString();
        $this->resetPage();
    }

    public function today(): void
    {
        $this->date = '';
        $this->resetPage();
    }

    protected function selectedDay(): Carbon
    {
        if (trim($this->date) === '') {
            return today();
        }

        try {
            $parsed = Carbon::parse($this->date)->startOfDay();
        } catch (\Throwable) {
            return today();
        }

        return $parsed->isFuture() ? today() : $parsed;
    }

    /** One day's events, before any filter the admin applies. */
    protected function baseQuery(Carbon $day): Builder
    {
        return UserLoginEvent::query()
            ->with('user.package')
            ->whereDate('created_at', $day->toDateString());
    }

    protected function applySearch(Builder $query): Builder
    {
        return $query->when(trim($this->search) !== '', function (Builder $query): void {
            $term = '%'.trim($this->search).'%';
            $query->whereHas('user', fn (Builder $u) => $u
                ->where('username', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term));
        });
    }

    /** Exactly what the table lists: the day's events under both filters. */
    protected function eventsQuery(Carbon $day): Builder
    {
        return $this->applySearch($this->baseQuery($day))
            ->when($this->channel !== 'all', fn (Builder $q) => $q->where('channel', $this->channel));
    }

    public function render(): View
    {
        $day = $this->selectedDay();

        $events = $this->eventsQuery($day)
            ->latest('created_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        // The headline figures walk the same filters as the rows, so a filtered table can
        // never be summarised by an unfiltered number.
        $total = $this->eventsQuery($day)->count();
        $uniqueUsers = $this->eventsQuery($day)->distinct()->count('user_id');

        // The surface split and its percentages stay day-wide (the same search, every
        // channel): the bars double as the surface picker, so selecting Telegram must not
        // zero every other bar.
        $dayWide = $this->applySearch($this->baseQuery($day));
        $dayTotal = $dayWide->count();
        $byChannel = $this->applySearch($this->baseQuery($day))
            ->selectRaw('channel, count(*) as c')
            ->groupBy('channel')
            ->pluck('c', 'channel');

        // A 7-day sparkline that ENDS on the selected day, so walking back reveals history.
        // It follows the surface filter — that is the trend of what you are looking at.
        $points = [];
        for ($i = 6; $i >= 0; $i--) {
            $cursor = $day->copy()->subDays($i);
            $points[] = [
                'label' => $cursor->format('j M'),
                'value' => (float) $this->eventsQuery($cursor)->count(),
            ];
        }

        // Sign-ins that predate the log (or the migration's deployment) still show for today:
        // the users whose last_login_at lands on this day but who have no recorded event.
        // Their surface was never recorded, so the section is only honest while the filter is
        // "all" (or "web", which is what every pre-log sign-in was).
        $untrackedDay = $day->isSameDay(today()) && in_array($this->channel, ['all', LoginChannel::WEB->value], true);

        $untracked = $untrackedDay
            ? User::query()
                ->whereDate('last_login_at', $day->toDateString())
                ->whereDoesntHave('loginEvents', fn (Builder $q) => $q->whereDate('created_at', $day->toDateString()))
                ->when(trim($this->search) !== '', fn (Builder $q) => $q
                    ->where(fn (Builder $inner) => $inner
                        ->where('username', 'like', '%'.trim($this->search).'%')
                        ->orWhere('email', 'like', '%'.trim($this->search).'%')
                        ->orWhere('first_name', 'like', '%'.trim($this->search).'%')
                        ->orWhere('last_name', 'like', '%'.trim($this->search).'%')))
                ->with('package')
                ->latest('last_login_at')
                ->take(25)
                ->get()
            : collect();

        $firstSignIn = $this->eventsQuery($day)->min('created_at') ?: null;
        $lastSignIn = $this->eventsQuery($day)->max('created_at') ?: null;

        return view('livewire.backoffice.reports.sign-ins', [
            'events' => $events,
            'day' => $day,
            'isToday' => $day->isSameDay(today()),
            'total' => $total,
            'dayTotal' => $dayTotal,
            'uniqueUsers' => $uniqueUsers,
            'byChannel' => collect(LoginChannel::cases())
                ->mapWithKeys(fn (LoginChannel $c): array => [$c->value => (int) ($byChannel[$c->value] ?? 0)])
                ->all(),
            'points' => $points,
            'firstSignIn' => $firstSignIn ? Carbon::parse($firstSignIn) : null,
            'lastSignIn' => $lastSignIn ? Carbon::parse($lastSignIn) : null,
            'untracked' => $untracked,
            'channels' => LoginChannel::cases(),
        ]);
    }
}
