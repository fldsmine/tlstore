<?php

namespace App\Livewire\Dashboard;

use App\Models\Popup as PopupModel;
use App\Models\PopupView;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The promotional popups that greet a user on the dashboard.
 *
 * Mounted once on the dashboard home, it queues every popup the user is due in a RANDOM order and
 * shows them one at a time: closing one slides the next in behind it. When only one is active that
 * is simply one dialog.
 *
 * A recursive popup is due again on every visit; a one-off is spent the moment it is displayed —
 * PopupView records that, and Popup::scopeUnseenBy filters it out from then on.
 */
class Popup extends Component
{
    /** Popup ids queued for this visit, in random order. */
    public array $queue = [];

    /** Which entry of the queue is on screen. */
    public int $position = 0;

    public bool $open = false;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->queue = PopupModel::query()
            ->active()
            ->unseenBy($user)
            ->inRandomOrder()
            ->pluck('id')
            ->all();

        $this->open = $this->queue !== [];

        if ($this->open) {
            $this->recordShown();
        }
    }

    /** Close the popup on screen — the rest of the queue slides in behind it. */
    public function dismiss(): void
    {
        $user = auth()->user();
        $current = $this->current();

        if ($current && $user) {
            PopupView::recordDismissed($current, $user);
        }

        $this->position++;

        if (! isset($this->queue[$this->position])) {
            $this->open = false;

            return;
        }

        $this->recordShown();
    }

    public function render(): View
    {
        $current = $this->current();

        // Switched off or deleted while the queue was running: stop rather than show a stale card.
        if ($this->open && $current === null) {
            $this->open = false;
        }

        return view('livewire.dashboard.popup', [
            'popup' => $current,
            'queued' => count($this->queue),
            'following' => max(count($this->queue) - $this->position - 1, 0),
        ]);
    }

    protected function current(): ?PopupModel
    {
        if (! $this->open) {
            return null;
        }

        return PopupModel::query()->active()->find($this->queue[$this->position] ?? null);
    }

    /** Remember that the popup on screen has been seen — this is what spends a one-off. */
    protected function recordShown(): void
    {
        $user = auth()->user();
        $current = $this->current();

        if ($user && $current) {
            PopupView::recordShown($current, $user);
        }
    }
}
