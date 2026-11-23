<div>
    <x-backoffice.page title="Withdrawals" subtitle="Review payouts and generate bank sheets">
        @php
            $canWrite = $this->canWrite();
            $PROCESSING = \App\Enums\WithdrawalStatus::PROCESSING;
            $PENDING = \App\Enums\WithdrawalStatus::PENDING;
            // Processing splits into Processing (blue) vs Exported (purple).
            $statusBadge = function ($w) use ($PROCESSING) {
                if ($w->status === $PROCESSING) {
                    return $w->exported_at
                        ? ['label' => 'Exported', 'tone' => 'purple']
                        : ['label' => 'Processing', 'tone' => 'blue'];
                }

                return ['label' => $w->status->getName(), 'tone' => match ($w->status) {
                    \App\Enums\WithdrawalStatus::PENDING => 'warning',
                    \App\Enums\WithdrawalStatus::COMPLETED => 'success',
                    \App\Enums\WithdrawalStatus::REJECTED => 'danger',
                    default => 'muted',
                }];
            };
            $closeBtn ='<button type="button" @click="open = false" class="size-8 rounded-full bg-background flex items-center justify-center text-muted shrink-0 hover:text-text transition"><i class="ri-close-line text-[18px]"></i></button>';
        @endphp

        <div class="flex flex-col gap-3">
            {{-- Status filters: one clean row of pills. --}}
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'pending' => 'Pending', 'processing' => 'Processing', 'exported' => 'Exported', 'completed' => 'Completed', 'rejected' => 'Rejected'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')"
                            class="px-3.5 h-[34px] rounded-full font-sans text-[12px] transition flex items-center gap-1.5 {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                        {{ $label }}
                        @if ($key === 'pending' && $pendingCount)<span class="rounded-full bg-[#d79937] text-white text-[10px] px-1.5">{{ $pendingCount }}</span>@endif
                        @if ($key === 'processing' && $processingCount)<span class="rounded-full bg-[#2f6feb] text-white text-[10px] px-1.5">{{ $processingCount }}</span>@endif
                        @if ($key === 'exported' && $exportedCount)<span class="rounded-full bg-[#7c5cff] text-white text-[10px] px-1.5">{{ $exportedCount }}</span>@endif
                    </button>
                @endforeach
            </div>

            {{-- Search + export scoping (single-currency + destination bank class). --}}
            <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm flex-1 min-w-0">
                    <i class="ri-search-line text-muted text-[17px]"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Reference, account, user…"
                           class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                </div>
                <div class="relative h-[44px] shrink-0 w-full sm:w-[160px]">
                    <select wire:model.live="currencyFilter" class="h-[44px] w-full rounded-[14px] bg-surface shadow-sm border-0 pl-3.5 pr-9 font-sans text-[13px] text-muted focus:ring-2 focus:ring-primary/40 appearance-none">
                        <option value="">All currencies</option>
                        @foreach ($currencies as $cur)
                            <option value="{{ $cur }}">{{ $cur }}</option>
                        @endforeach
                    </select>
                    <i class="ri-arrow-down-s-line text-[18px] text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
                <div class="relative h-[44px] shrink-0 w-full sm:w-[180px]">
                    <select wire:model.live="bankTypeFilter" class="h-[44px] w-full rounded-[14px] bg-surface shadow-sm border-0 pl-3.5 pr-9 font-sans text-[13px] text-muted focus:ring-2 focus:ring-primary/40 appearance-none">
                        <option value="">All banks</option>
                        <option value="commercial">Commercial</option>
                        <option value="non_commercial">Non-Commercial</option>
                    </select>
                    <i class="ri-arrow-down-s-line text-[18px] text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
                <div class="relative h-[44px] shrink-0 w-full sm:w-[130px]">
                    <select wire:model.live="perPage" class="h-[44px] w-full rounded-[14px] bg-surface shadow-sm border-0 pl-3.5 pr-9 font-sans text-[13px] text-muted focus:ring-2 focus:ring-primary/40 appearance-none">
                        @foreach ([10, 20, 50, 100, 250, 500] as $n)
                            <option value="{{ $n }}">{{ $n }} / page</option>
                        @endforeach
                    </select>
                    <i class="ri-arrow-down-s-line text-[18px] text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            </div>
        </div>

        @if ($canWrite && count($selected))
            <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between rounded-[14px] bg-primary/8 border border-primary/15 px-4 py-2.5">
                <span class="font-sans text-[13px] text-primary">
                    {{ count($selected) }} selected
                    @if (count($selectedCurrencies) === 1) · {{ $selectedCurrencies[0] }}
                    @elseif (count($selectedCurrencies) > 1)<span class="text-danger"> · {{ implode(', ', $selectedCurrencies) }} (mixed)</span>@endif
                </span>
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($status === 'pending')
                        <button type="button" @click="$dispatch('open-approve-bulk')" class="h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px] flex items-center gap-1.5"><i class="ri-check-double-line"></i> Approve selected</button>
                    @endif
                    @if ($status === 'processing')
                        <button type="button" @click="$dispatch('open-sheet')" class="h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px] flex items-center gap-1.5"><i class="ri-file-download-line"></i> Export to sheet</button>
                        <button type="button" @click="$dispatch('open-markexported-bulk')" class="h-[34px] px-3.5 rounded-full bg-[#7c5cff]/12 text-[#7c5cff] font-sans text-[12px] flex items-center gap-1.5"><i class="ri-check-line"></i> Mark as exported</button>
                    @endif
                    @if ($status === 'exported')
                        <button type="button" @click="$dispatch('open-sheet')" class="h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px] flex items-center gap-1.5"><i class="ri-file-download-line"></i> Re-export sheet</button>
                        <button type="button" @click="$dispatch('open-markpaid-bulk')" class="h-[34px] px-3.5 rounded-full bg-success text-white font-sans text-[12px] flex items-center gap-1.5"><i class="ri-checkbox-circle-line"></i> Mark as paid</button>
                    @endif
                    @if ($status === 'completed')
                        <button type="button" @click="$dispatch('open-sheet')" class="h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px] flex items-center gap-1.5"><i class="ri-file-download-line"></i> Download sheet</button>
                    @endif
                    {{-- Exported rows (payout in flight) can only be marked paid, never rejected. --}}
                    @if (in_array($status, ['pending', 'processing'], true))
                        <button type="button" @click="$dispatch('open-reject-bulk')" class="h-[34px] px-3.5 rounded-full bg-danger/10 text-danger font-sans text-[12px] flex items-center gap-1.5"><i class="ri-close-circle-line"></i> Reject selected</button>
                    @endif
                </div>
            </div>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($withdrawals->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-money-dollar-circle-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No withdrawals here.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                @if ($canWrite)
                                    <th class="px-4 py-3 w-10"><input type="checkbox" wire:model.live="selectPage" class="rounded border-muted/30 text-primary focus:ring-primary/40"></th>
                                @endif
                                <th class="px-4 py-3 font-medium">User / Reference</th>
                                <th class="px-4 py-3 font-medium">Bank</th>
                                <th class="px-4 py-3 font-medium text-right">Amount</th>
                                <th class="px-4 py-3 font-medium">Wallet</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($withdrawals as $w)
                                @php $b = $statusBadge($w); @endphp
                                <tr wire:key="w-{{ $w->id }}" wire:click="openDetail('{{ $w->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/40 cursor-pointer">
                                    @if ($canWrite)
                                        <td class="px-4 py-3" @click.stop>
                                            <input type="checkbox" value="{{ $w->id }}" wire:model.live="selected" class="rounded border-muted/30 text-primary focus:ring-primary/40">
                                        </td>
                                    @endif
                                    <td class="px-4 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $w->user?->username ? '@'.$w->user->username : '—' }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $w->reference }} · {{ $w->created_at?->format('j M, g:i A') }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $w->bank_name ?: '—' }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $w->account_number }} · {{ $w->account_name }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <p class="font-heading text-text text-[13px]">{{ money($w->amount_paid) }}</p>
                                        @if (abs((float) $w->amount_paid - (float) $w->amount) >= 0.01)<p class="font-sans text-muted text-[11px]">{{ (float) $w->amount_paid > (float) $w->amount ? 'incl. bonus' : 'net of fee' }} · from {{ money($w->amount) }}</p>@endif
                                        <p class="font-sans text-primary text-[11px]">{{ $w->local_currency ?: 'NGN' }} {{ number_format((float) ($w->local_amount_paid ?? $w->amount_paid), 2) }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-sans text-muted text-[12px]">{{ $w->wallet_label }}</td>
                                    <td class="px-4 py-3">
                                        <x-backoffice.badge :tone="$b['tone']">{{ $b['label'] }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-4 py-3 text-right"><i class="ri-arrow-right-s-line text-muted text-[18px]"></i></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($withdrawals->hasPages())
            <div>{{ $withdrawals->links('vendor.pagination.nextel') }}</div>
        @endif

        {{-- Detail modal (z-70/80) sits below the action modals so those layer on top without closing it. --}}
        <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-detail.window="open = true" @close-detail.window="open = false" @keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity class="fixed inset-0 z-[70] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
            <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[480px] z-[80] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)] max-h-[88vh] overflow-y-auto">
                @if ($this->detailTarget)
                    @php $t = $this->detailTarget; $tb = $statusBadge($t); $tDeductAt = $t->meta['deduct_at'] ?? config("settings.withdrawal.wallets.{$t->wallet}.deduct_at", 'request'); @endphp
                    <div class="flex items-start justify-between gap-3 mb-4">
                        @if ($t->user)
                            <a href="{{ route('backoffice.users.show', $t->user) }}" wire:navigate class="min-w-0 hover:opacity-80 transition">
                                <x-backoffice.user-cell :user="$t->user" />
                                <p class="font-sans text-muted text-[11px] mt-1.5">{{ $t->reference }}</p>
                            </a>
                        @else
                            <div class="min-w-0">
                                <x-backoffice.user-cell :user="null" />
                                <p class="font-sans text-muted text-[11px] mt-1.5">{{ $t->reference }}</p>
                            </div>
                        @endif
                        <div class="flex items-center gap-2 shrink-0">
                            <x-backoffice.badge :tone="$tb['tone']">{{ $tb['label'] }}</x-backoffice.badge>
                            {!! $closeBtn !!}
                        </div>
                    </div>

                    <div class="rounded-[14px] bg-background border border-muted/10 divide-y divide-muted/10 mb-4">
                        @php $row = 'flex items-start justify-between gap-3 px-3.5 py-2.5'; $lbl = 'font-sans text-muted text-[12px]'; $val = 'font-sans text-text text-[13px] text-right'; @endphp
                        <div class="{{ $row }}"><span class="{{ $lbl }}">Bank</span><span class="{{ $val }}">{{ $t->bank_name ?: '—' }}<span class="block text-muted text-[11px]">{{ $t->account_number }} · {{ $t->account_name }}</span></span></div>
                        <div class="{{ $row }}"><span class="{{ $lbl }}">Wallet</span><span class="{{ $val }}">{{ $t->wallet_label }}</span></div>
                        <div class="{{ $row }}"><span class="{{ $lbl }}">Requested</span><span class="font-heading text-text text-[13px]">{{ money($t->amount) }}</span></div>
                        @if ((float) $t->fee > 0)
                            <div class="{{ $row }}"><span class="{{ $lbl }}">Fee</span><span class="{{ $val }}">{{ money($t->fee) }}</span></div>
                        @elseif ((float) $t->amount_paid > (float) $t->amount)
                            <div class="{{ $row }}"><span class="{{ $lbl }}">Bonus</span><span class="font-heading text-success text-[13px]">+{{ money((float) $t->amount_paid - (float) $t->amount) }}</span></div>
                        @endif
                        @if (abs((float) $t->amount_paid - (float) $t->amount) >= 0.01)
                            <div class="{{ $row }}"><span class="{{ $lbl }}">Net payout</span><span class="font-heading text-text text-[13px]">{{ money($t->amount_paid) }}</span></div>
                        @endif
                        <div class="{{ $row }}"><span class="{{ $lbl }}">Local Amount</span><span class="font-heading text-text text-[13px]">{{ $t->local_currency ?: 'NGN' }} {{ number_format((float) ($t->local_amount_paid ?? $t->amount_paid), 2) }}</span></div>
                        <div class="{{ $row }}"><span class="{{ $lbl }}">Debit timing</span><span class="{{ $val }}">{{ $tDeductAt === 'approval' ? 'On approval' : 'On request' }}</span></div>
                        <div class="{{ $row }}"><span class="{{ $lbl }}">Requested at</span><span class="{{ $val }}">{{ $t->created_at?->format('j M Y, g:i A') }}</span></div>
                        @if ($t->exported_at)
                            <div class="{{ $row }}"><span class="{{ $lbl }}">Exported at</span><span class="{{ $val }}">{{ $t->exported_at->format('j M Y, g:i A') }}</span></div>
                        @endif
                        @if ($t->processed_at)
                            <div class="{{ $row }}"><span class="{{ $lbl }}">{{ $t->status === \App\Enums\WithdrawalStatus::REJECTED ? 'Rejected at' : 'Completed at' }}</span><span class="{{ $val }}">{{ $t->processed_at->format('j M Y, g:i A') }}</span></div>
                        @endif
                        @if ($t->status === \App\Enums\WithdrawalStatus::REJECTED && ($t->meta['rejection_reason'] ?? null))
                            <div class="{{ $row }}"><span class="{{ $lbl }}">Reason</span><span class="{{ $val }} text-danger">{{ $t->meta['rejection_reason'] }}</span></div>
                        @endif
                    </div>

                    @if ($canWrite && $t->status->isInProgress())
                        @if ($t->status === $PENDING)
                            <p class="font-sans text-muted text-[12px] mb-3 flex items-start gap-1.5"><i class="ri-information-line text-[14px] mt-px"></i> Not yet debited — approving debits the wallet and moves this payout to Processing.</p>
                        @endif
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            @if ($t->status === $PENDING)
                                <button type="button" wire:click="openReject('{{ $t->id }}')" class="h-[40px] px-4 rounded-full bg-danger/10 text-danger font-sans text-[13px]">Reject</button>
                                <button type="button" wire:click="openApprove('{{ $t->id }}')" class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px]">Approve</button>
                            @elseif ($t->exported_at)
                                {{-- Exported (on a sheet) — mark paid only, never reject --}}
                                <button type="button" wire:click="openMarkPaid('{{ $t->id }}')" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px]">Mark as paid</button>
                            @else
                                <button type="button" wire:click="openReject('{{ $t->id }}')" class="h-[40px] px-4 rounded-full bg-danger/10 text-danger font-sans text-[13px]">Reject</button>
                                <button type="button" wire:click="openMarkExported('{{ $t->id }}')" class="h-[40px] px-4 rounded-full bg-[#7c5cff]/12 text-[#7c5cff] font-sans text-[13px]">Mark as exported</button>
                                <button type="button" wire:click="openMarkPaid('{{ $t->id }}')" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px]">Mark as paid</button>
                            @endif
                        </div>
                    @else
                        <div class="flex justify-end">
                            <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Close</button>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        @if ($canWrite)
            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-reject.window="open = true" @close-reject.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[420px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Reject withdrawal</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Review the payout below, then confirm. This cannot be undone.</p>
                    @if ($this->rejectTarget)
                        @php $t = $this->rejectTarget; @endphp
                        <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="font-sans text-muted text-[12px]">User</span>
                                <span class="font-sans text-text text-[13px]">{{ $t->user?->username ? '@'.$t->user->username : '—' }} · {{ $t->reference }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-sans text-muted text-[12px]">Amount</span>
                                <span class="font-heading text-text text-[13px]">{{ money($t->amount) }} <span class="font-sans text-muted text-[11px]">· {{ $t->wallet_label }}</span></span>
                            </div>
                            <p class="font-sans text-[11px] pt-0.5 {{ $this->rejectRefund['will_refund'] ? 'text-success' : 'text-muted' }}">
                                @if ($this->rejectRefund['will_refund'])
                                    <i class="ri-refund-2-line"></i> {{ money($this->rejectRefund['amount']) }} will be refunded to the user's {{ $t->wallet_label }} wallet.
                                @else
                                    <i class="ri-information-line"></i> Nothing has been deducted yet — no refund needed.
                                @endif
                            </p>
                        </div>
                    @endif
                    <form wire:submit="confirmReject" class="flex flex-col gap-3">
                        <textarea wire:model="rejectReason" rows="3" placeholder="Reason for rejection (optional)…"
                                  class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                        @error('rejectReason') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        <div class="flex items-center justify-end gap-2 mt-1">
                            <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="confirmReject" class="h-[40px] px-4 rounded-full bg-danger text-white font-sans text-[13px] disabled:opacity-60">
                                <span wire:loading.remove wire:target="confirmReject">Reject</span>
                                <span wire:loading wire:target="confirmReject" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Deduct amount (wallet) and pay amount (bank/sheet) may differ. --}}
            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-approve.window="open = true" @close-approve.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[440px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Approve withdrawal</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Set how much to deduct from the user's wallet and how much to pay out, then approve to move this payout to Processing.</p>
                    @if ($this->approveTarget)
                        @php $t = $this->approveTarget; @endphp
                        <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="font-sans text-muted text-[12px]">User</span>
                                <span class="font-sans text-text text-[13px]">{{ $t->user?->username ? '@'.$t->user->username : '—' }} · {{ $t->reference }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-sans text-muted text-[12px]">Bank</span>
                                <span class="font-sans text-text text-[13px] text-right">{{ $t->bank_name }}<span class="block text-muted text-[11px]">{{ $t->account_number }} · {{ $t->account_name }}</span></span>
                            </div>
                            <div class="flex items-center justify-between border-t border-muted/10 pt-2">
                                <span class="font-sans text-muted text-[12px]">Requested · {{ $t->wallet_label }}</span>
                                <span class="font-heading text-text text-[13px]">{{ money($t->amount) }}@if ((float) $t->fee > 0)<span class="font-sans text-muted text-[11px]"> · net {{ money($t->amount_paid) }}</span>@endif</span>
                            </div>
                            <p class="font-sans text-[11px] pt-0.5 {{ $this->approveDeductAt === 'approval' ? 'text-[#d79937]' : 'text-muted' }}">
                                @if ($this->approveDeductAt === 'approval')
                                    <i class="ri-information-line"></i> This wallet debits on approval — the wallet will be debited now.
                                @else
                                    <i class="ri-information-line"></i> Debited at request — the gross is already held; any difference is reconciled.
                                @endif
                            </p>
                        </div>
                    @endif
                    <form wire:submit="confirmApprove" class="flex flex-col gap-3.5">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Amount to deduct ($)</label>
                                <input type="number" step="any" wire:model="amountToDeduct" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                @error('amountToDeduct') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Amount to pay ($)</label>
                                <input type="number" step="any" wire:model="amountToPay" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                @error('amountToPay') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <span class="font-sans text-[11px] text-muted">Deduct = removed from the wallet · Pay = sent to the bank (on the payment sheet). They may differ.</span>
                        <div class="flex items-center justify-end gap-2 mt-1">
                            <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="confirmApprove" class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                                <span wire:loading.remove wire:target="confirmApprove">Approve</span>
                                <span wire:loading wire:target="confirmApprove" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Approving…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-markpaid.window="open = true" @close-markpaid.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[420px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Mark as paid</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Confirm the payout has been sent to the bank. This completes the withdrawal.</p>
                    @if ($this->markPaidTarget)
                        @php $t = $this->markPaidTarget; @endphp
                        <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="font-sans text-muted text-[12px]">User</span>
                                <span class="font-sans text-text text-[13px]">{{ $t->user?->username ? '@'.$t->user->username : '—' }} · {{ $t->reference }}</span>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <span class="font-sans text-muted text-[12px]">Amount Paid</span>
                                <span class="font-heading text-text text-[13px] text-right">{{ money($t->amount_paid) }}<span class="block font-sans text-muted text-[11px]">{{ $t->bank_name }} · {{ $t->account_number }}</span></span>
                            </div>
                        </div>
                    @endif
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="confirmMarkPaid" wire:loading.attr="disabled" wire:target="confirmMarkPaid" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="confirmMarkPaid">Mark as paid</span>
                            <span wire:loading wire:target="confirmMarkPaid" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-markexported.window="open = true" @close-markexported.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[420px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Mark as exported</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Flag this payout as exported (handled outside the sheet flow) and move it to the Exported tab. No sheet is generated.</p>
                    @if ($this->markExportedTarget)
                        @php $t = $this->markExportedTarget; @endphp
                        <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex items-center justify-between mb-4">
                            <span class="font-sans text-text text-[13px]">{{ $t->user?->username ? '@'.$t->user->username : '—' }} <span class="text-muted text-[11px]">· {{ $t->reference }}</span></span>
                            <span class="font-heading text-text text-[13px]">{{ money($t->amount_paid) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="confirmMarkExported" wire:loading.attr="disabled" wire:target="confirmMarkExported" class="h-[40px] px-4 rounded-full bg-[#7c5cff] text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="confirmMarkExported">Mark as exported</span>
                            <span wire:loading wire:target="confirmMarkExported" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-approve-bulk.window="open = true" @close-approve-bulk.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[460px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    @php $bp = $this->bulkApprovePreview; @endphp
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Approve {{ $bp['count'] }} withdrawal{{ $bp['count'] === 1 ? '' : 's' }}</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Each selected request is approved — the user's wallet is debited and the payout moves to Processing (requested amount deducted, net paid out).</p>
                    <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-3">
                        <div class="flex items-center justify-between">
                            <span class="font-sans text-muted text-[12px]">Total to deduct</span>
                            <span class="font-heading text-text text-[14px]">{{ money($bp['deduct']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-sans text-muted text-[12px]">Total to pay</span>
                            <span class="font-heading text-text text-[14px]">{{ money($bp['pay']) }}</span>
                        </div>
                    </div>
                    @if ($bp['count'])
                        <div class="max-h-[180px] overflow-y-auto flex flex-col gap-1.5 mb-4 pr-1">
                            @foreach ($bp['rows'] as $r)
                                <div class="flex items-center justify-between font-sans text-[12px]">
                                    <span class="text-text truncate">{{ $r->user?->username ? '@'.$r->user->username : '—' }} <span class="text-muted">· {{ $r->reference }}</span></span>
                                    <span class="text-text whitespace-nowrap">{{ money($r->amount) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="approveSelected" wire:loading.attr="disabled" wire:target="approveSelected" class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="approveSelected">Approve {{ $bp['count'] }}</span>
                            <span wire:loading wire:target="approveSelected" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Approving…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-markpaid-bulk.window="open = true" @close-markpaid-bulk.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[460px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    @php $mp = $this->bulkMarkPaidPreview; @endphp
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Mark {{ $mp['count'] }} withdrawal{{ $mp['count'] === 1 ? '' : 's' }} as paid</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Confirm these payouts have been sent to their banks. This completes them.</p>
                    <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex items-center justify-between mb-4">
                        <span class="font-sans text-muted text-[12px]">Total paid out</span>
                        <span class="font-heading text-text text-[14px]">{{ money($mp['pay']) }}</span>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="markCompletedSelected" wire:loading.attr="disabled" wire:target="markCompletedSelected" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="markCompletedSelected">Mark {{ $mp['count'] }} as paid</span>
                            <span wire:loading wire:target="markCompletedSelected" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-markexported-bulk.window="open = true" @close-markexported-bulk.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[440px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    @php $me = $this->bulkMarkExportedPreview; @endphp
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Mark {{ $me['count'] }} withdrawal{{ $me['count'] === 1 ? '' : 's' }} as exported</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Flag the selected payouts as exported (handled outside the sheet flow) and move them to the Exported tab. No sheet is generated.</p>
                    <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex items-center justify-between mb-4">
                        <span class="font-sans text-muted text-[12px]">Total payout</span>
                        <span class="font-heading text-text text-[14px]">{{ money($me['pay']) }}</span>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="markExportedSelected" wire:loading.attr="disabled" wire:target="markExportedSelected" class="h-[40px] px-4 rounded-full bg-[#7c5cff] text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="markExportedSelected">Mark {{ $me['count'] }} as exported</span>
                            <span wire:loading wire:target="markExportedSelected" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-reject-bulk.window="open = true" @close-reject-bulk.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[460px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    @php $rp = $this->bulkRejectPreview; @endphp
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Reject {{ $rp['count'] }} withdrawal{{ $rp['count'] === 1 ? '' : 's' }}</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Any amount already deducted is refunded to each user's wallet. Already-exported rows are skipped. This cannot be undone.</p>
                    <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-3">
                        <div class="flex items-center justify-between">
                            <span class="font-sans text-muted text-[12px]">Total requested</span>
                            <span class="font-heading text-text text-[14px]">{{ money($rp['amount']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-sans text-muted text-[12px]">Total to refund</span>
                            <span class="font-heading text-success text-[14px]">{{ money($rp['refund']) }}</span>
                        </div>
                    </div>
                    @if ($rp['count'])
                        <div class="max-h-[150px] overflow-y-auto flex flex-col gap-1.5 mb-4 pr-1">
                            @foreach ($rp['rows'] as $r)
                                <div class="flex items-center justify-between font-sans text-[12px]">
                                    <span class="text-text truncate">{{ $r->user?->username ? '@'.$r->user->username : '—' }} <span class="text-muted">· {{ $r->reference }}</span></span>
                                    <span class="text-text whitespace-nowrap">{{ money($r->amount) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <form wire:submit="rejectSelected" class="flex flex-col gap-3">
                        <textarea wire:model="bulkRejectReason" rows="3" placeholder="Reason for rejection (optional)…"
                                  class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                        @error('bulkRejectReason') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="rejectSelected" class="h-[40px] px-4 rounded-full bg-danger text-white font-sans text-[13px] disabled:opacity-60">
                                <span wire:loading.remove wire:target="rejectSelected">Reject {{ $rp['count'] }}</span>
                                <span wire:loading wire:target="rejectSelected" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Rejecting…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-sheet.window="open = true" @close-sheet.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[420px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">{{ $status === 'completed' ? 'Download payment sheet' : 'Generate payment sheet' }}</p>
                        {!! $closeBtn !!}
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">
                        @if ($status === 'completed')
                            Download a payment sheet of the {{ count($selected) }} selected completed payout(s). This is a read-only export — nothing changes.
                        @else
                            {{ count($selected) }} withdrawal(s) will be exported to a payment sheet and moved to the Exported tab. Re-exporting regenerates the sheet.
                        @endif
                    </p>
                    @php
                        $mixedCurrency = count($selectedCurrencies) > 1;
                        // NGN never shares a sheet — a mix containing it is blocked outright.
                        $mixedWithNgn = $mixedCurrency && in_array('NGN', $selectedCurrencies, true);
                    @endphp
                    @if ($mixedWithNgn)
                        <div class="flex items-start gap-2 rounded-[12px] bg-danger/10 border border-danger/20 px-3 py-2.5 mb-4">
                            <i class="ri-error-warning-line text-danger text-[16px] shrink-0 mt-0.5"></i>
                            <p class="font-sans text-danger text-[12px] leading-snug">Selection mixes NGN with other currencies ({{ implode(', ', $selectedCurrencies) }}). NGN payouts must be exported on their own sheet — filter to NGN, or exclude it.</p>
                        </div>
                    @elseif ($mixedCurrency)
                        {{-- Foreign-only mix: the General Bank Sheet carries per-row currency + country, so it
                             can hold it. The Nigerian bank templates can't — the server rejects that combo. --}}
                        <div class="flex items-start gap-2 rounded-[12px] bg-warning/10 border border-warning/20 px-3 py-2.5 mb-4">
                            <i class="ri-error-warning-line text-warning text-[16px] shrink-0 mt-0.5"></i>
                            <p class="font-sans text-text/80 text-[12px] leading-snug">Selection spans multiple currencies ({{ implode(', ', $selectedCurrencies) }}). The <span class="font-heading text-text">General Bank Sheet</span> exports them together — each row carries its own currency and country. The Nigerian bank templates hold one currency only.</p>
                        </div>
                    @elseif (count($selectedCurrencies) === 1)
                        <p class="font-sans text-muted text-[12px] -mt-2 mb-4">Payout currency: <span class="font-heading text-text">{{ $selectedCurrencies[0] }}</span></p>
                    @endif
                    <form wire:submit="generateSheet" class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Processor</label>
                            <select wire:model="sheetProcessor" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                @foreach ($processors as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Format</label>
                            <select wire:model="sheetFormat" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                @foreach ($formats as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="flex items-center justify-end gap-2 mt-1">
                            <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                            <button type="submit" @disabled($mixedWithNgn) wire:loading.attr="disabled" wire:target="generateSheet" class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="generateSheet">Generate & download</span>
                                <span wire:loading wire:target="generateSheet" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Generating…</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-backoffice.page>
</div>
