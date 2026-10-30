<?php

namespace App\Livewire\Backoffice\Reports;

use App\Enums\LoginChannel;
use App\Models\User;
use App\Models\UserLoginEvent;
use App\Support\DeviceInfo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One account's sign-in history — every recorded sign-in, plus the sessions it
 * currently holds open.
 *
 * Reached from the sign-in report (and linked from the user's own page), it is the
 * "see more details" view: each sign-in with its surface, IP and device, and the
 * live sessions still attached to the account.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Sign-in detail — Backoffice')]
class ShowSignIns extends Component
{
    use WithPagination;

    public string $userId;

    #[Url]
    public string $channel = 'all';

    public function mount(User $user): void
    {
        $this->userId = $user->id;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = in_array($channel, ['all', ...LoginChannel::values()], true) ? $channel : 'all';
        $this->resetPage();
    }

    protected function subject(): User
    {
        abort_unless(auth('admin')->check(), 403);

        return User::query()->with('package')->findOrFail($this->userId);
    }

    public function render(): View
    {
        $user = $this->subject();

        // Everything the page reports walks the same surface filter as the table below, so
        // "Telegram" chips never leave an all-surfaces headline above a Telegram-only list.
        $filtered = fn (): Builder => UserLoginEvent::query()
            ->where('user_id', $user->id)
            ->when($this->channel !== 'all', fn (Builder $q) => $q->where('channel', $this->channel));

        $events = $filtered()
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // The surface mix is the exception: it is the unfiltered split of the account's
        // history, and it is what the chips are picked from.
        $byChannel = UserLoginEvent::query()
            ->where('user_id', $user->id)
            ->selectRaw('channel, count(*) as c')
            ->groupBy('channel')
            ->pluck('c', 'channel');

        return view('livewire.backoffice.reports.show-sign-ins', [
            'user' => $user,
            'events' => $events,
            'channels' => LoginChannel::cases(),
            'total' => $filtered()->count(),
            'today' => $filtered()->whereDate('created_at', today()->toDateString())->count(),
            'lastWeek' => $filtered()->where('created_at', '>=', now()->subDays(7))->count(),
            'firstEvent' => $filtered()->min('created_at'),
            'byChannel' => collect(LoginChannel::cases())
                ->mapWithKeys(fn (LoginChannel $c): array => [$c->value => (int) ($byChannel[$c->value] ?? 0)])
                ->all(),
            'distinctIps' => $filtered()->whereNotNull('ip_address')->distinct()->count('ip_address'),
            'sessions' => $this->sessions($user),
        ]);
    }

    /**
     * The account's live sessions (Laravel's database session driver). Not available when
     * sessions are stored elsewhere (array store in tests, redis in some deployments) — the
     * panel then simply says there is nothing to show.
     *
     * @return list<array{id: string, ip: string, device: string, mobile: bool, lastActivity: Carbon|null}>
     */
    protected function sessions(User $user): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        try {
            $rows = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->limit(10)
                ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
        } catch (\Throwable) {
            return [];
        }

        return $rows->map(fn ($row): array => [
            'id' => substr((string) $row->id, 0, 12),
            'ip' => (string) ($row->ip_address ?: '—'),
            'device' => DeviceInfo::describe($row->user_agent),
            'rawUa' => (string) $row->user_agent,
            'mobile' => DeviceInfo::isMobile($row->user_agent),
            'lastActivity' => $row->last_activity ? Carbon::createFromTimestamp((int) $row->last_activity) : null,
        ])->all();
    }
}
