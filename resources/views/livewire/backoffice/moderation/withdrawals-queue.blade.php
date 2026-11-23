<div>
    <x-backoffice.page title="Withdrawals" subtitle="Settle pending payout requests">
        <div class="rounded-[16px] border border-[#d79937]/25 bg-[#d79937]/10 px-4 py-3 flex items-start gap-3">
            <i class="ri-information-line text-[#d79937] text-[19px] shrink-0 mt-0.5"></i>
            <p class="font-sans text-[13px] text-text">Marking a request <strong>paid</strong> records that the payout was sent through your external rail — it does <strong>not</strong> transfer money by itself. <strong>Rejecting</strong> releases the reserved funds back to the user wallet. Every decision is audited.</p>
        </div>

        <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[420px]">
            <i class="ri-search-line text-muted text-[17px]"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user, payout option or reference…"
                   class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
            @endif
        </div>

        @forelse ($withdrawals as $withdrawal)
            @php
                $payout = is_array($withdrawal->payout_details) ? $withdrawal->payout_details : [];
                $netMinor = max(0, (int) $withdrawal->amount_minor - (int) $withdrawal->fee_minor);
            @endphp
            <x-backoffice.card wire:key="withdrawal-{{ $withdrawal->id }}">
                <div class="flex flex-col md:flex-row md:items-start gap-4">
                    <div class="flex-1 min-w-0 flex flex-col gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-arrow-right-up-line text-[20px]"></i></span>
                            <div class="min-w-0">
                                <p class="font-heading font-semibold text-text text-[16px] leading-tight"><x-backoffice.money :minor="$withdrawal->amount_minor" :currency="$withdrawal->currency" /></p>
                                <p class="font-sans text-muted text-[12px] truncate">{{ $withdrawal->user?->full_name }} ({{ '@'.$withdrawal->user?->username }}) · {{ optional($withdrawal->requested_at)->format('j M Y, H:i') }}</p>
                            </div>
                            <x-backoffice.badge tone="warning">Pending</x-backoffice.badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[12px] font-sans">
                            <span class="text-muted">Option: <span class="text-text">{{ $withdrawal->option?->name ?? '—' }}</span></span>
                            <span class="text-muted">Fee: <span class="text-text"><x-backoffice.money :minor="$withdrawal->fee_minor" :currency="$withdrawal->currency" /></span></span>
                            <span class="text-muted">Net: <span class="text-text"><x-backoffice.money :minor="$netMinor" :currency="$withdrawal->currency" /></span></span>
                        </div>

                        @if ($payout)
                            <details class="rounded-[14px] bg-background p-3">
                                <summary class="cursor-pointer font-sans text-[12px] text-primary inline-flex items-center gap-1.5 select-none"><i class="ri-bank-card-line"></i> Payout details</summary>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 mt-2">
                                    @foreach ($payout as $key => $value)
                                        <div class="flex items-baseline justify-between gap-4 border-b border-muted/10 last:border-0 py-1">
                                            <dt class="font-sans text-[11px] text-muted">{{ \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', (string) $key)) }}</dt>
                                            <dd class="font-mono text-[11px] text-text text-right break-all">{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </details>
                        @endif
                    </div>

                    <div class="md:w-[300px] shrink-0 flex flex-col gap-2">
                        <label class="label" for="ref-{{ $withdrawal->id }}">Provider reference <span class="text-muted">(optional)</span></label>
                        <input id="ref-{{ $withdrawal->id }}" type="text" wire:model="references.{{ $withdrawal->id }}" class="input" placeholder="e.g. payout batch / transfer ID">
                        <label class="label" for="reason-{{ $withdrawal->id }}">Reason <span class="text-muted">(required to reject)</span></label>
                        <textarea id="reason-{{ $withdrawal->id }}" wire:model="reasons.{{ $withdrawal->id }}" rows="2" class="input" placeholder="Why is this withdrawal being rejected?"></textarea>
                        @error('reasons.'.$withdrawal->id)<span class="error">{{ $message }}</span>@enderror
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-primary btn-md flex-1"
                                    x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'markPaid', params: [{{ $withdrawal->id }}], title: 'Mark as paid?', message: 'Records this payout as sent and posts the reservation. This does NOT move money — send it through your provider first.', confirmText: 'Mark paid', tone: 'primary' })">
                                <i class="ri-check-line"></i> Paid
                            </button>
                            <button type="button" wire:click="reject({{ $withdrawal->id }})" class="btn btn-danger btn-md flex-1">
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
                    <p class="font-sans text-muted text-[14px]">No pending withdrawals.</p>
                </div>
            </x-backoffice.card>
        @endforelse

        @if ($withdrawals->hasPages())
            <div>{{ $withdrawals->links('vendor.pagination.backoffice') }}</div>
        @endif
    </x-backoffice.page>
</div>
