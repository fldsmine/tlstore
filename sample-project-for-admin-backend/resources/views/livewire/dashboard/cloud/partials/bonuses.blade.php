{{-- Sponsor bonuses waiting to be claimed. Renders nothing when there is nothing to claim. --}}

@if ($bonuses->isNotEmpty())
    <div class="{{ $card }} overflow-hidden">
        <div class="flex items-center gap-2.5 px-5 py-4 border-b border-muted/10">
            <span class="size-10 rounded-[12px] bg-secondary/30 flex items-center justify-center text-primary shrink-0"><i class="ri-gift-line text-[20px]"></i></span>
            <div class="min-w-0 flex-1">
                <p class="font-heading text-text text-[15px]">Bonuses to claim</p>
                <p class="font-sans text-muted text-[12px] mt-0.5">Sponsors you saved shared a payout — claim it before it stacks up.</p>
            </div>
            <span class="shrink-0 font-heading text-primary text-[17px] tabular-nums"><x-money :usd="(float) $bonuses->sum('amount')" /></span>
        </div>

        @foreach ($bonuses as $b)
            <div wire:key="bonus-{{ $b->id }}" class="flex items-center gap-3 px-5 py-3.5 border-b border-muted/5 last:border-0">
                @if ($b->avatar_url)
                    <img src="{{ $b->avatar_url }}" alt="" class="size-10 rounded-[12px] object-cover bg-background shrink-0">
                @else
                    <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0 font-heading text-[14px]">{{ strtoupper(substr($b->sender_name, 0, 1)) }}</span>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="font-sans text-text text-[14px] truncate">{{ $b->sender_name }}</p>
                    <p class="font-sans text-[12px] truncate">
                        <span class="{{ $b->is_shareholder ? 'text-[#a87b1e]' : 'text-success' }}">{{ $b->is_shareholder ? 'Shareholder' : 'Promoter' }} bonus</span><span class="text-muted"> · pays into {{ $b->is_shareholder ? 'Nextel Shares' : 'N-Cloud Calls' }}</span>
                    </p>
                </div>
                <button type="button" wire:click="claimBonus('{{ $b->id }}')" wire:loading.attr="disabled" wire:target="claimBonus"
                        class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-primary text-surface font-sans text-[13px] hover:opacity-95 transition disabled:opacity-60">
                    <i class="ri-hand-coin-line"></i> Claim <x-money :usd="(float) $b->amount" />
                </button>
            </div>
        @endforeach
    </div>
@endif
