<?php

namespace App\Livewire\Dashboard\Earnings;

use App\Models\AllocatedNumber;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Enums\ChargePurpose;
use App\Models\CallType;
use App\Services\CallLogService;
use App\Services\ClaimService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ShareSubscriptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Earnings Breakdown')]
class ShareholderCallType extends Component
{
    use WithPagination;

    public string $callTypeId;

    public function mount(CallType $callType): void
    {
        abort_unless($callType->is_active, 404);

        app(CallLogService::class)->ensureTodaysPool();

        $this->callTypeId = $callType->id;
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

    public function render(): View
    {
        $user = auth()->user()->loadMissing('package.callTypes');
        $claims = app(ClaimService::class);

        $callType = CallType::query()->findOrFail($this->callTypeId);
        $subscription = $claims->subscription($user);
        $shareProducts = app(ShareSubscriptionService::class);
        $directSharePay = app(PaymentGatewayManager::class)->availableFor(ChargePurpose::PACKAGE);
        $priceRegion = RegionPricing::forUser($user);
        $inPackage = $claims->inPackage($user, $callType, $subscription);
        $pct = $inPackage ? $claims->claimPercent($user, $callType, $subscription) : (float) $callType->claimable_percentage;
        $allocated = $claims->allocatedMinutes($user, $subscription);
        $remaining = $claims->remainingMinutes($user, $subscription);
        $available = $claims->availableMinutes($user, $subscription);
        $dailyCap = $claims->maxDailyEarning($user, $subscription);
        $dailyCapRemaining = $claims->remainingEarning($user, $subscription);

        $pool = CallRecord::query()
            ->whereDate('created_at', today())
            ->where('call_type_id', $callType->id)
            ->with('callType')
            ->get();

        $myClaims = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->where('call_type_id', $callType->id)
            ->get()
            ->keyBy('call_record_id');

        $claimableBreakdown = $claims->claimableBreakdown(
            $user,
            $pool->reject(fn (CallRecord $record): bool => $myClaims->has($record->id)),
            $subscription,
            $available,
            $dailyCapRemaining,
        );
        $claimableTotal = array_sum(array_column($claimableBreakdown, 'amount'));

        $rows = $pool->map(function (CallRecord $r) use ($myClaims, $inPackage, $pct, $user, $claims, $subscription, $available, $dailyCapRemaining): array {
            $myClaim = $myClaims->get($r->id);
            $claimed = $myClaim !== null;
            $network = AllocatedNumber::networkFor($r->counterparty) ?? 'Nextel';
            $claimableMinutes = $claimed
                ? 0.0
                : $claims->claimableMinutes($user, $r, $subscription, $available, $dailyCapRemaining);
            $potentialAmount = round((float) $r->earnings * $pct / 100, 6);
            $amount = $claimed
                ? (float) $myClaim->amount
                : ($claimableMinutes > 0
                    ? $claims->claimableAmountForMinutes($user, $r, $claimableMinutes, $subscription)
                    : $potentialAmount);
            $capAllows = $dailyCapRemaining > 0 && ($claimableMinutes > 0 || $potentialAmount <= $dailyCapRemaining);

            return [
                'id' => $r->id,
                'code' => '#'.strtoupper(substr($r->id, -4)),
                'duration' => (float) $r->duration_minutes,
                'claimableMinutes' => $claimableMinutes,
                'claimedMinutes' => $claimed ? (float) $myClaim->duration_minutes : 0.0,
                'earnings' => (float) $r->earnings,
                'claimable' => $amount,
                'capAllows' => $capAllows,
                'claimed' => $claimed,
                'claimedAt' => $myClaim?->claimed_at,
                'occurredAt' => $r->created_at,
                'canClaim' => $inPackage && ! $claimed && $claimableMinutes > 0,
                'counterparty' => $r->counterparty,
                'network' => $network,
                'logo' => AllocatedNumber::logoFor($network),
                'direction' => $r->direction,
            ];
        })->sort(function (array $a, array $b): int {

            if ($a['claimed'] !== $b['claimed']) {
                return $b['claimed'] <=> $a['claimed'];
            }
            $ta = ($a['claimed'] ? $a['claimedAt'] : $a['occurredAt'])?->timestamp ?? 0;
            $tb = ($b['claimed'] ? $b['claimedAt'] : $b['occurredAt'])?->timestamp ?? 0;

            return $tb <=> $ta;
        })->values();

        $perPage = 10;
        $page = $this->getPage();
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );

        return view('livewire.dashboard.earnings.shareholder-call-type', [
            'callType' => $callType,
            'inPackage' => $inPackage,
            'sharePct' => $pct,
            'rows' => $paginated,
            'totalCalls' => $rows->count(),
            'totalMinutes' => round((float) $pool->sum('duration_minutes'), 1),
            'claimableTotal' => round((float) $claimableTotal, 6),
            'claimedTotal' => round((float) $myClaims->sum('amount'), 6),
            'remaining' => $remaining,
            'available' => $available,
            'allowance' => $allocated,
            'dailyCap' => $dailyCap,
            'dailyCapRemaining' => $dailyCapRemaining,
            'shareExpiresAt' => $subscription?->expires_at,
            'hasActiveSharePlan' => $subscription !== null,
            'shareTypeName' => $subscription?->callType?->name,
            'shareConfigured' => $shareProducts->callTypeIsConfigured($callType),
            'sharePrice' => RegionPricing::customer($callType, $priceRegion),
            'canBuyShare' => $subscription === null
                && $directSharePay
                && $shareProducts->callTypeIsDirectlyPurchasable($callType, $priceRegion),
            'shareRenewalAvailable' => $user->package !== null
                && $subscription === null
                && $shareProducts->packageIsConfigured($user->package),
            'directPackagePay' => $directSharePay,
            'packageName' => $user->package?->name,
        ]);
    }
}
