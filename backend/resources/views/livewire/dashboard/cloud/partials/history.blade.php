{{-- Call history — paginated, newest first. --}}

<div class="{{ $card }} overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-muted/10">
        <div class="min-w-0">
            <p class="font-heading text-text text-[15px]">Call history</p>
            <p class="font-sans text-muted text-[12px] mt-0.5">Every sponsored call you placed or answered.</p>
        </div>
        @if ($history->total() > 0)
            <span class="shrink-0 font-sans text-muted text-[12px]">{{ $history->total() }} {{ Str::plural('call', $history->total()) }}</span>
        @endif
    </div>

    @forelse ($history as $call)
        @php
            $tone = $call->status->tone();
            $toneClass = $tone === 'success' ? 'text-success' : ($tone === 'danger' ? 'text-danger' : 'text-muted');
        @endphp
        <div wire:key="call-{{ $call->id }}" class="flex items-center gap-3 px-5 py-3.5 border-b border-muted/5 last:border-0">
            <span class="size-10 rounded-[13px] bg-background flex items-center justify-center shrink-0 {{ $toneClass }}">
                <i class="ri-{{ $call->direction === 'incoming' ? 'phone-fill' : 'phone-line' }} text-[17px]"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-sans text-text text-[14px] truncate">{{ $call->number_name }}</p>
                <p class="font-sans text-muted text-[11.5px] truncate">{{ ucfirst($call->direction) }} · {{ $call->created_at->format('j M Y, g:i A') }} · {{ $call->duration_seconds }}s</p>
            </div>
            <div class="text-right shrink-0">
                @if ((float) $call->earned > 0)
                    <p class="font-heading text-success text-[13px] tabular-nums">+<x-money :usd="(float) $call->earned" /></p>
                @endif
                <p class="font-sans text-[11.5px] {{ $toneClass }}">{{ $call->status->label() }}</p>
            </div>
        </div>
    @empty
        <div class="px-5 py-12 text-center flex flex-col items-center gap-3">
            <span class="size-14 rounded-[18px] bg-background inline-flex items-center justify-center text-muted"><i class="ri-phone-line text-[24px]"></i></span>
            <div>
                <p class="font-heading text-text text-[15px]">No calls yet</p>
                <p class="font-sans text-muted text-[13px] mt-1 leading-relaxed max-w-[300px] mx-auto">Once you call a sponsor and listen to the full message, it shows up here with what you earned.</p>
            </div>
        </div>
    @endforelse

    @if ($history->hasPages())
        <div class="px-5 py-3 border-t border-muted/10">{{ $history->links('vendor.pagination.nextel') }}</div>
    @endif
</div>
