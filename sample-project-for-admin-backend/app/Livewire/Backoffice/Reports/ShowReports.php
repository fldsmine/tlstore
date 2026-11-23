<?php

namespace App\Livewire\Backoffice\Reports;

use App\Enums\CouponType;
use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Enums\VtuStatus;
use App\Enums\WithdrawalStatus;
use App\Models\AdShare;
use App\Models\CallType;
use App\Models\CloudBonusClaim;
use App\Models\CloudBroadcast;
use App\Models\CloudCall;
use App\Models\CloudDistribution;
use App\Models\CloudInboxMessage;
use App\Models\CloudMessage;
use App\Models\CloudStoragePlan;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\DebitCard;
use App\Models\Loan;
use App\Models\MinuteTopUp;
use App\Models\Package;
use App\Models\Referral;
use App\Models\RewardTarget;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VtuOrder;
use App\Models\Withdrawal;
use App\Services\ReportRollupService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Reports — Backoffice')]
class ShowReports extends Component
{
    /**
     * How long a built payload may be reused, in seconds.
     *
     * The page answers with whole-table totals; a minute of staleness is invisible on it, and the
     * memo is what keeps the page off the big tables when an admin clicks through the window
     * chips. "Refresh" recomputes immediately.
     */
    private const PAYLOAD_TTL = 60;

