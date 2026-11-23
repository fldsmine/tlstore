<div>
    <x-backoffice.page title="Manual deposits" subtitle="Verify bank-transfer top-ups before crediting users">
        <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[420px]">
            <i class="ri-search-line text-muted text-[17px]"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user, bank or provider reference…"
                   class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
            @endif
        </div>

        @forelse ($deposits as $deposit)
            <x-backoffice.card wire:key="deposit-{{ $deposit->id }}">
                <div class="flex flex-col md:flex-row md:items-start gap-4">
                    <div class="flex-1 min-w-0 flex flex-col gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-bank-line text-[20px]"></i></span>
                            <div class="min-w-0">
                                <p class="font-heading font-semibold text-text text-[16px] leading-tight"><x-backoffice.money :minor="$deposit->amount_minor" :currency="$deposit->currency" /></p>
                                <p class="font-sans text-muted text-[12px] truncate">{{ $deposit->user?->full_name }} ({{ '@'.$deposit->user?->username }}) · {{ optional($deposit->submitted_at)->format('j M Y, H:i') }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[12px] font-sans">
                            <span class="text-muted">Bank ref: <code class="text-text">{{ $deposit->bank_reference ?: '—' }}</code></span>
                            <span class="text-muted">Provider: <span class="text-text">{{ $deposit->paymentIntent?->provider }}{{ $deposit->paymentIntent?->provider_reference ? ' · '.$deposit->paymentIntent->provider_reference : '' }}</span></span>
                            @if ($deposit->proof_storage_path)
                                <a href="{{ route('backoffice.moderation.deposits.proof', $deposit) }}" target="_blank" rel="noopener" class="text-primary underline inline-flex items-center gap-1"><i class="ri-image-line"></i> View payment proof</a>
                            @else
                                <span class="text-muted italic">No proof uploaded</span>
                            @endif
                        </div>
                    </div>

                    <div class="md:w-[280px] shrink-0 flex flex-col gap-2">
                        <label class="label" for="deposit-reason-{{ $deposit->id }}">Reason <span class="text-muted">(required to reject)</span></label>
                        <textarea id="deposit-reason-{{ $deposit->id }}" wire:model="reasons.{{ $deposit->id }}" rows="2" class="input" placeholder="Why is this deposit being rejected?"></textarea>
                        @error('reasons.'.$deposit->id)<span class="error">{{ $message }}</span>@enderror
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-primary btn-md flex-1" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'approve', params: [{{ $deposit->id }}], title: 'Approve deposit?', message: 'Funds will be credited to the user wallet immediately and a notification is sent. Verify the proof matches the amount first.', confirmText: 'Approve & credit', tone: 'primary' })">
                                <i class="ri-check-line"></i> Approve
                            </button>
                            <button type="button" wire:click="reject({{ $deposit->id }})" class="btn btn-danger btn-md flex-1">
                                <i class="ri-close-line"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </x-backoffice.card>
        @empty
            <x-backoffice.card>
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-success"><i class="ri-checkbox-circle-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No pending manual deposits.</p>
                </div>
            </x-backoffice.card>
        @endforelse

        @if ($deposits->hasPages())
            <div>{{ $deposits->links('vendor.pagination.backoffice') }}</div>
        @endif
    </x-backoffice.page>
</div>
