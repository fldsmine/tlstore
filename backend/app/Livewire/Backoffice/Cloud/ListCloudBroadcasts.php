<?php

namespace App\Livewire\Backoffice\Cloud;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CloudBroadcast;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Broadcasts — Backoffice')]
class ListCloudBroadcasts extends Component
{
    use AuthorizesAdminActions, WithPagination;

    public function render(): View
    {
        return view('livewire.backoffice.cloud.list-cloud-broadcasts', [
            'broadcasts' => CloudBroadcast::query()
                ->withCount([
                    'recipients as delivered_count',
                    // Reference the pivot column explicitly — wherePivot() inside withCount() qualifies
                    // as `pivot.status`, which MySQL rejects ("Unknown column 'pivot'"), though SQLite tolerates it.
                    'recipients as answered_count' => fn ($q) => $q->where('cloud_broadcast_user.status', 'answered'),
                    'recipients as declined_count' => fn ($q) => $q->where('cloud_broadcast_user.status', 'declined'),
                ])
                ->latest()
                ->paginate(20),
        ]);
    }
}
