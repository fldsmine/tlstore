<?php

namespace App\Livewire\Dashboard\Earnings;

use App\Enums\ChargePurpose;
use App\Models\CallClaim;
use App\Models\CallRecord;
use App\Models\CallType;
use App\Services\CallLogService;
use App\Services\ClaimService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ShareSubscriptionService;
use App\Services\TokenRedemptionService;
use App\Support\RegionPricing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Shareholder Portal')]
class Shareholder extends Component
{
    public ?string $shareCallTypeId = null;

    public string $shareToken = '';

    public function mount(): void
    {
        app(CallLogService::class)->ensureTodaysPool();
    }

    public function claimAll(): void
    {
        $user = auth()->user();
        app(CallLogService::class)->ensureTodaysPool();

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

    public function redeemShare(TokenRedemptionService $redemption): void
    {
        $this->validate([
            'shareCallTypeId' => ['required', 'string', 'exists:call_types,id'],
            'shareToken' => ['required', 'string', 'min:6', 'max:32'],
        ], [
            'shareCallTypeId.required' => 'Choose the share product this code is for.',
            'shareToken.required' => 'Enter your share activation code.',
        ]);

        $callType = CallType::query()->active()->findOrFail($this->shareCallTypeId);
        $redemption->redeemShareAccess(auth()->user(), $callType->id, $this->shareToken, 'shareCallTypeId', 'shareToken');

        $this->reset('shareCallTypeId', 'shareToken');
        $this->dispatch('toast', message: $callType->name.' share access is now active.', type: 'success');
    }

    public function render(): View
    {
        $user = auth()->user()->loadMissing('package.callTypes');
        $claims = app(ClaimService::class);
        $shareProducts = app(ShareSubscriptionService::class);
        $region = RegionPricing::forUser($user);
        $directSharePay = app(PaymentGatewayManager::class)->availableFor(ChargePurpose::PACKAGE);

        $callTypes = CallType::query()->active()->ordered()->get();
        $couponShareTypes = $callTypes
            ->filter(fn (CallType $callType): bool => $shareProducts->callTypeIsConfigured($callType))
            ->values();

        $pool = CallRecord::query()->whereDate('created_at', today())->with('callType')->get();
        $byType = $pool->groupBy('call_type_id');

        $myClaims = CallClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->get();
        $myClaimedRecordIds = $myClaims->pluck('call_record_id')->filter()->flip();
        $myClaimedByType = $myClaims->groupBy('call_type_id');

        $subscription = $claims->subscription($user);
        $allocated = $claims->allocatedMinutes($user, $subscription);
        $remaining = $claims->remainingMinutes($user, $subscription);
        $available = $claims->availableMinutes($user, $subscription);
        $dailyCap = $claims->maxDailyEarning($user, $subscription);
        $dailyCapRemaining = $claims->remainingEarning($user, $subscription);
        $claimableBreakdown = $claims->claimableBreakdown(
            $user,
            $pool->reject(fn (CallRecord $record): bool => $myClaimedRecordIds->has($record->id)),
            $subscription,
            $available,
            $dailyCapRemaining,
        );
        $claimableByType = collect($claimableBreakdown)
            ->groupBy(fn (array $row): ?string => $row['record']->call_type_id)
            ->map(fn ($typeRows): float => (float) $typeRows->sum('amount'));

        $cards = $callTypes->map(function (CallType $callType) use (
            $byType,
            $myClaimedRecordIds,
            $myClaimedByType,
            $user,
            $claims,
            $shareProducts,
            $region,
            $subscription,
            $claimableByType,
            $directSharePay,
            $remaining,
        ): array {
            $records = $byType->get($callType->id, collect());
            $inPlan = $claims->inPackage($user, $callType, $subscription);
            $percent = $claims->claimPercent($user, $callType, $subscription);
            $defaultPercent = (float) $callType->claimable_percentage;
            $unclaimed = $records->reject(fn (CallRecord $record): bool => $myClaimedRecordIds->has($record->id));

            $claimable = $inPlan ? (float) $claimableByType->get($callType->id, 0.0) : 0.0;
            $promptThreshold = max(0, (float) $callType->share_purchase_prompt_minutes);

            return [
                'type' => $callType,
                'inPackage' => $inPlan,
                'calls' => $records->count(),
                'unclaimed' => $inPlan ? $unclaimed->count() : 0,
                'minutes' => round((float) $records->sum('duration_minutes'), 1),
                'earnings' => round((float) $records->sum('earnings'), 6),
                'claimable' => round((float) $claimable, 6),
                'claimed' => round((float) ($myClaimedByType->get($callType->id)?->sum('amount') ?? 0), 6),
                'sharePct' => $inPlan ? $percent : $defaultPercent,
                'promptThreshold' => $promptThreshold,
                'showPurchasePrompt' => $inPlan && $promptThreshold > 0 && $remaining < $promptThreshold,
                'shareConfigured' => $shareProducts->callTypeIsConfigured($callType),
                'sharePrice' => RegionPricing::customer($callType, $region),
                'canBuyShare' => $subscription === null
                    && $directSharePay
                    && $shareProducts->callTypeIsDirectlyPurchasable($callType, $region),
                'activeOnAnotherShareType' => $subscription?->call_type_id !== null
                    && $subscription->call_type_id !== $callType->id,
            ];
        });

        $topTypeId = $cards->where('earnings', '>', 0)->sortByDesc('earnings')->first()['type']->id ?? null;
        $shareRenewalAvailable = $user->package !== null
            && $subscription === null
            && $shareProducts->packageIsConfigured($user->package);
        $directPackagePay = $directSharePay;

        return view('livewire.dashboard.earnings.shareholder', [
            'cards' => $cards,
            'topTypeId' => $topTypeId,
            'totalClaimable' => round((float) $cards->sum('claimable'), 6),
            'hasClaimable' => $claimableBreakdown !== [],
            'totalClaimed' => round((float) $cards->sum('claimed'), 6),
            'allowance' => $allocated,
            'remaining' => $remaining,
            'available' => $available,
            'dailyCap' => $dailyCap,
            'dailyCapRemaining' => $dailyCapRemaining,
            'shareExpiresAt' => $subscription?->expires_at,
            'hasActiveSharePlan' => $subscription !== null,
            'shareSubscription' => $subscription,
            'shareTypeName' => $subscription?->callType?->name,
            'shareRenewalAvailable' => $shareRenewalAvailable,
            'directPackagePay' => $directPackagePay,
            'directSharePay' => $directSharePay,
            'couponShareTypes' => $couponShareTypes,
            'hasConfiguredShareProducts' => $couponShareTypes->isNotEmpty(),
            'packageName' => $user->package?->name,
        ]);
    }
}
