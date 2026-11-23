{{-- Opacity crossfade, not a 3D rotateY flip: preserve-3d flattens/mirrors in this nested layout. Front in flow sizes the card; back is an absolute overlay. Opacity forced with `!important` or Tailwind transition utilities win the cascade and pin the front visible. --}}
@props([
    'user',
    'planName' => '—',
    'linesUsed' => 0,
    'linesQuota' => 0,
    'validLabel' => '—',
    'status' => 'Active',
    'balance' => 0,
    'todayEarned' => 0,
    'dailyCap' => 0,
    'hourlyEarned' => 0,
    'hourlyCap' => 0,
    'withdrawUrl' => '#',
])

@php
    $statusColor = in_array($status, ['Active', 'Trial'], true) ? 'text-secondary' : 'text-[#f0a04b]';
    $linesLabel = $linesQuota > 0 ? $linesUsed.'/'.$linesQuota : (string) $linesUsed;
    // Dark-on-white: inverted (light-on-dark) QR codes defeat a lot of scanners, so the code gets
    // its own white tile rather than sitting directly on the card.
    $lineQr = \App\Support\QrCode::svg((string) ($user->cloud_line_number ?? ''), 120, '123A34');
@endphp

<div x-data="{ flipped: false, show: false, masked: @js($user->cloud_line_masked ?? 'Not assigned'), full: @js($user->cloud_line_formatted ?? 'Not assigned') }"
     class="relative w-full select-none">

    {{-- FRONT: in flow to size the card; opacity-gated so one face is clickable, no display toggle → no reflow. --}}
    <div @click="flipped = true" :style="flipped ? 'opacity: 0 !important; pointer-events: none' : 'opacity: 1 !important'"
         class="cursor-pointer transition-opacity duration-300 rounded-[22px] overflow-hidden bg-primary p-5 flex flex-col gap-4 shadow-[0_10px_30px_rgba(24,68,62,0.35)]">
        <div class="absolute -right-12 -top-12 size-56 rounded-full border-[18px] border-secondary/10 pointer-events-none"></div>
        <div class="absolute right-8 top-8 size-28 rounded-full border-[12px] border-secondary/10 pointer-events-none"></div>

        <div class="relative flex items-center justify-between">
            <div class="flex items-center">
                <img src="{{ asset('images/public/logo-light.png') }}" alt="Nextel" class="h-6 w-auto object-contain">
            </div>
            <div class="flex items-center gap-2">
                <i class="ri-signal-tower-line text-secondary text-[15px]"></i>
                <span class="font-heading text-secondary text-[10px] tracking-[0.2em] border border-secondary/40 rounded-[6px] px-1.5 py-0.5">ESIM</span>
            </div>
        </div>

        <div class="relative flex items-start justify-between gap-3">
            <div>
                <p class="font-sans text-white/55 text-[11px] uppercase tracking-wider">N-Cloud Calls</p>
                <p class="font-heading font-semibold text-white text-[30px] leading-none mt-1"><x-money :usd="(float) $balance" /></p>
            </div>
            <a href="{{ $withdrawUrl }}" wire:navigate @click.stop
               class="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-white/15 hover:bg-white/25 transition px-3 py-1.5 font-sans text-[12px] text-white"><i class="ri-arrow-right-up-line"></i> Withdraw</a>
        </div>

        <div class="relative flex items-end justify-between gap-3 pt-3 border-t border-white/10">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-7 rounded-[6px] bg-gradient-to-br from-secondary/70 to-secondary/30 grid grid-cols-3 gap-px p-1 shrink-0">
                    @for ($i = 0; $i < 6; $i++)<span class="bg-primary/40 rounded-[1px]"></span>@endfor
                </div>
                <div class="min-w-0">
                    <p class="font-sans text-secondary/80 text-[10px] uppercase tracking-wider">Virtual line</p>
                    <p class="font-heading font-semibold text-white text-[17px] tracking-wider tabular-nums truncate" x-text="show ? full : masked">{{ $user->cloud_line_masked }}</p>
                </div>
            </div>
            <span @click.stop="show = !show" class="shrink-0 flex items-center gap-1 text-secondary/80 hover:text-secondary transition cursor-pointer">
                <i class="text-[14px]" :class="show ? 'ri-eye-off-line' : 'ri-eye-line'"></i>
                <span class="font-sans text-[11px]" x-text="show ? 'Hide' : 'Show'">Show</span>
            </span>
        </div>

        <div class="relative grid grid-cols-3 gap-2 pt-3 border-t border-white/10">
            @foreach ([['PLAN', $planName], ['VALID', $validLabel], ['STATUS', $status]] as [$k, $v])
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="font-sans text-white/45 text-[9px] tracking-wider">{{ $k }}</span>
                    <span class="font-heading font-medium text-[12px] truncate {{ $k === 'STATUS' ? $statusColor : 'text-white' }}">{{ $v }}</span>
                </div>
            @endforeach
        </div>

        <span class="relative self-end font-sans text-white/40 text-[9px] tracking-[0.2em] pointer-events-none">TAP TO FLIP →</span>
    </div>

    {{-- BACK: absolute overlay, opacity-gated. --}}
    <div @click="flipped = false" :style="flipped ? 'opacity: 1 !important; pointer-events: auto' : 'opacity: 0 !important; pointer-events: none'"
         class="cursor-pointer absolute inset-0 transition-opacity duration-300 rounded-[22px] overflow-hidden bg-[#123a34] p-5 flex flex-col gap-4 shadow-[0_10px_30px_rgba(24,68,62,0.35)]">
        <div class="h-8 -mx-5 -mt-5 bg-black/40"></div>
        <div class="flex items-start justify-between gap-3">
            <div class="flex flex-col gap-0.5 min-w-0">
                <span class="font-sans text-white/45 text-[9px] tracking-wider">FULL NUMBER</span>
                <span class="font-heading font-semibold text-white text-[18px] tracking-wider tabular-nums">{{ $user->cloud_line_formatted ?? 'Not assigned' }}</span>
                @if ($lineQr !== '')
                    <span class="font-sans text-white/35 text-[9px] tracking-wider mt-0.5">SCAN TO SAVE THIS LINE</span>
                @endif
            </div>
            @if ($lineQr !== '')
                {{-- Encodes cloud_line_number; the white tile doubles as the QR's quiet zone. --}}
                <div class="shrink-0 rounded-[10px] bg-white p-2 size-[86px] flex items-center justify-center">
                    <div class="size-full [&>svg]:size-full [&>svg]:block">{!! $lineQr !!}</div>
                </div>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-3">
            @foreach ([['PLAN', $planName], ['STATUS', $status], ['LINES SAVED', $linesLabel], ['VALID', $validLabel], ['BALANCE', money((float) $balance)], ['EARNED TODAY', money((float) $todayEarned)]] as [$k, $v])
                <div class="flex flex-col gap-0.5">
                    <span class="font-sans text-white/45 text-[9px] tracking-wider">{{ $k }}</span>
                    <span class="font-heading font-medium text-[12px] {{ $k === 'STATUS' ? $statusColor : 'text-white' }}">{{ $v }}</span>
                </div>
            @endforeach
        </div>
        <span class="self-start font-sans text-white/40 text-[9px] tracking-[0.2em] pointer-events-none">← TAP TO FLIP BACK · NEXTEL CLOUD eSIM</span>
    </div>
</div>
