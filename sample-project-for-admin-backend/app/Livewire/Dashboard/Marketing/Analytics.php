<?php

namespace App\Livewire\Dashboard\Marketing;

use App\Enums\PaymentProvider;
use App\Models\Ad;
use App\Models\User;
use App\Services\AdService;
use App\Services\CloudCallService;
use App\Services\CloudInboxService;
use App\Services\WebsiteCodeRenewalService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('My Website')]
class Analytics extends Component
{
    public string $renewalCode = '';

    /** Code-mode website renewal: redeem a distributor-sold code to extend the site. */
    public function renewWithCode(): void
    {
        $this->validate(['renewalCode' => ['required', 'string']], ['renewalCode.required' => 'Enter your renewal code.']);

        app(WebsiteCodeRenewalService::class)->redeem(auth()->user(), $this->renewalCode, 'renewalCode');

        // The service extends a separately-locked instance; refresh so this render reflects the new window.
        auth()->user()->refresh();

        $this->renewalCode = '';
        $this->dispatch('toast', message: 'Your website has been renewed.', type: 'success');
    }

    public function share(string $adId): void
    {
        $service = app(AdService::class);

        if (! $service->isEnabled()) {
            $this->dispatch('toast', message: 'Ads aren\'t available right now.', type: 'error');

            return;
        }

        $ad = Ad::query()->active()->whereKey($adId)->first();

        if (! $ad) {
            $this->dispatch('toast', message: 'That ad is no longer available.', type: 'error');

            return;
        }

        $earned = $service->share(auth()->user(), $ad);

        $this->dispatch('toast',
            message: $earned > 0
                ? 'You earned '.money($earned, 'auto').' for sharing '.$ad->name.'.'
                : 'You\'ve already earned from this ad.',
            type: $earned > 0 ? 'success' : 'info',
        );
    }

    public function render(CloudCallService $cloud): View
    {
        $user = auth()->user()->loadMissing('package');

        if (! $user->websiteActive()) {
            return view('livewire.dashboard.marketing.analytics', [
                'websiteActive' => false,
                'renewalMode' => (string) config('settings.website.renewal_mode'),
                'expiredTitle' => (string) config('settings.website.expired_title'),
                'expiredMessage' => (string) config('settings.website.expired_message'),
                'renewalPrice' => (float) config('settings.website.renewal_price'),
                'renewalDays' => (int) config('settings.website.duration_days'),
                'renewalProvider' => PaymentProvider::tryFrom((string) config('settings.payment.renewal_provider'))?->label(),
                'expiredAt' => $user->website_expires_at,
            ]);
        }

        $adService = app(AdService::class);
        $ads = $adService->isEnabled()
            ? $adService->activeAds()->take((int) config('settings.ads.website_slots', 3))
            : collect();

        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $totals = $user->callClaims()->selectRaw('sum(duration_minutes) as minutes, count(*) as calls')->first();
        $totalMinutes = (float) ($totals->minutes ?? 0);
        $totalCalls = (int) ($totals->calls ?? 0);

        $allocatedNumbers = $user->allocatedNumbers()->active()->count();

        // Earnings only — the agent float and VTU balance are funding, not earnings.
        $todayEarnings = (float) $user->transactions()
            ->where('direction', 'credit')
            ->whereIn('wallet', User::SPENDABLE_WALLETS)
            ->whereDate('created_at', today())
            ->sum('amount');

        $cloudBalance = (float) $user->cloud_call_balance + (float) $user->cloud_sms_balance;
        $hasCloud = $user->canAccessCloud() || $cloudBalance > 0;
        $cloudTodayEarned = $hasCloud ? $cloud->dailyEarned($user) : 0.0;

        $byDay = $user->callClaims()
            ->where('claimed_at', '>=', today()->subDays(6)->startOfDay())
            ->selectRaw('date(claimed_at) as d, sum(duration_minutes) as minutes')
            ->groupBy('d')
            ->pluck('minutes', 'd');

        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $chart[] = [
                'label' => $day->format('D'),
                'minutes' => (float) ($byDay[$day->toDateString()] ?? 0),
            ];
        }

        $peak = max(1.0, max(array_column($chart, 'minutes')));
        $niceMax = (float) (ceil($peak / 10) * 10);

        foreach ($chart as &$point) {
            $point['pct'] = (int) round($point['minutes'] / $niceMax * 100);
        }
        unset($point);

        return view('livewire.dashboard.marketing.analytics', [
            'websiteActive' => true,
            'user' => $user,
            'greeting' => $greeting,
            'name' => $user->first_name ?: $user->username,
            'hasPackage' => (bool) $user->package,

            'stats' => [
                ['icon' => 'ri-contacts-book-3-line', 'label' => 'Call IDs', 'value' => number_format($allocatedNumbers), 'usd' => null, 'trend' => null],
                ['icon' => 'ri-time-line', 'label' => 'Total Call Minutes', 'value' => number_format($totalMinutes), 'usd' => null, 'trend' => null],
                ['icon' => 'ri-money-dollar-circle-line', 'label' => "Today's Earnings", 'value' => null, 'usd' => (float) $todayEarnings, 'trend' => $todayEarnings > 0 ? 'Today' : null],
                ['icon' => 'ri-wallet-3-line', 'label' => 'Total Balance', 'value' => null, 'usd' => (float) $user->lifetime_earnings, 'trend' => null],
            ],
            'chart' => $chart,
            'chartMax' => $niceMax,
            'totalCalls' => $totalCalls,
            'walletBalance' => (float) $user->total_balance,
            'cloudBalance' => $cloudBalance,
            'cloudTodayEarned' => $cloudTodayEarned,
            'hasCloud' => $hasCloud,

            'cloudCanCall' => $user->canCloudCall(),
            'cloudUnread' => $hasCloud ? app(CloudInboxService::class)->unreadCount($user) : 0,

            'ads' => $ads,
            'earnedAdIds' => $ads->isNotEmpty() ? app(AdService::class)->earnedCurrentAdIds($user, $ads) : [],
        ]);
    }
}
