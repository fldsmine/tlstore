<?php

namespace App\Livewire\Backoffice;

use App\Models\Coupon;
use App\Models\CouponBatch;
use App\Models\TelegramIdentity;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class BackofficeSearch extends Component
{
    public string $q = '';

    public function render(): View
    {
        $q = trim($this->q);
        $needle = strtolower($q);

        $pages = collect($this->pages())
            ->filter(fn ($p) => $q !== '' && (
                str_contains(strtolower($p['label']), $needle)
                || str_contains(strtolower($p['keywords'] ?? ''), $needle)
            ))
            ->take(6)->values();

        $users = collect();
        $telegramUsers = collect();
        $batches = collect();
        $coupons = collect();
        $withdrawals = collect();

        if ($q !== '') {
            $term = '%'.$q.'%';

            $users = User::query()
                ->where(fn (Builder $w) => $w
                    ->where('username', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term))
                ->take(5)->get();

            $batches = CouponBatch::query()
                ->with('agent')
                ->where('reference', 'like', $term)
                ->take(4)->get();

            $coupons = Coupon::query()
                ->where('code', 'like', $term)
                ->take(4)->get();

            $withdrawals = Withdrawal::query()
                ->with('user')
                ->where(fn (Builder $w) => $w
                    ->where('reference', 'like', $term)
                    ->orWhere('meta->account_number', 'like', $term)
                    ->orWhere('meta->account_name', 'like', $term))
                ->latest()->take(4)->get();

            $telegramUsers = TelegramIdentity::query()
                ->with('user')
                ->where(fn (Builder $w) => $w
                    ->where('username', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('username', 'like', $term)
                        ->orWhere('email', 'like', $term)))
                ->take(4)->get();
        }

        return view('livewire.backoffice.search', [
            'pages' => $pages,
            'users' => $users,
            'telegramUsers' => $telegramUsers,
            'batches' => $batches,
            'coupons' => $coupons,
            'withdrawals' => $withdrawals,
            'empty' => $q !== '' && $pages->isEmpty() && $users->isEmpty() && $telegramUsers->isEmpty() && $batches->isEmpty() && $coupons->isEmpty() && $withdrawals->isEmpty(),
        ]);
    }

    private function pages(): array
    {
        $admin = auth('admin')->user();

        $pages = [
            ['label' => 'Dashboard', 'route' => 'backoffice.dashboard', 'icon' => 'ri-dashboard-line', 'keywords' => 'home overview kpis'],
            ['label' => 'Reports', 'route' => 'backoffice.reports.index', 'icon' => 'ri-line-chart-line', 'keywords' => 'analytics charts stats trends'],
            ['label' => 'Users', 'route' => 'backoffice.users.index', 'icon' => 'ri-group-line', 'keywords' => 'members people accounts'],
            ['label' => 'Mini App users', 'route' => 'backoffice.telegram-users.index', 'icon' => 'ri-telegram-line', 'keywords' => 'telegram mini app bot chat message dm identities linked'],
            ['label' => 'Support Tickets', 'route' => 'backoffice.support-tickets.index', 'icon' => 'ri-customer-service-2-line', 'keywords' => 'support contact help ticket inbox reply customer'],
            ['label' => 'Agents', 'route' => 'backoffice.users.index', 'params' => ['type' => 'agents'], 'icon' => 'ri-store-2-line', 'keywords' => 'vendors users'],
            ['label' => 'Referrals', 'route' => 'backoffice.referrals.index', 'icon' => 'ri-share-forward-line', 'keywords' => 'referrer downline'],
            ['label' => 'Withdrawals', 'route' => 'backoffice.withdrawals.index', 'icon' => 'ri-money-dollar-circle-line', 'keywords' => 'payouts cash out approve reject pending processing exported mark as paid'],
            ['label' => 'Payment Sheets', 'route' => 'backoffice.withdrawals.payment-sheets', 'icon' => 'ri-file-list-3-line', 'keywords' => 'bank payout export csv xlsx'],
            ['label' => 'Transactions', 'route' => 'backoffice.transactions.index', 'icon' => 'ri-exchange-dollar-line', 'keywords' => 'ledger history'],
            ['label' => 'Payment requests', 'route' => 'backoffice.payment-requests.index', 'icon' => 'ri-shield-keyhole-line', 'keywords' => 'payment pending failed verify settle reconcile callback gateway paystack korapay flutterwave bachs manual transfer'],
            ['label' => 'Mailing campaigns', 'route' => 'backoffice.mailing.index', 'icon' => 'ri-mail-send-line', 'keywords' => 'mail email newsletter campaign recipients bulk send template draft schedule test retry queue'],
            ['label' => 'Loans', 'route' => 'backoffice.loans.index', 'icon' => 'ri-bank-line', 'keywords' => 'cash loan approve'],
            ['label' => 'Pay Later', 'route' => 'backoffice.pay-later.index', 'icon' => 'ri-hand-coin-line', 'keywords' => 'minutes credit overdue'],
            ['label' => 'Reward Targets', 'route' => 'backoffice.reward-targets.index', 'icon' => 'ri-gift-line', 'keywords' => 'daily target reward'],
            ['label' => 'Call Logs', 'route' => 'backoffice.call-records.index', 'icon' => 'ri-phone-line', 'keywords' => 'call records pool network calls'],
            ['label' => 'Call IDs', 'route' => 'backoffice.call-ids.index', 'icon' => 'ri-cellphone-line', 'keywords' => 'allocated numbers phone lines'],
            ['label' => 'Call Claims', 'route' => 'backoffice.call-claims.index', 'icon' => 'ri-hand-coin-line', 'keywords' => 'call earnings claimed shareholder'],
            ['label' => 'Packages', 'route' => 'backoffice.packages.index', 'icon' => 'ri-vip-crown-line', 'keywords' => 'plans tiers membership'],
            ['label' => 'Call Types', 'route' => 'backoffice.call-types.index', 'icon' => 'ri-phone-line', 'keywords' => 'on net off net rate'],
            ['label' => 'Minute Plans', 'route' => 'backoffice.minute-plans.index', 'icon' => 'ri-add-circle-line', 'keywords' => 'additional minutes top up'],
            ['label' => 'Cloud Storage Plans', 'route' => 'backoffice.cloud.storage-plans.index', 'icon' => 'ri-database-2-line', 'keywords' => 'nextel cloud storage plan contact slots token price'],
            ['label' => 'Sponsor Numbers', 'route' => 'backoffice.cloud.numbers.index', 'icon' => 'ri-phone-line', 'keywords' => 'nextel cloud sponsor contact number earning voice message smartcard broadcast distribute bonus'],
            ['label' => 'Cloud Broadcasts', 'route' => 'backoffice.cloud.broadcasts.index', 'icon' => 'ri-broadcast-line', 'keywords' => 'nextel cloud broadcast incoming call'],
            ['label' => 'Bonus Distributions', 'route' => 'backoffice.cloud.distributions.index', 'icon' => 'ri-gift-line', 'keywords' => 'nextel cloud promoter shareholder bonus distribution savers claim history'],
            ['label' => 'Bill Payments', 'route' => 'backoffice.vas.orders.index', 'icon' => 'ri-shopping-bag-line', 'keywords' => 'vtu vtpass airtime data tv electricity waec jamb orders bill payment vas'],
            ['label' => 'Banks', 'route' => 'backoffice.banks.index', 'icon' => 'ri-building-line', 'keywords' => 'bank code'],
            ['label' => 'Countries', 'route' => 'backoffice.countries.index', 'icon' => 'ri-global-line', 'keywords' => 'country nationality region'],
            ['label' => 'Currencies', 'route' => 'backoffice.currencies.index', 'icon' => 'ri-exchange-dollar-line', 'keywords' => 'currency rate usd exchange symbol'],
            ['label' => 'Token Batches', 'route' => 'backoffice.batches.index', 'icon' => 'ri-stack-line', 'keywords' => 'coupons vouchers'],
            ['label' => 'Coupons', 'route' => 'backoffice.coupons.index', 'icon' => 'ri-coupon-line', 'keywords' => 'tokens codes'],
            ['label' => 'Ads', 'route' => 'backoffice.ads.index', 'icon' => 'ri-advertisement-line', 'keywords' => 'advertisement share earn'],
            ['label' => 'Ad Shares', 'route' => 'backoffice.ads.index', 'params' => ['tab' => 'shares'], 'icon' => 'ri-share-line', 'keywords' => 'ad earnings'],
            ['label' => 'Announcements', 'route' => 'backoffice.announcements.index', 'icon' => 'ri-megaphone-line', 'keywords' => 'notice banner message'],
            ['label' => 'My Account', 'route' => 'backoffice.account.profile', 'icon' => 'ri-user-3-line', 'keywords' => 'profile account me avatar name email'],
            ['label' => 'Change Password', 'route' => 'backoffice.account.password', 'icon' => 'ri-lock-2-line', 'keywords' => 'security password account'],
        ];

        if ($admin?->canWrite()) {
            $pages[] = ['label' => 'Settings', 'route' => 'backoffice.settings.general', 'icon' => 'ri-settings-3-line', 'keywords' => 'config general withdrawal tokens features'];
            $pages[] = ['label' => 'Bill Payment Settings', 'route' => 'backoffice.settings.vtu', 'icon' => 'ri-shopping-bag-line', 'keywords' => 'settings vtu vtpass wallet limits airtime data'];
            $pages[] = ['label' => 'Cloud Settings', 'route' => 'backoffice.settings.cloud', 'icon' => 'ri-cloud-line', 'keywords' => 'settings nextel cloud broadcast online window'];
            $pages[] = ['label' => 'Backups', 'route' => 'backoffice.settings.backup', 'icon' => 'ri-database-2-line', 'keywords' => 'settings backup s3 object storage database restore schedule'];
        }
        if ($admin?->isSuper()) {
            $pages[] = ['label' => 'Administrators', 'route' => 'backoffice.admins.index', 'icon' => 'ri-shield-user-line', 'keywords' => 'admin staff roles'];
        }

        return $pages;
    }
}
