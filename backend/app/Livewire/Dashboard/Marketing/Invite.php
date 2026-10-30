<?php

namespace App\Livewire\Dashboard\Marketing;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Invite & Team')]
class Invite extends Component
{
    public ?string $selectedId = null;

    public string $search = '';

    public function showMember(string $id): void
    {
        $this->selectedId = $id;
    }

    public function closeMember(): void
    {
        $this->selectedId = null;
    }

    public function render(): View
    {
        $user = auth()->user();

        $directCount = $user->referrals()->count();
        $indirectCount = User::query()->whereIn('referrer_id', $user->referrals()->select('id'))->count();

        $term = trim($this->search);
        $matches = fn ($query) => $query->when($term !== '', function ($q) use ($term): void {
            $q->where(fn ($w) => $w->where('username', 'like', '%'.$term.'%')
                ->orWhere('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%'));
        });

        $direct = $matches($user->referrals()->with('package'))->latest()->take(50)->get();
        $indirect = $matches(User::query()->with('package')->whereIn('referrer_id', $user->referrals()->select('id')))->latest()->take(50)->get();

        $base = config('settings.referral.base_url') ?: config('app.url');

        $selected = null;
        $level = null;
        $upline = null;
        if ($this->selectedId) {
            $selected = $direct->firstWhere('id', $this->selectedId);
            $level = $selected ? 'Direct Connect' : null;

            if (! $selected) {
                $selected = $indirect->firstWhere('id', $this->selectedId);
                $level = $selected ? 'Indirect Connect' : null;

                if ($selected) {
                    $upline = User::query()->with('package')->whereKey($selected->referrer_id)->first();
                }
            }
        }

        return view('livewire.dashboard.marketing.invite', [
            'user' => $user,
            'code' => $user->username,
            'link' => rtrim((string) $base, '/').'/auth/register?ref='.$user->username,
            'direct' => $direct,
            'indirect' => $indirect,
            'directCount' => $directCount,
            'indirectCount' => $indirectCount,
            'searching' => $term !== '',
            'earned' => (float) $user->referral_bonus_balance,
            'selectedMember' => $selected,
            'selectedLevel' => $level,
            'selectedUpline' => $upline,
        ]);
    }
}
