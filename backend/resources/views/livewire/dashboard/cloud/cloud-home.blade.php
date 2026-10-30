<div>
    {{-- addOpen/q live on the page root so the hero CTA and the contacts card open the same picker. --}}
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]"
         x-data="{ addOpen: false, q: '' }">

        <x-app.app-bar :title="'Nextel Cloud'" :subtitle="'Earn from sponsored calls and messages'" />

        @php
            $card = 'bg-surface rounded-[20px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]';
            $eyebrow = 'font-sans text-muted text-[11px] uppercase tracking-[0.14em]';
        @endphp

        @if (! ($canAccess ?? true))
            @include('livewire.dashboard.cloud.partials.locked')
        @elseif (! $canCall && ($canSms ?? false))
            @include('livewire.dashboard.cloud.partials.sms-only')
        @elseif (! $canCall)
            {{-- Cloud is enabled but neither calls nor SMS are on this plan — prompt an upgrade. --}}
            @include('livewire.dashboard.cloud.partials.locked')
        @else
            {{-- The eSIM is the page's identity object, so it spans the full width on its own row. --}}
            <x-app.cloud-sim-card
                :user="auth()->user()"
                :planName="$packageName"
                :linesUsed="$quotaUsed"
                :linesQuota="$quota"
                :validLabel="'Lifetime'"
                :status="'Active'"
                :balance="$balance"
                :todayEarned="$todayEarned"
                :dailyCap="$dailyCap"
                :hourlyEarned="$hourlyEarned"
                :hourlyCap="$hourlyCap"
                :withdrawUrl="route('withdraw', ['wallet' => 'cloud_call'])" />

            <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-[22px] lg:items-start">
                <div class="lg:col-span-2 flex flex-col gap-[22px]">
                    @include('livewire.dashboard.cloud.partials.today')
                    @include('livewire.dashboard.cloud.partials.bonuses')
                    @include('livewire.dashboard.cloud.partials.contacts')
                    @include('livewire.dashboard.cloud.partials.history')
                </div>

                <div class="lg:col-span-1 flex flex-col gap-[22px]">
                    @include('livewire.dashboard.cloud.partials.storage')
                    {{-- @include('livewire.dashboard.cloud.partials.week-chart') --}}
                    @include('livewire.dashboard.cloud.partials.shortcuts')
                </div>
            </div>
        @endif
    </div>

    <x-app.bottom-nav />
</div>
