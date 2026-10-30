<?php

namespace App\Livewire\Dashboard\Marketing;

use App\Models\AdShare;
use App\Models\User;
use App\Services\AdService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Ads Revenue')]
class Ads extends Component
{
    use WithPagination;

    private function user(): User
    {
        return auth()->user();
    }

    public function render(): View
    {
        $service = app(AdService::class);
        $user = $this->user();

        return view('livewire.dashboard.marketing.ads', [
            'enabled' => $service->isEnabled(),
            'shares' => AdShare::query()
                ->where('user_id', $user->id)
                ->with('ad')
                ->latest('shared_at')
                ->paginate(10),
            'lifetime' => (float) AdShare::query()->where('user_id', $user->id)->sum('amount'),
            'today' => $service->earnedToday($user),
            'week' => $service->earnedThisWeek($user),
            'month' => $service->earnedThisMonth($user),
        ]);
    }
}
