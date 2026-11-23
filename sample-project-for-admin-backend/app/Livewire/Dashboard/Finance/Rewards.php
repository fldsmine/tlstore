<?php

namespace App\Livewire\Dashboard\Finance;

use App\Enums\RewardTargetStatus;
use App\Models\RewardTarget;
use App\Models\User;
use App\Services\TargetRewardService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Rewards')]
class Rewards extends Component
{
    public string $customTarget = '';

    private function service(): TargetRewardService
    {
        return app(TargetRewardService::class);
    }

    private function user(): User
    {
        return auth()->user();
    }

    public function updatedCustomTarget(string $value): void
    {
        $this->customTarget = preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    public function setTarget(float $amount): void
    {
        $svc = $this->service();

        if (! $svc->isEnabled()) {
            $this->dispatch('toast', message: 'Rewards aren’t available right now.', type: 'error');

            return;
        }

        if ($svc->todayTarget($this->user())) {
            $this->dispatch('toast', message: 'You’ve already set today’s target — it can’t be changed.', type: 'info');

            return;
        }

        $min = $svc->minTarget();
        $max = $svc->maxTarget();

        if ($amount <= 0) {
            $this->dispatch('toast', message: 'Enter a valid target amount.', type: 'warning');

            return;
        }

        if ($amount < $min) {
            $this->dispatch('toast', message: 'The minimum daily target is '.money($min).'.', type: 'warning');

            return;
        }

        if ($max > 0 && $amount > $max) {
            $this->dispatch('toast', message: 'The maximum daily target is '.money($max).'.', type: 'warning');

            return;
        }

        $svc->setTarget($this->user(), $amount);
        $this->customTarget = '';
        $this->dispatch('toast', message: 'Daily target set to '.money($amount).'.', type: 'success');
    }

    public function saveCustom(): void
    {
        $amount = (float) ($this->customTarget ?: 0);

        if ($amount <= 0) {
            $this->dispatch('toast', message: 'Enter a target amount.', type: 'warning');

            return;
        }

        $this->setTarget($amount);
    }

    public function claim(string $id): void
    {
        $target = $this->user()->rewardTargets()->whereKey($id)->first();

        if ($target && $this->service()->claim($target)) {
            $this->dispatch('toast', message: money($target->reward_amount).' reward claimed.', type: 'success');

            return;
        }

        $this->dispatch('toast', message: 'That reward can’t be claimed.', type: 'error');
    }

    public function claimAll(): void
    {
        $total = $this->service()->claimAll($this->user());

        $this->dispatch('toast',
            message: $total > 0 ? money($total).' in rewards claimed.' : 'No rewards to claim.',
            type: $total > 0 ? 'success' : 'info',
        );
    }

    public function render(): View
    {
        $svc = $this->service();
        $user = $this->user();

        $svc->evaluate($user);

        $today = $svc->todayTarget($user);
        $targetAmount = $today ? (float) $today->target_amount : 0.0;
        $progress = $today ? min($svc->progress($user, $today), $targetAmount ?: INF) : 0.0;
        $pct = $targetAmount > 0 ? (int) min(100, round($progress / $targetAmount * 100)) : 0;
        $hit = $today && in_array($today->status, [RewardTargetStatus::ACHIEVED, RewardTargetStatus::CLAIMED], true);

        $min = $svc->minTarget();
        $max = $svc->maxTarget();
        $presets = collect([50, 100, 200])
            ->filter(fn (int $p): bool => $p >= $min && ($max <= 0 || $p <= $max))
            ->values()
            ->all();

        $unclaimed = $user->rewardTargets()->unclaimed()->orderByDesc('date')->get();

        $history = $user->rewardTargets()
            ->where(fn ($q) => $q
                ->whereIn('status', [RewardTargetStatus::CLAIMED, RewardTargetStatus::MISSED])
                ->orWhere(fn ($q2) => $q2->where('status', RewardTargetStatus::ACTIVE)->whereDate('date', '<', today()))
            )
            ->orderByDesc('date')
            ->take(30)
            ->get()
            ->map(fn (RewardTarget $t) => [
                'amount' => (float) $t->reward_amount,
                'target' => (float) $t->target_amount,
                'date' => $t->date->format('D, M j'),
                'claimed' => $t->status === RewardTargetStatus::CLAIMED,
            ]);

        return view('livewire.dashboard.finance.rewards', [
            'enabled' => $svc->isEnabled(),
            'percent' => $svc->rewardPercent(),
            'minTarget' => $min,
            'maxTarget' => $max,
            'presets' => $presets,
            'hasTarget' => (bool) $today,
            'targetSet' => (bool) $today,
            'locked' => $hit,
            'targetAmount' => $targetAmount,
            'progress' => $progress,
            'pct' => $pct,
            'remaining' => max(0, $targetAmount - $progress),
            'lifetimeEarned' => $svc->lifetimeEarned($user),
            'unclaimedTotal' => (float) $unclaimed->sum('reward_amount'),
            'streak' => $svc->streak($user),
            'unclaimed' => $unclaimed,
            'history' => $history,
        ]);
    }
}
