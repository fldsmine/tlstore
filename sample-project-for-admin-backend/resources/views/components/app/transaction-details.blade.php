{{-- Transaction receipt bottom-sheet; host exposes closeDetails(), shows only when `tx` is set. --}}
@props(['tx' => null])

@if ($tx)
    @php
        $statusBadge = [
            'completed' => ['bg-success/15', 'text-success', 'Completed'],
            'pending' => ['bg-[#d79937]/15', 'text-[#d79937]', 'Pending'],
            'failed' => ['bg-danger/15', 'text-danger', 'Failed'],
        ][$tx->status] ?? ['bg-muted/15', 'text-muted', ucfirst($tx->status)];
    @endphp
    <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll
         x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeDetails"></div>

        <div class="relative w-full sm:max-w-[400px] bg-surface rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-8 flex flex-col gap-5"
             x-show="shown"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

            <div class="flex items-center justify-between">
                <p class="font-heading font-medium text-text text-[16px]">Transaction Details</p>
                <button type="button" wire:click="closeDetails" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>
            </div>

            <div class="flex flex-col items-center gap-2.5 py-2">
                @if ($tx->network_logo)
                    <img src="{{ $tx->network_logo }}" alt="" class="size-14 rounded-full object-cover">
                @else
                    <span class="size-14 rounded-full flex items-center justify-center {{ $tx->is_credit ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger' }}"><i class="{{ $tx->type->icon() }} text-[26px]"></i></span>
                @endif
                <p class="font-heading font-semibold text-[30px] leading-none {{ $tx->is_credit ? 'text-success' : 'text-danger' }}">{{ $tx->is_credit ? '+' : '-' }}<x-money :usd="$tx->amount" :abbreviate="false" /></p>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 {{ $statusBadge[0] }}">
                    <span class="font-sans text-[12px] {{ $statusBadge[1] }}">{{ $statusBadge[2] }}</span>
                </span>
            </div>

            <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                @foreach (array_filter([
                    ['Type', $tx->type->getName()],
                    ['Description', $tx->description],
                    $tx->network_logo ? ['Network', $tx->meta['network'] ?? null] : null,
                    // VTpass bill payments: NGN face value + recipient/meter/smartcard.
                    isset($tx->meta['amount_ngn']) ? ['Amount (₦)', '₦'.number_format((float) $tx->meta['amount_ngn'], 2)] : null,
                    ! empty($tx->meta['recipient']) ? ['Recipient', $tx->meta['recipient']] : null,
                    (float) $tx->fee > 0 ? ['Fee', money((float) $tx->fee)] : null,
                    (float) $tx->fee > 0 ? ['Net Amount', money($tx->net_amount)] : null,
                    ['Date', $tx->created_at?->format('j M, Y · g:i A')],
                    ['Reference', $tx->reference],
                    // Electricity token / education pin, when returned.
                    ! empty($tx->meta['token']) ? ['Token', $tx->meta['token']] : null,
                ]) as [$label, $value])
                    @if ($value)
                        <div class="flex items-start justify-between gap-3 px-4 py-3">
                            <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                            <span class="font-sans text-text text-[13px] text-right break-words min-w-0">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            @isset($actions)
                {{ $actions }}
            @endisset

            <button type="button" wire:click="closeDetails" class="h-[50px] rounded-full bg-primary text-surface font-sans text-[15px] hover:opacity-95 transition">Done</button>
        </div>
    </div>
@endif
