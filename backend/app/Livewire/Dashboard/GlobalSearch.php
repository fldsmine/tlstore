<?php

namespace App\Livewire\Dashboard;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $q = '';

    public function render(): View
    {
        $user = auth()->user();
        $q = trim($this->q);

        $needle = strtolower($q);
        $pages = collect($this->pages())
            ->filter(fn ($p) => $q !== '' && (
                str_contains(strtolower($p['label']), $needle)
                || str_contains(strtolower($p['keywords'] ?? ''), $needle)
            ))
            ->take(7)->values();

        $numbers = collect();
        $transactions = collect();

        if ($q !== '' && $user) {
            $numbers = $user->allocatedNumbers()->active()->get()
                ->filter(fn ($n) => str_contains(strtolower($n->masked.' '.$n->network.' '.$n->suffix), strtolower($q)))
                ->take(5)->values();

            $transactions = $user->transactions()
                ->where(fn ($qq) => $qq->where('description', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('type', 'like', '%'.str_replace(' ', '_', strtolower($q)).'%'))
                ->latest('created_at')->take(5)->get();
        }

        return view('livewire.dashboard.global-search', [
            'pages' => $pages,
            'numbers' => $numbers,
            'transactions' => $transactions,
            'empty' => $q !== '' && $pages->isEmpty() && $numbers->isEmpty() && $transactions->isEmpty(),
        ]);
    }

    private function pages(): array
    {
        $pages = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'ri-home-5-line', 'keywords' => 'home wallet balance overview'],
            ['label' => 'My Website', 'route' => 'my-website', 'icon' => 'ri-global-line', 'keywords' => 'analytics stats performance call minutes call ids my website ads'],
            ['label' => 'Beneficiaries', 'route' => 'beneficiaries', 'icon' => 'ri-phone-line', 'keywords' => 'call contacts numbers'],
            ['label' => 'Make a Call', 'route' => 'call.preview', 'icon' => 'ri-phone-fill', 'keywords' => 'dial ring call now'],
            ['label' => 'Transactions', 'route' => 'transactions', 'icon' => 'ri-exchange-dollar-line', 'keywords' => 'history activity ledger payments'],
            ['label' => 'Earnings', 'route' => 'earnings', 'icon' => 'ri-money-dollar-circle-line', 'keywords' => 'claim call earnings today unclaimed'],
            ['label' => 'Shareholder Portal', 'route' => 'shareholder', 'icon' => 'ri-pie-chart-2-line', 'keywords' => 'earning portal claim call type breakdown profit'],
            ['label' => 'Nextel Cloud', 'route' => 'cloud', 'icon' => 'ri-cloud-line', 'keywords' => 'cloud sponsored calls earn esim virtual line plan renew upgrade bonus promoter shareholder token'],
            ['label' => 'Cloud Inbox', 'route' => 'cloud.inbox', 'icon' => 'ri-mail-line', 'keywords' => 'cloud inbox sponsored messages sms read to earn claim reward'],
            ['label' => 'Special Coupon', 'route' => 'special-coupon', 'icon' => 'ri-gift-2-line', 'keywords' => 'special coupon code activate storage bonus tier cloud contacts voucher gift redeem'],
            ['label' => 'Withdraw', 'route' => 'withdraw', 'icon' => 'ri-bank-card-line', 'keywords' => 'cash out payout bank money'],
            ['label' => 'Shares', 'route' => 'plans', 'icon' => 'ri-add-circle-line', 'keywords' => 'shares buy minutes top up plan boost allowance'],
            ['label' => 'Bill Payments', 'route' => 'vas', 'icon' => 'ri-shopping-bag-line', 'keywords' => 'vas vtu airtime data internet tv cable dstv gotv startimes electricity waec jamb bills recharge topup pay'],
            ['label' => 'Debit Card', 'route' => 'debit-card', 'icon' => 'ri-bank-card-2-line', 'keywords' => 'virtual card atm verve'],
            ['label' => 'Share Ads', 'route' => 'ads', 'icon' => 'ri-advertisement-line', 'keywords' => 'advert advertisement earn'],
            ['label' => 'Rewards', 'route' => 'rewards', 'icon' => 'ri-gift-line', 'keywords' => 'bonus target streak'],
            ['label' => 'Pay Later', 'route' => 'paylater', 'icon' => 'ri-hand-coin-line', 'keywords' => 'credit minutes earn now buy minutes'],
            ['label' => 'Loan', 'route' => 'loan', 'icon' => 'ri-bank-line', 'keywords' => 'borrow cash advance short term loan'],
            ['label' => 'My Team', 'route' => 'invite', 'icon' => 'ri-team-line', 'keywords' => 'invite referral friends downline'],
            ['label' => 'Profile & Settings', 'route' => 'profile', 'icon' => 'ri-account-circle-line', 'keywords' => 'account me'],
            ['label' => 'Edit Profile', 'route' => 'profile.edit', 'icon' => 'ri-user-3-line', 'keywords' => 'name avatar username'],
            ['label' => 'Bank Account', 'route' => 'payment-settings', 'icon' => 'ri-bank-line', 'keywords' => 'payment settings payout account'],
            ['label' => 'Change Password', 'route' => 'change-password', 'icon' => 'ri-lock-2-line', 'keywords' => 'security password'],
            ['label' => 'Change PIN', 'route' => 'change-pin', 'icon' => 'ri-shield-keyhole-line', 'keywords' => 'transaction pin security'],
        ];

        if (auth()->user()?->is_agent) {
            $pages[] = ['label' => 'Vendor Dashboard', 'route' => 'agent.dashboard', 'icon' => 'ri-store-2-line', 'keywords' => 'agent token batch generate sell coupon'];
        }

        return $pages;
    }
}
