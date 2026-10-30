@props(['title' => null, 'subtitle' => null, 'back' => null])

<div class="min-h-screen bg-background">
    <div class="w-full px-4 md:px-8 lg:px-10 pb-24 lg:pb-10 flex flex-col gap-6">
        <x-backoffice.app-bar :title="$title" :subtitle="$subtitle" :back="$back">
            <x-slot:action>{{ $action ?? '' }}</x-slot:action>
        </x-backoffice.app-bar>

        @if (config('settings.app.enable_maintenance_mode'))
            <div class="flex items-start gap-3 rounded-[16px] bg-[#d79937]/12 border border-[#d79937]/25 px-4 py-3.5">
                <i class="ri-tools-fill text-[#d79937] text-[20px] shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[14px]">Maintenance mode is ON</p>
                    <p class="font-sans text-muted text-[13px]">The public site is showing the maintenance page to users. The backoffice stays open.</p>
                </div>
                @if (auth('admin')->user()->canWrite() && \Illuminate\Support\Facades\Route::has('backoffice.settings.general'))
                    <a href="{{ route('backoffice.settings.general') }}" wire:navigate
                       class="shrink-0 self-center font-sans text-[13px] text-[#d79937] underline whitespace-nowrap">Turn off</a>
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
