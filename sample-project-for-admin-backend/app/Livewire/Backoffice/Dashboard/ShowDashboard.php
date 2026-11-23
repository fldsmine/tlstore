<?php

namespace App\Livewire\Backoffice\Dashboard;

use App\Enums\LoanStatus;
use App\Enums\PayLaterStatus;
use App\Enums\WithdrawalStatus;
use App\Models\AdShare;
use App\Models\CloudCall;
use App\Models\Coupon;
use App\Models\Loan;
use App\Models\MinuteCredit;
use App\Models\TelegramIdentity;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Dashboard')]
class ShowDashboard extends Component
{
    public function render(): View
    {
        $users = User::query()->selectRaw('
            count(*) as total,
            sum(case when status = ? then 1 else 0 end) as active,
            sum(case when is_agent then 1 else 0 end) as agents,
            sum(shareholder_balance) as w_shareholder,
            sum(ads_revenue_balance) as w_ads_revenue,
            sum(referral_bonus_balance) as w_referral_bonus,
            sum(reward_balance) as w_reward,
            sum(cloud_call_balance) as w_cloud_call,
            sum(cloud_sms_balance) as w_cloud_sms,
            sum(agent_balance) as w_agent
        ', ['active'])->first();

        $pending = Withdrawal::query()->where('status', WithdrawalStatus::PENDING->value);
        $pendingCount = (clone $pending)->count();
        $pendingAmount = (float) $pending->sum('amount');

        $paidToday = (float) Withdrawal::query()
            ->where('status', WithdrawalStatus::COMPLETED->value)
            ->whereDate('processed_at', today())
            ->sum('amount_paid');

        $perWallet = collect(User::SPENDABLE_WALLETS)
            ->mapWithKeys(fn (string $key): array => [$key => (float) $users->{'w_'.$key}])
            ->all();
        $totalLiability = array_sum($perWallet);
        $withdrawableLiability = collect($perWallet)->sum(
            fn (float $amount, string $key): float => (bool) config("settings.withdrawal.wallets.{$key}.enabled") ? $amount : 0.0
        );

        $withdrawalOpen = collect(array_keys($perWallet))->contains(
            fn (string $key): bool => (bool) config("settings.withdrawal.wallets.{$key}.enabled")
        );

        return view('livewire.backoffice.dashboard.show-dashboard', [
            'usersTotal' => (int) $users->total,
            'usersActive' => (int) $users->active,
            'telegramUsers' => TelegramIdentity::query()->count(),
            'agents' => (int) $users->agents,
            'totalLiability' => (float) $totalLiability,
            'vendorFloat' => (float) $users->w_agent,
            'withdrawableLiability' => (float) $withdrawableLiability,
            'withdrawalOpen' => $withdrawalOpen,
            'pendingCount' => $pendingCount,
            'pendingAmount' => $pendingAmount,
            'paidToday' => $paidToday,
            'couponsTotal' => Coupon::query()->count(),
            'couponsUsed' => Coupon::query()->whereNotNull('user_id')->count(),
            'adShares' => AdShare::query()->count(),
            'adSharesAmount' => (float) AdShare::query()->sum('amount'),
            'loansPending' => Loan::query()->where('status', LoanStatus::PROCESSING->value)->count(),
            'payLaterOverdue' => MinuteCredit::query()
                ->where('status', PayLaterStatus::ACTIVE->value)
                ->whereDate('due_date', '<', today())
                ->count(),
            'cloudActivePlans' => User::query()
                ->whereHas('package', fn ($q) => $q->where('enable_cloud', true))
                ->count(),
            'cloudCallsToday' => CloudCall::query()->completed()->whereDate('ended_at', today())->count(),
            'recentUsers' => User::query()->latest()->take(6)->get(),
            'recentWithdrawals' => Withdrawal::query()->with('user')->latest()->take(6)->get(),
        ]);
    }
}
