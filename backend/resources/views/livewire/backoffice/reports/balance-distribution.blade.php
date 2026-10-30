<div>
    <x-backoffice.card title="Users by balance range">
        <div class="flex flex-wrap items-end gap-3 -mt-1 mb-4">
            <label class="flex flex-col gap-1">
                <span class="font-sans text-muted text-[11px]">Wallet</span>
                <select wire:model.live="distWallet" class="h-[36px] rounded-[10px] bg-background border border-muted/15 pl-3 pr-8 font-sans text-[13px] text-text outline-none focus:border-primary">
                    @foreach ($walletOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1">
                <span class="font-sans text-muted text-[11px]">Range size ($)</span>
                <select wire:model.live="distStep" class="h-[36px] rounded-[10px] bg-background border border-muted/15 pl-3 pr-8 font-sans text-[13px] text-text outline-none focus:border-primary">
                    @foreach ($stepOptions as $s)
                        <option value="{{ $s }}">{{ number_format($s) }}</option>
                    @endforeach
                </select>
            </label>
            <span class="ml-auto pb-2 font-sans text-muted text-[11px]" wire:loading.remove wire:target="distWallet,distStep">Balances in USD · users per range</span>
            <span class="ml-auto pb-2 font-sans text-primary text-[11px] inline-flex items-center gap-1" wire:loading wire:target="distWallet,distStep"><i class="ri-loader-4-line animate-spin"></i> Updating…</span>
        </div>
        <div class="flex flex-col gap-2.5 overflow-y-auto pr-1" style="max-height: 360px">
            @forelse ($distribution as $r)
                <div class="flex items-center gap-3">
                    <span class="font-sans text-text text-[12.5px] w-28 sm:w-36 shrink-0 tabular-nums">{{ $r['label'] }}</span>
                    <div class="flex-1 h-2.5 rounded-full bg-background overflow-hidden">
                        <div class="h-full rounded-full {{ ($r['over'] ?? false) ? 'bg-warning' : 'bg-primary' }}" style="width: {{ max($r['pct'], $r['count'] > 0 ? 2 : 0) }}%"></div>
                    </div>
                    <span class="font-heading text-text text-[13px] w-16 text-right shrink-0 tabular-nums">{{ number_format($r['count']) }}</span>
                </div>
            @empty
                <p class="font-sans text-muted text-[13px] py-3 text-center">No users yet.</p>
            @endforelse
        </div>
    </x-backoffice.card>
</div>
