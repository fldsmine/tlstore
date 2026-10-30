<?php

namespace App\Livewire\Dashboard\Earnings;

use App\Models\AllocatedNumber;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Services\CallLogService;
use App\Services\ClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Earnings')]
class Earnings extends Component
{
    use WithPagination;

    public function mount(): void
    {
        // Share-only accounts use the dedicated Shareholder Portal; the legacy earnings list is
        // retained for eSIM package holders and does not become a second share-access surface.
        if (auth()->user()?->package_id === null) {
            $this->redirectRoute('shareholder', navigate: true);

            return;
        }

        app(CallLogService::class)->ensureTodaysPool();
    }

    public function claim(string $recordId): void
    {
        $user = auth()->user();

        $record = CallRecord::query()->whereKey($recordId)->with('callType')->first();

        if (! $record) {
            return;
        }

        $claims = app(ClaimService::class);
        $blockedReason = $claims->blockedReason($user, $record);

        if ($blockedReason !== null) {
            $message = match ($blockedReason) {
                'share_plan_inactive' => 'An active share term is required to claim. Start or buy one from the Shareholder Portal.',
                'call_type_not_in_package' => ($record->callType?->name ?? 'This call type').' is not included in your active share product. Choose that share product from the Shareholder Portal.',
                'daily_earning_cap' => 'No part of this call fits within your remaining daily earning cap.',
                'term_minutes_exhausted' => 'No share-term minutes remain for this call.',
                'share_minutes_not_accrued' => 'Your next share-minute allowance has not accrued yet. Unused accrued minutes roll forward.',
                'already_claimed' => 'You have already claimed this call record.',
                'call_record_expired' => 'This call record is no longer available for claiming.',
                default => 'This call record cannot be claimed right now.',
            };
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        $claimedMinutes = $claims->claimableMinutes($user, $record);
        $amount = $claims->claim($user, $record);

        if ($amount > 0) {
            $partialDetail = $claimedMinutes + 0.001 < (float) $record->duration_minutes
                ? ' for '.rtrim(rtrim(number_format($claimedMinutes, 2), '0'), '.').' of '.rtrim(rtrim(number_format((float) $record->duration_minutes, 2), '0'), '.').' min'
                : '';
            $this->dispatch('toast', message: 'Claimed '.money($amount).$partialDetail.' to your Nextel Shares wallet.', type: 'success');
        } else {
            $this->dispatch('toast', message: 'Could not claim — daily allowance reached or already claimed.', type: 'error');
        }
    }

    public function claimAll(): void
    {
        $user = auth()->user();
        app(CallLogService::class)->ensureTodaysPool();

        // Do not use the paginated rows rendered below: claim-all must consider the entire daily pool.
        $records = CallRecord::query()
            ->whereDate('created_at', today())
            ->with('callType')
            ->get();
        $amount = app(ClaimService::class)->claimBatch($user, $records);

        if ($amount > 0) {
            $this->dispatch('toast', message: 'Claimed '.money($amount).' in available call earnings to your Nextel Shares wallet.', type: 'success');

            return;
        }

        $this->dispatch('toast', message: 'No eligible call earnings are available within your current share-minute and daily-cap limits.', type: 'info');
    }

    public function render(): View
    {
        $user = auth()->user()->loadMissing('package.callTypes');
        $claims = app(ClaimService::class);
        $subscription = $claims->subscription($user);
        $allowance = $claims->allocatedMinutes($user, $subscription);
        $remaining = $claims->remainingMinutes($user, $subscription);
        $available = $claims->availableMinutes($user, $subscription);
        $dailyCap = $claims->maxDailyEarning($user, $subscription);
        $dailyCapRemaining = $claims->remainingEarning($user, $subscription);

        $pool = CallRecord::query()->whereDate('created_at', today())->with('callType')->get();

        $typeMeta = $pool->pluck('callType')->filter()->unique('id')->mapWithKeys(function ($ct) use ($user, $claims, $subscription) {
            $inPackage = $claims->inPackage($user, $ct, $subscription);

            return [$ct->id => [
                'inPackage' => $inPackage,
                'pct' => $inPackage ? $claims->claimPercent($user, $ct, $subscription) : (float) $ct->claimable_percentage,
                'name' => $ct->name,
            ]];
        });

        $myClaims = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->get()
            ->keyBy('call_record_id');

        $rows = $pool->map(function (CallRecord $r) use ($typeMeta, $myClaims, $user, $claims, $subscription, $available, $dailyCapRemaining): array {
            $meta = $typeMeta->get($r->call_type_id, ['inPackage' => false, 'pct' => 0.0, 'name' => 'Call']);
            $myClaim = $myClaims->get($r->id);
            $claimed = $myClaim !== null;
            $network = AllocatedNumber::networkFor($r->counterparty) ?? 'Nextel';
            $claimableMinutes = $claimed
                ? 0.0
                : $claims->claimableMinutes($user, $r, $subscription, $available, $dailyCapRemaining);
            $potentialAmount = round((float) $r->earnings * $meta['pct'] / 100, 6);
            $amount = $claimed
                ? (float) $myClaim->amount
                : ($claimableMinutes > 0
                    ? $claims->claimableAmountForMinutes($user, $r, $claimableMinutes, $subscription)
                    : $potentialAmount);
            $capAllows = $dailyCapRemaining > 0 && ($claimableMinutes > 0 || $potentialAmount <= $dailyCapRemaining);

            return [
                'id' => $r->id,
                'type' => $meta['name'],
                'amount' => $amount,
                'capAllows' => $capAllows,
                'earned' => (float) $r->earnings,
                'duration' => (float) $r->duration_minutes,
                'claimableMinutes' => $claimableMinutes,
                'claimedMinutes' => $claimed ? (float) $myClaim->duration_minutes : 0.0,
                'claimed' => $claimed,
                'claimedAt' => $myClaim?->claimed_at,
                'occurredAt' => $r->created_at,
                'inPackage' => $meta['inPackage'],
                'canClaim' => $meta['inPackage'] && ! $claimed && $claimableMinutes > 0,
                'network' => $network,
                'logo' => AllocatedNumber::logoFor($network),
            ];
        })->sort(function (array $a, array $b): int {
            if ($a['claimed'] !== $b['claimed']) {
                return $b['claimed'] <=> $a['claimed'];
            }
            $ta = ($a['claimed'] ? $a['claimedAt'] : $a['occurredAt'])?->timestamp ?? 0;
            $tb = ($b['claimed'] ? $b['claimedAt'] : $b['occurredAt'])?->timestamp ?? 0;

            return $tb <=> $ta;
        })->values();

        $inPackageRows = $rows->where('inPackage', true);
        $claimedTotal = round((float) $myClaims->sum('amount'), 6);
        $unclaimedRecords = $pool->reject(fn (CallRecord $record): bool => $myClaims->has($record->id));
        $claimableBreakdown = $claims->claimableBreakdown($user, $unclaimedRecords, $subscription, $available, $dailyCapRemaining);
        $unclaimedTotal = round(array_sum(array_column($claimableBreakdown, 'amount')), 6);

        $perPage = 12;
        $page = $this->getPage();
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return view('livewire.dashboard.earnings.earnings', [
            'rows' => $paginated,
            'todayEarnings' => round($claimedTotal + $unclaimedTotal, 6),
            'claimedTotal' => $claimedTotal,
            'unclaimedTotal' => $unclaimedTotal,
            'hasClaimable' => $rows->where('canClaim', true)->isNotEmpty(),
            'allowanceExhausted' => $inPackageRows->where('claimed', false)->isNotEmpty() && $rows->where('canClaim', true)->isEmpty(),
            'lockedTypes' => $rows->where('inPackage', false)->pluck('type')->unique()->values(),
            'lockedPotential' => round((float) $rows->where('inPackage', false)->where('claimed', false)->sum('amount'), 6),
            'remaining' => $remaining,
            'available' => $available,
            'allowance' => $allowance,
            'dailyCap' => $dailyCap,
            'dailyCapRemaining' => $dailyCapRemaining,
            'shareExpiresAt' => $subscription?->expires_at,
            'hasActiveSharePlan' => $subscription !== null,
            'shareRenewalAvailable' => $user->package !== null
                && $subscription === null
                && app(\App\Services\ShareSubscriptionService::class)->packageIsConfigured($user->package),
            'directPackagePay' => app(\App\Services\Payments\PaymentGatewayManager::class)
                ->availableFor(\App\Enums\ChargePurpose::PACKAGE),
        ]);
    }
}