    #[Url]
    public int $days = 0;

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [0, 7, 30, 90, 365], true) ? $days : 0;
    }

    /**
     * Rebuild the report from the database, discarding the memoised payload.
     *
     * The build itself reads pre-aggregated history and computes today live, so this really does
     * produce current figures — it just skips the short payload cache.
     */
    public function hardRefresh(): void
    {
        Cache::forget($this->payloadCacheKey());

        $this->dispatch('toast', message: 'Report refreshed with the latest figures.', type: 'success');
    }

    protected function windowStart(): CarbonInterface
    {
        if ($this->days === 0) {
            $earliest = User::query()->min('created_at');

            return $earliest ? Carbon::parse($earliest)->startOfDay() : today()->startOfDay();
        }

        return now()->subDays($this->days - 1)->startOfDay();
    }

    protected function series(Builder $query, string $dateColumn, CarbonInterface $start, string $valueExpr): array
    {
        $rows = $query
            ->where($dateColumn, '>=', $start)
            ->selectRaw("date({$dateColumn}) as d, {$valueExpr} as v")
            ->groupBy('d')
            ->pluck('v', 'd');

        $series = [];

        if ($start->diffInDays(today()) > 92) {
            $byMonth = [];
            foreach ($rows as $date => $value) {
                $key = substr((string) $date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $value;
            }

            for ($month = $start->copy()->startOfMonth(); $month->lte(today()); $month->addMonth()) {
                $series[] = ['label' => $month->format('M y'), 'value' => (float) ($byMonth[$month->format('Y-m')] ?? 0)];
            }

            return $series;
        }

        for ($day = $start->copy()->startOfDay(); $day->lte(today()); $day->addDay()) {
            $series[] = ['label' => $day->format('j M'), 'value' => (float) ($rows[$day->format('Y-m-d')] ?? 0)];
        }

        return $series;
    }

    public function render(ReportRollupService $rollups): View
    {
        // Heavy time-windowed metrics (transactions / call_claims / signups) are read from the
        // report_daily_stats rollup — immutable past days precomputed, today computed live — so the
        // build is fast AND real-time. What is left is a few dozen window counts over the big
        // tables, which the payload memo hides on repeat views: this is a report, its figures are
        // whole-table totals, and nobody needs them recomputed on every click. "Refresh" bypasses
        // the memo (hardRefresh). The payload is plain arrays precisely so it can be cached.
        $payload = Cache::remember(
            $this->payloadCacheKey(),
            now()->addSeconds(self::PAYLOAD_TTL),
            fn (): array => $this->buildReport($rollups),
        );

        return view('livewire.backoffice.reports.show-reports', $payload);
    }

    protected function payloadCacheKey(): string
    {
        return 'backoffice.reports.payload.'.$this->days;
    }

    /**
     * Turn a [Y-m-d => value] daily map into the chart series (day buckets, or month buckets past 92d),
     * mirroring series() but reading from the rollup instead of a live query.
     *
     * @param  array<string, float>  $daily
     * @return list<array{label: string, value: float}>
     */
    protected function seriesFromDaily(array $daily, CarbonInterface $start): array
    {
        $series = [];

        if ($start->diffInDays(today()) > 92) {
            $byMonth = [];
            foreach ($daily as $date => $value) {
                $key = substr((string) $date, 0, 7);
                $byMonth[$key] = ($byMonth[$key] ?? 0) + (float) $value;
            }

            for ($month = $start->copy()->startOfMonth(); $month->lte(today()); $month->addMonth()) {
                $series[] = ['label' => $month->format('M y'), 'value' => (float) ($byMonth[$month->format('Y-m')] ?? 0)];
            }

            return $series;
        }

        for ($day = $start->copy()->startOfDay(); $day->lte(today()); $day->addDay()) {
            $series[] = ['label' => $day->format('j M'), 'value' => (float) ($daily[$day->format('Y-m-d')] ?? 0)];
        }

        return $series;
    }

    /**
     * Compute the full report payload for the current window.
     *
     * @return array<string, mixed>
     */
    public function buildReport(ReportRollupService $rollups): array
    {
        $start = $this->windowStart();

        $signups = $this->seriesFromDaily($rollups->dailyTotals('signups', $start, 'cnt'), $start);
        $earnings = $this->seriesFromDaily($rollups->dailyTotals('earnings_by_type', $start, 'amt'), $start);
        $payouts = $this->series(Withdrawal::query()->where('status', WithdrawalStatus::COMPLETED->value), 'processed_at', $start, 'sum(amount_paid)');
        $adShareSeries = $this->series(AdShare::query(), 'shared_at', $start, 'count(*)');

        // Users snapshot AND withdrawal eligibility in a single pass over `users`. They were two
        // queries (two full scans, one of them with a CASE pair per wallet); the wallets are
        // ~8 columns, so folding the eligible-balance roll-up into the snapshot query costs
        // nothing and removes a scan.
        $eligSelects = [];
        $eligBindings = [];
        foreach (User::WALLET_COLUMNS as $walletKey => $column) {
            $min = (float) config("settings.withdrawal.wallets.{$walletKey}.min", 0);
            $eligSelects[] = "sum(case when {$column} >= ? then 1 else 0 end) as cnt_{$walletKey}";
            $eligSelects[] = "sum(case when {$column} >= ? then {$column} else 0 end) as sum_{$walletKey}";
            $eligBindings[] = $min;
            $eligBindings[] = $min;
        }

        $u = User::query()
            ->selectRaw('
                count(*) as total,
                sum(case when status = \'active\' then 1 else 0 end) as active,
                sum(case when status = \'suspended\' then 1 else 0 end) as suspended,
                sum(case when is_agent then 1 else 0 end) as agents,
                sum(case when package_id is not null then 1 else 0 end) as activated,
                sum(shareholder_balance) as w_shareholder,
                sum(ads_revenue_balance) as w_ads,
                sum(referral_bonus_balance) as w_referral,
                sum(reward_balance) as w_reward,
                sum(agent_balance) as w_agent,
                sum(cloud_call_balance) as w_cloud_call,
                sum(cloud_sms_balance) as w_cloud_sms,
                sum(bills_balance) as w_bills
            ')
            ->selectRaw(implode(', ', $eligSelects), $eligBindings)
            ->first();

        $ln = Loan::query()->selectRaw('
            sum(case when status = ? then 1 else 0 end) as processing_c, sum(case when status = ? then total_repayment else 0 end) as processing_a,
            sum(case when status = ? then 1 else 0 end) as approved_c, sum(case when status = ? then total_repayment else 0 end) as approved_a,
            sum(case when status = ? then 1 else 0 end) as paid_c, sum(case when status = ? then total_repayment else 0 end) as paid_a,
            sum(case when status = ? then 1 else 0 end) as rejected_c, sum(case when status = ? then total_repayment else 0 end) as rejected_a
        ', [
            LoanStatus::PROCESSING->value, LoanStatus::PROCESSING->value,
            LoanStatus::APPROVED->value, LoanStatus::APPROVED->value,
            LoanStatus::PAID->value, LoanStatus::PAID->value,
            LoanStatus::REJECTED->value, LoanStatus::REJECTED->value,
        ])->first();

        $wd = Withdrawal::query()->selectRaw('
            sum(case when status = ? then 1 else 0 end) as pending_c, sum(case when status = ? then amount else 0 end) as pending_a,
            sum(case when status = ? then 1 else 0 end) as processing_c, sum(case when status = ? then amount else 0 end) as processing_a,
            sum(case when status = ? then 1 else 0 end) as completed_c, sum(case when status = ? then amount_paid else 0 end) as completed_a,
            sum(case when status = ? then 1 else 0 end) as rejected_c, sum(case when status = ? then amount else 0 end) as rejected_a
        ', [
            WithdrawalStatus::PENDING->value, WithdrawalStatus::PENDING->value,
            WithdrawalStatus::PROCESSING->value, WithdrawalStatus::PROCESSING->value,
            WithdrawalStatus::COMPLETED->value, WithdrawalStatus::COMPLETED->value,
            WithdrawalStatus::REJECTED->value, WithdrawalStatus::REJECTED->value,
        ])->first();

        $elig = $u;

        $withdrawalEligible = [];
        foreach (Arr::only(User::WALLET_LABELS, User::SPENDABLE_WALLETS) as $walletKey => $label) {
            $withdrawalEligible[] = [
                'label' => $label,
                'min' => (float) config("settings.withdrawal.wallets.{$walletKey}.min", 0),
                'enabled' => (bool) config("settings.withdrawal.wallets.{$walletKey}.enabled", true),
                'count' => (int) $elig->{"cnt_{$walletKey}"},
                'total' => (float) $elig->{"sum_{$walletKey}"},
            ];
        }

        $totalUsers = (int) $u->total;

        // Users per package in one grouped pass, instead of a correlated count per package plus a
        // separate "no package" scan.
        $byPackage = User::query()
            ->selectRaw('package_id, count(*) as c')
            ->groupBy('package_id')
            ->pluck('c', 'package_id');

        $packageRows = Package::query()->ordered()->get(['id', 'name'])
            ->map(fn (Package $p): array => ['name' => $p->name, 'count' => (int) ($byPackage[$p->id] ?? 0)])
            ->push(['name' => 'No package', 'count' => (int) ($byPackage[null] ?? 0)])
            ->map(fn (array $r): array => $r + ['pct' => $totalUsers > 0 ? round($r['count'] / $totalUsers * 100) : 0])
            ->all();

        $refRel = Referral::query()->where('created_at', '>=', $start);
        $directRefs = (clone $refRel)->count();

        $indirectRefs = (clone $refRel)->whereHas('referrer', fn (Builder $q) => $q->whereNotNull('referrer_id'))->count();

        // Referral (Connect) bonuses by level — from the rollup.
        $bonusCnt = $rollups->bucketTotals('referral_bonus', $start, 'cnt');
        $bonusAmt = $rollups->bucketTotals('referral_bonus', $start, 'amt');
        $directBonusCount = (int) ($bonusCnt['direct'] ?? 0);
        $directBonusAmount = (float) ($bonusAmt['direct'] ?? 0);
        $indirectBonusCount = (int) ($bonusCnt['indirect'] ?? 0);
        $indirectBonusAmount = (float) ($bonusAmt['indirect'] ?? 0);

        // Earnings credited by type (excludes admin adjustments) — from the rollup.
        $creditByType = $rollups->bucketTotals('earnings_by_type', $start, 'amt');
        $creditEarned = fn (TransactionType $type): float => (float) ($creditByType[$type->value] ?? 0);
        $earningsCreditedTotal = (float) array_sum($creditByType);
        // Cloud earnings split by wallet: calls credit cloud_call, SMS claims credit cloud_sms.
        $cloudByWallet = $rollups->bucketTotals('cloud_earnings', $start, 'amt');
        $cloudCallEarned = (float) ($cloudByWallet['cloud_call'] ?? 0);
        $cloudSmsEarned = (float) ($cloudByWallet['cloud_sms'] ?? 0);

        // Genuine earning sources only (admin adjustments are excluded from earnings and shown
        // in their own card); this reconciles with the "Earnings credited" total.
        $sources = [
            ['Nextel Shares', $creditEarned(TransactionType::CALL_EARNING)],
            ['Ad revenue', $creditEarned(TransactionType::AD_REVENUE)],
            ['Connect Bonus', $creditEarned(TransactionType::REFERRAL_BONUS)],
            ['Target Reward', $creditEarned(TransactionType::REWARD)],
            ['N-Cloud Calls', $cloudCallEarned],
            ['N-Cloud SMS', $cloudSmsEarned],
        ];
        $sourceTotal = array_sum(array_column($sources, 1));
        $earningsBySource = array_map(fn (array $r): array => [
            'label' => $r[0],
            'amount' => $r[1],
            'pct' => $sourceTotal > 0 ? (int) round($r[1] / $sourceTotal * 100) : 0,
        ], $sources);

        // "Cloud subscribers by plan" moved to the Loans page (backoffice.loans) at the client's
        // request — kept here (commented) in case it should come back to Reports.
        // $cloudStoragePlanDistribution = CloudStoragePlan::query()
        //     ->withCount(['packs as subs_count' => fn (Builder $q) => $q->where(
        //         fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now())
        //     )])
        //     ->ordered()->get(['id', 'name'])
        //     ->map(fn (CloudStoragePlan $p): array => ['name' => $p->name, 'count' => (int) $p->subs_count])
        //     ->all();
        // $cloudSubTotal = array_sum(array_column($cloudStoragePlanDistribution, 'count'));
        // $cloudStoragePlanDistribution = array_map(fn (array $r): array => $r + [
        //     'pct' => $cloudSubTotal > 0 ? (int) round($r['count'] / $cloudSubTotal * 100) : 0,
        // ], $cloudStoragePlanDistribution);

        // VTU / bill payments — order status split (all-time) + delivered-by-service (window).
        $vtu = VtuOrder::query()->selectRaw('
            sum(case when status = ? then 1 else 0 end) as delivered_c, sum(case when status = ? then amount_usd else 0 end) as delivered_a,
            sum(case when status = ? then 1 else 0 end) as pending_c, sum(case when status = ? then amount_usd else 0 end) as pending_a,
            sum(case when status = ? then 1 else 0 end) as failed_c, sum(case when status = ? then amount_usd else 0 end) as failed_a,
            sum(case when status = ? then 1 else 0 end) as reversed_c, sum(case when status = ? then amount_usd else 0 end) as reversed_a
        ', [
            VtuStatus::DELIVERED->value, VtuStatus::DELIVERED->value,
            VtuStatus::PENDING->value, VtuStatus::PENDING->value,
            VtuStatus::FAILED->value, VtuStatus::FAILED->value,
            VtuStatus::REVERSED->value, VtuStatus::REVERSED->value,
        ])->first();

        $vtuByService = VtuOrder::query()->where('status', VtuStatus::DELIVERED->value)->where('created_at', '>=', $start)
            ->selectRaw('service, count(*) as c, sum(amount_usd) as a')->groupBy('service')->orderByDesc('a')
            ->get()->map(fn ($r): array => ['label' => Str::headline((string) $r->service), 'count' => (int) $r->c, 'amount' => (float) $r->a])->all();

        // Cloud SMS + promoter/shareholder distributions.
        $sponsoredInbox = fn () => CloudInboxMessage::query()->where('type', CloudInboxMessage::TYPE_SPONSORED);
        $cloudSms = [
            ['SMS blasts sent', CloudMessage::query()->where('created_at', '>=', $start)->count(), false],
            ['SMS recipients', (int) CloudMessage::query()->where('created_at', '>=', $start)->sum('recipients_count'), false],
            ['SMS claimed', (clone $sponsoredInbox())->whereNotNull('claimed_at')->where('claimed_at', '>=', $start)->count(), false],
            ['Unclaimed SMS liability', (float) (clone $sponsoredInbox())->whereNull('claimed_at')->where('earn_rate', '>', 0)->sum('earn_rate'), true],
            ['Distributions fired', CloudDistribution::query()->where('created_at', '>=', $start)->count(), false],
            ['Bonus claimed', (float) CloudBonusClaim::query()->where('claimed_at', '>=', $start)->sum('amount'), true],
        ];

        // Token inventory (all-time, by face value) + mix by type.
        $tok = Coupon::query()->selectRaw('
            count(*) as total_c, coalesce(sum(price), 0) as total_a,
            sum(case when user_id is not null then 1 else 0 end) as redeemed_c,
            sum(case when user_id is null then 1 else 0 end) as unredeemed_c,
            coalesce(sum(case when user_id is null then price else 0 end), 0) as unredeemed_a
        ')->first();
        $tokensByType = Coupon::query()->selectRaw('type, count(*) as c')->groupBy('type')->orderByDesc('c')
            ->get()->map(fn ($r): array => ['label' => Str::headline((string) ($r->type instanceof \BackedEnum ? $r->type->value : $r->type)), 'count' => (int) $r->c])->all();

        // Manual adjustments (completed) + vendor float transfers, over the window — from the rollup.
        $adjByDirection = $rollups->bucketTotals('adjustments', $start, 'amt');
        $adjCredited = (float) ($adjByDirection['credit'] ?? 0);
        $adjDebited = (float) ($adjByDirection['debit'] ?? 0);
        $agentXferCount = (int) $rollups->total('agent_transfer', $start, 'cnt');
        $agentXferAmount = $rollups->total('agent_transfer', $start, 'amt');

        // Geography + call earnings by type.
        $usersByCountry = User::query()->selectRaw('country, count(*) as c')->groupBy('country')->orderByDesc('c')->limit(8)
            ->get()->map(fn ($r): array => ['code' => (string) $r->country, 'count' => (int) $r->c])->all();
        $countryNames = Country::query()->pluck('name', 'code')->all();

        $callTypeNames = CallType::query()->pluck('name', 'id')->all();
        $callCnt = $rollups->bucketTotals('call_earnings', $start, 'cnt');
        $callAmt = $rollups->bucketTotals('call_earnings', $start, 'amt');
        $callEarningsByType = collect($callAmt)
            ->map(fn (float $amount, string $callTypeId): array => [
                'label' => (string) ($callTypeNames[$callTypeId] ?? 'Unknown'),
                'count' => (int) ($callCnt[$callTypeId] ?? 0),
                'amount' => (float) $amount,
            ])
            ->sortByDesc('amount')->values()->all();

        return [
            'generatedAt' => now()->toIso8601String(),
            'referralBreakdown' => [
                'directRefs' => $directRefs,
                'indirectRefs' => $indirectRefs,
                'directBonusCount' => $directBonusCount,
                'directBonusAmount' => $directBonusAmount,
                'indirectBonusCount' => $indirectBonusCount,
                'indirectBonusAmount' => $indirectBonusAmount,
            ],
            'earningsBySource' => $earningsBySource,
            'days' => $this->days,
            'usersByPackage' => $packageRows,
            'signups' => $signups,
            'earnings' => $earnings,
            'payouts' => $payouts,
            'adShareSeries' => $adShareSeries,
            // Cloud earnings come from both sponsored calls and SMS claims — both are CLOUD_EARNING credits.
            'cloudEarningsSeries' => $this->seriesFromDaily($rollups->dailyTotals('cloud_earnings', $start, 'amt'), $start),
            // 'cloudStoragePlanDistribution' => $cloudStoragePlanDistribution, // moved to Loans page
            'cloudStats' => [
                ['Cloud calls', CloudCall::query()->completed()->where('ended_at', '>=', $start)->count(), 'ri-phone-line', false],
                ['Call earnings', $cloudCallEarned, 'ri-money-dollar-circle-line', true],
                ['SMS earnings', $cloudSmsEarned, 'ri-message-2-line', true],
                ['Broadcasts sent', CloudBroadcast::query()->where('created_at', '>=', $start)->count(), 'ri-broadcast-line', false],
                ['Calling enabled', User::query()->whereHas('package', fn (Builder $q) => $q->where('cloud_calls', true))->count(), 'ri-cloud-line', false],
                ['Cloud users', User::query()->whereHas('package', fn (Builder $q) => $q->where('enable_cloud', true))->count(), 'ri-user-voice-line', false],
            ],

            'periodStats' => [
                ['New users', (int) array_sum(array_column($signups, 'value')), 'ri-user-add-line', false],
                ['Earnings credited', $earningsCreditedTotal, 'ri-money-dollar-circle-line', true],
                ['Withdrawals requested', Withdrawal::query()->where('created_at', '>=', $start)->count(), 'ri-arrow-up-line', false],
                ['Paid out', (float) Withdrawal::query()->where('status', WithdrawalStatus::COMPLETED->value)->where('processed_at', '>=', $start)->sum('amount_paid'), 'ri-bank-card-line', true],
                ['Referrals', $directRefs, 'ri-share-forward-line', false],
                ['Coupons redeemed', Coupon::query()->where('used_at', '>=', $start)->count(), 'ri-coupon-line', false],
                ['Ad shares', (int) array_sum(array_column($adShareSeries, 'value')), 'ri-advertisement-line', false],
                ['Ad payouts', (float) AdShare::query()->where('shared_at', '>=', $start)->sum('amount'), 'ri-share-line', true],
                ['New agents', User::query()->where('is_agent', true)->where('created_at', '>=', $start)->count(), 'ri-store-2-line', false],
                ['Calls claimed', (int) $rollups->total('calls_claimed', $start, 'cnt'), 'ri-phone-line', false],
                ['Loans requested', Loan::query()->where('created_at', '>=', $start)->count(), 'ri-bank-line', false],
                ['Reward targets hit', RewardTarget::query()->where('achieved_at', '>=', $start)->count(), 'ri-trophy-line', false],
                ['VTU orders', (int) $vtu->delivered_c, 'ri-smartphone-line', false],
                ['VTU spend', VtuOrder::query()->where('status', VtuStatus::DELIVERED->value)->where('created_at', '>=', $start)->sum('amount_usd'), 'ri-shopping-bag-3-line', true],
                ['Activations', Coupon::query()->where('type', CouponType::ACTIVATION->value)->where('used_at', '>=', $start)->count(), 'ri-sim-card-line', false],
                ['Share access activations', Coupon::query()->where('type', CouponType::SHARE_ACTIVATION->value)->where('used_at', '>=', $start)->count()
                    + Transaction::query()->where('type', TransactionType::SHARE_ACCESS_PURCHASE->value)->where('status', 'completed')->where('updated_at', '>=', $start)->count(), 'ri-pie-chart-2-line', false],
                ['Package upgrades', Coupon::query()->where('type', CouponType::UPGRADE->value)->where('used_at', '>=', $start)->count(), 'ri-arrow-up-double-line', false],
                ['Minute top-ups', MinuteTopUp::query()->where('created_at', '>=', $start)->count(), 'ri-time-line', false],
                ['Debit cards issued', DebitCard::query()->where('created_at', '>=', $start)->count(), 'ri-bank-card-2-line', false],
                ['Vendor transfers', $agentXferCount, 'ri-arrow-left-right-line', false],
            ],
            'usersSnapshot' => [
                ['Total users', number_format((int) $u->total)],
                ['Active', number_format((int) $u->active)],
                ['Activated (has package)', number_format((int) $u->activated)],
                ['Agents', number_format((int) $u->agents)],
                ['Suspended', number_format((int) $u->suspended)],
            ],
            'walletSnapshot' => [
                ['Nextel Shares', (float) $u->w_shareholder],
                ['Ads Revenue', (float) $u->w_ads],
                ['Connect Bonus', (float) $u->w_referral],
                ['Target Reward', (float) $u->w_reward],
                ['N-Cloud Calls', (float) $u->w_cloud_call],
                ['N-Cloud SMS', (float) $u->w_cloud_sms],
                ['VTU Balance', (float) $u->w_bills],

                ['Vendor Float', (float) $u->w_agent],
            ],
            'withdrawalEligible' => $withdrawalEligible,
            'withdrawalRows' => [
                ['Pending', (int) $wd->pending_c, (float) $wd->pending_a, 'warning'],
                ['Processing', (int) $wd->processing_c, (float) $wd->processing_a, 'warning'],
                ['Completed', (int) $wd->completed_c, (float) $wd->completed_a, 'success'],
                ['Rejected', (int) $wd->rejected_c, (float) $wd->rejected_a, 'danger'],
            ],
            // Loan requested/repayment totals by workflow state; Paid means staff recorded manual disbursement.
            'loanRows' => [
                ['Processing', (int) $ln->processing_c, (float) $ln->processing_a, 'warning'],
                ['Approved — awaiting disbursement', (int) $ln->approved_c, (float) $ln->approved_a, 'info'],
                ['Paid / disbursed', (int) $ln->paid_c, (float) $ln->paid_a, 'success'],
                ['Rejected', (int) $ln->rejected_c, (float) $ln->rejected_a, 'danger'],
            ],
            // Completed payouts by payout currency — the USD table alone hides local-currency totals.
            'payoutsByCurrency' => Withdrawal::query()
                ->where('status', WithdrawalStatus::COMPLETED->value)
                ->selectRaw("coalesce(nullif(local_currency, ''), 'NGN') as currency, count(*) as c, sum(local_amount_paid) as local_total, sum(amount_paid) as usd_total")
                ->groupBy('currency')
                ->orderByDesc('usd_total')
                ->get()
                ->map(fn ($r): array => [
                    'currency' => (string) $r->currency,
                    'count' => (int) $r->c,
                    'local' => (float) $r->local_total,
                    'usd' => (float) $r->usd_total,
                ])->all(),
            // Ranked like the public Top Earners page: lifetime referral-earning history
            // (summed Connect Bonus credits from the ledger), active non-system users, top 5.
            // Plain arrays, not Eloquent collections — the payload is cached (Redis), and caching
            // models is fragile (stringified keys, incomplete-class on unserialize).
            // The three leaderboards used to rank the whole users table by a correlated aggregate
            // (withSum/withCount + ORDER BY on the alias), which makes the database evaluate that
            // subquery for every user before the LIMIT can apply. Each now aggregates the source
            // table first — driven by an index on the filtered columns — joins users for the name
            // and the eligibility filter, and only then takes five.
            'topEarners' => Transaction::query()
                ->join('users', 'users.id', '=', 'transactions.user_id')
                ->where('transactions.type', TransactionType::REFERRAL_BONUS->value)
                ->where('transactions.direction', 'credit')
                ->where('users.status', 'active')
                ->whereNotIn('users.username', User::SYSTEM_USERNAMES)
                ->groupBy('transactions.user_id', 'users.username')
                ->orderByDesc('referral_earned')
                ->limit(5)
                ->get([
                    'transactions.user_id as id',
                    'users.username',
                    DB::raw('sum(transactions.amount) as referral_earned'),
                ])
                ->map(fn ($row): array => ['id' => $row->id, 'username' => $row->username, 'value' => (float) $row->referral_earned])
                ->all(),
            'topAgents' => Coupon::query()
                ->join('users', 'users.id', '=', 'coupons.agent_id')
                ->whereNotNull('coupons.user_id')
                ->where('users.is_agent', true)
                ->groupBy('coupons.agent_id', 'users.username')
                ->orderByDesc('redeemed_count')
                ->limit(5)
                ->get([
                    'coupons.agent_id as id',
                    'users.username',
                    DB::raw('count(*) as redeemed_count'),
                ])
                ->map(fn ($row): array => ['id' => $row->id, 'username' => $row->username, 'count' => (int) $row->redeemed_count])
                ->all(),
            // Same source as before: User::referrals() is the self-referential "users who named me
            // as their referrer", so the board counts referred USERS (the referrals ledger, which
            // only exists for confirmed pairs, is deliberately not what this card has ever shown).
            'topReferrers' => User::query()
                ->join('users as referrers', 'referrers.id', '=', 'users.referrer_id')
                ->groupBy('users.referrer_id', 'referrers.username')
                ->orderByDesc('referrals_count')
                ->limit(5)
                ->get([
                    'users.referrer_id as id',
                    'referrers.username',
                    DB::raw('count(*) as referrals_count'),
                ])
                ->map(fn ($row): array => ['id' => $row->id, 'username' => $row->username, 'count' => (int) $row->referrals_count])
                ->all(),

            'vtuRows' => [
                ['Delivered', (int) $vtu->delivered_c, (float) $vtu->delivered_a, 'success'],
                ['Pending', (int) $vtu->pending_c, (float) $vtu->pending_a, 'warning'],
                ['Failed', (int) $vtu->failed_c, (float) $vtu->failed_a, 'danger'],
                ['Reversed', (int) $vtu->reversed_c, (float) $vtu->reversed_a, 'muted'],
            ],
            'vtuByService' => $vtuByService,
            'cloudSms' => $cloudSms,
            'tokenInventory' => [
                ['Generated', (int) $tok->total_c, (float) $tok->total_a],
                ['Redeemed', (int) $tok->redeemed_c, null],
                ['Outstanding', (int) $tok->unredeemed_c, (float) $tok->unredeemed_a],
            ],
            'tokensByType' => $tokensByType,
            'adjustments' => ['credited' => $adjCredited, 'debited' => $adjDebited],
            'agentTransfers' => ['count' => $agentXferCount, 'amount' => $agentXferAmount],
            'usersByCountry' => $usersByCountry,
            'countryNames' => $countryNames,
            'callEarningsByType' => $callEarningsByType,
        ];
    }
}
