<div>
    <x-backoffice.page title="Loans" subtitle="Review applications and manage manual disbursements">
        @php
            $canWrite = $this->canWrite();
            $tone = [
                'processing' => 'warning',
                'approved' => 'success',
                'paid' => 'info',
                'rejected' => 'danger',
            ];
        @endphp

        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" wire:click="setStatus('all')"
                        class="px-3.5 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === 'all' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                    All <span class="ml-1 opacity-70">{{ array_sum($statusCounts) }}</span>
                </button>
                @foreach ($statuses as $loanStatus)
                    <button type="button" wire:click="setStatus('{{ $loanStatus->value }}')"
                            class="px-3.5 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $loanStatus->value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                        {{ $loanStatus->label() }}
                        @if (($statusCounts[$loanStatus->value] ?? 0) > 0)
                            <span class="ml-1 opacity-70">{{ number_format($statusCounts[$loanStatus->value]) }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm flex-1 min-w-0">
                    <i class="ri-search-line text-muted text-[17px]"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Loan ID, user, account, bank…"
                           class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                </div>
                <div class="relative h-[44px] shrink-0 w-full sm:w-[140px]">
                    <select wire:model.live="perPage" class="h-[44px] w-full rounded-[14px] bg-surface shadow-sm border-0 pl-3.5 pr-9 font-sans text-[13px] text-muted focus:ring-2 focus:ring-primary/40 appearance-none">
                        @foreach ([10, 20, 50, 100, 250, 500] as $size)
                            <option value="{{ $size }}">{{ $size }} / page</option>
                        @endforeach
                    </select>
                    <i class="ri-arrow-down-s-line text-[18px] text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            </div>
        </div>

        @if ($canWrite && in_array($status, [\App\Enums\LoanStatus::PROCESSING->value, \App\Enums\LoanStatus::APPROVED->value], true) && count($selected))
            @php
                $pendingSelection = $status === \App\Enums\LoanStatus::PROCESSING->value;
                $selection = $pendingSelection ? $this->selectedProcessingPreview : $this->selectedApprovedPreview;
                $selectionLabel = $pendingSelection ? 'pending' : 'approved';
                $selectionMatches = $selection['count'] === count($selected);
            @endphp
            <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between rounded-[14px] bg-primary/8 border border-primary/15 px-4 py-2.5">
                <span class="font-sans text-[13px] text-primary">
                    {{ $selection['count'] }} {{ $selectionLabel }} selected · {{ money($selection['amount']) }} requested
                </span>
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($pendingSelection)
                        <button type="button" @disabled(! $selectionMatches)
                                x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'rejectSelected', params: [], title: 'Reject selected loan requests', message: 'The selected pending loan applications will be rejected. Their application fees remain non-refundable.', confirmText: 'Reject selected', tone: 'danger' })"
                                class="h-[34px] px-3.5 rounded-full bg-danger/10 text-danger font-sans text-[12px] flex items-center gap-1.5 disabled:opacity-50">
                            <i class="ri-close-circle-line"></i> Reject selected
                        </button>
                    @else
                        <button type="button" wire:click="openExport" @disabled(! $selectionMatches)
                                class="h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px] flex items-center gap-1.5 disabled:opacity-50">
                            <i class="ri-file-download-line"></i> Export to sheet + mark paid
                        </button>
                        <button type="button" wire:click="openBulkMarkPaid" @disabled(! $selectionMatches)
                                class="h-[34px] px-3.5 rounded-full bg-success text-white font-sans text-[12px] flex items-center gap-1.5 disabled:opacity-50">
                            <i class="ri-checkbox-circle-line"></i> Mark selected paid
                        </button>
                    @endif
                </div>
            </div>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($loans->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-hand-coin-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No loan requests match this view.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                @if ($canWrite && in_array($status, [\App\Enums\LoanStatus::PROCESSING->value, \App\Enums\LoanStatus::APPROVED->value], true))
                                    <th class="px-4 py-3 w-10"><input type="checkbox" wire:model.live="selectPage" class="rounded border-muted/30 text-primary focus:ring-primary/40" aria-label="Select loans on this page"></th>
                                @endif
                                <th class="px-4 py-3 font-medium">User / loan</th>
                                <th class="px-4 py-3 font-medium">Bank account for disbursement</th>
                                <th class="px-4 py-3 font-medium text-right">Requested</th>
                                <th class="px-4 py-3 font-medium text-right">Repayment</th>
                                <th class="px-4 py-3 font-medium">Due date</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($loans as $loan)
                                <tr wire:key="loan-{{ $loan->id }}" wire:click="openDetail('{{ $loan->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/40 cursor-pointer">
                                    @if ($canWrite && in_array($status, [\App\Enums\LoanStatus::PROCESSING->value, \App\Enums\LoanStatus::APPROVED->value], true))
                                        <td class="px-4 py-3" @click.stop>
                                            <input type="checkbox" value="{{ $loan->id }}" wire:model.live="selected" class="rounded border-muted/30 text-primary focus:ring-primary/40" aria-label="Select loan {{ $loan->id }}">
                                        </td>
                                    @endif
                                    <td class="px-4 py-3">
                                        @if ($loan->user)
                                            <a href="{{ route('backoffice.users.show', $loan->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition">
                                                <x-backoffice.user-cell :user="$loan->user" />
                                            </a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                        <p class="font-sans text-muted text-[11px] mt-1">{{ $loan->loan_type?->label() ?? 'Loan' }} · {{ $loan->created_at?->format('j M Y, g:i A') }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $loan->disbursement_account_name ?: '—' }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $loan->disbursement_bank_name ?: 'Bank missing' }} · {{ $loan->disbursement_account_number ?: 'Account missing' }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <p class="font-heading text-text text-[13px]">{{ money($loan->amount) }}</p>
                                        <p class="font-sans text-muted text-[11px]">USD requested</p>
                                    </td>
                                    <td class="px-4 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ money($loan->total_repayment) }}</td>
                                    <td class="px-4 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $loan->due_date?->format('j M Y') ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <x-backoffice.badge :tone="$tone[$loan->status->value] ?? 'muted'">{{ $loan->status->label() }}</x-backoffice.badge>
                                        @if ($loan->paid_at)
                                            <p class="font-sans text-muted text-[10px] mt-1">{{ $loan->paid_at->format('j M Y, g:i A') }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($loans->hasPages())
            <div>{{ $loans->links('vendor.pagination.nextel') }}</div>
        @endif

        <x-backoffice.card title="Cloud subscribers by plan">
            <div class="flex flex-col gap-3">
                @forelse ($cloudStoragePlanDistribution as $row)
                    <div class="flex items-center gap-3">
                        <span class="font-sans text-text text-[13px] w-32 shrink-0 truncate">{{ $row['name'] }}</span>
                        <div class="flex-1 h-2.5 rounded-full bg-background overflow-hidden">
                            <div class="h-full rounded-full bg-primary" style="width: {{ max($row['pct'], $row['count'] > 0 ? 2 : 0) }}%"></div>
                        </div>
                        <span class="font-heading text-text text-[13px] w-16 text-right shrink-0">{{ number_format($row['count']) }}</span>
                        <span class="font-sans text-muted text-[12px] w-12 text-right shrink-0">{{ $row['pct'] }}%</span>
                    </div>
                @empty
                    <p class="font-sans text-muted text-[13px] py-3 text-center">No Cloud storage plans yet.</p>
                @endforelse
            </div>
        </x-backoffice.card>

        <x-backoffice.modal :show="$showDetail" title="Manage loan" closeAction="$set('showDetail', false)">
            @if ($this->detailLoan)
                @php $loan = $this->detailLoan; @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        @if ($loan->user)
                            <a href="{{ route('backoffice.users.show', $loan->user) }}" wire:navigate class="min-w-0 hover:opacity-80 transition"><x-backoffice.user-cell :user="$loan->user" /></a>
                        @else
                            <span class="font-sans text-muted text-[13px]">—</span>
                        @endif
                        <x-backoffice.badge :tone="$tone[$loan->status->value] ?? 'muted'">{{ $loan->status->label() }}</x-backoffice.badge>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Loan type', $loan->loan_type?->label() ?? '—'],
                            ['Requested amount', money($loan->amount)],
                            ['Total repayment', money($loan->total_repayment)],
                            ['Repayment due date', $loan->due_date?->format('j M Y') ?? '—'],
                            ['Application fee', $loan->application_fee_currency ? $loan->application_fee_currency.' '.number_format((float) $loan->application_fee_amount, 2).' (non-refundable)' : '—'],
                            ['Fee calculation', $loan->application_fee_type === 'percentage' ? number_format((float) $loan->application_fee_percentage, 2).'% of principal' : 'Fixed amount'],
                            ['Fee payment', $loan->application_fee_coupon_id ? 'Vendor coupon' : ($loan->application_fee_reference ? 'Payment channel' : '—')],
                            ['Payment reference', $loan->application_fee_reference ?? '—'],
                            ['Guarantor-form fee', $loan->guarantor_form_fee_paid_at ? 'NGN '.number_format((float) $loan->guarantor_form_fee_amount, 2).' · paid '.$loan->guarantor_form_fee_paid_at->format('j M Y, g:i A') : 'Not verified'],
                            ['Guarantor form', $loan->hasCompletedGuarantorForm() ? 'Submitted '.$loan->guarantor_form_submitted_at?->format('j M Y, g:i A') : 'Not submitted'],
                            ['Disbursement account name', $loan->disbursement_account_name ?? '—'],

                            ['Disbursement account number', $loan->disbursement_account_number ?? '—'],
                            ['Disbursement bank name', $loan->disbursement_bank_name ?? '—'],
                            ['Agreement accepted', $loan->agreement_accepted_at?->format('j M Y, g:i A') ?? '—'],
                            ['Created', $loan->created_at?->format('j M Y, g:i A') ?? '—'],
                            ['Paid at', $loan->paid_at?->format('j M Y, g:i A') ?? '—'],
                            ['Exported at', $loan->exported_at?->format('j M Y, g:i A') ?? '—'],
                            ['Marked paid by', $loan->paidBy?->name ?? '—'],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right break-words">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($loan->hasCompletedGuarantorForm())
                        @php $guarantor = (array) $loan->guarantor_form_data; @endphp
                        <div class="rounded-[16px] border border-muted/10 bg-background divide-y divide-muted/10">
                            <p class="px-4 pt-4 pb-2 font-sans text-text text-[13px] font-semibold">Submitted guarantor details</p>
                            @foreach ([
                                ['Full name', $guarantor['full_name'] ?? '—'],
                                ['Relationship', $guarantor['relationship'] ?? '—'],
                                ['Phone', $guarantor['phone'] ?? '—'],
                                ['Email', $guarantor['email'] ?? '—'],
                                ['Residential address', $guarantor['residential_address'] ?? '—'],
                                ['Consent', ($guarantor['consent'] ?? false) ? 'Confirmed' : '—'],
                                ['Electronic signature', $guarantor['signature'] ?? '—'],
                            ] as [$label, $value])
                                <div class="flex items-start justify-between gap-3 px-4 py-3">
                                    <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                    <span class="font-sans text-text text-[13px] text-right break-words">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($loan->status === \App\Enums\LoanStatus::APPROVED && ! $loan->hasCompletedGuarantorForm())
                        <div class="rounded-[12px] bg-warning/10 px-4 py-3 font-sans text-[12px] text-text leading-relaxed">
                            This loan is approved, but disbursement is locked until the applicant pays the guarantor-form fee and submits the form. No payout action is available yet.
                        </div>
                    @elseif ($loan->status === \App\Enums\LoanStatus::APPROVED)
                        <div class="rounded-[12px] bg-success/10 px-4 py-3 font-sans text-[12px] text-text leading-relaxed">
                            Ready for manual disbursement. Exporting the bank details marks the selected loan as paid while preparing the CSV; alternatively, use Mark as paid after a transfer made outside the system. Neither action sends funds.
                        </div>
                    @elseif ($loan->status === \App\Enums\LoanStatus::PAID)
                        <div class="rounded-[12px] bg-success/10 px-4 py-3 font-sans text-[12px] text-text leading-relaxed">
                            This loan is recorded as paid/disbursed. No bank transfer is initiated by this page.
                        </div>
                    @endif

                    @if ($loan->status === \App\Enums\LoanStatus::REJECTED && $loan->rejection_reason)
                        <p class="rounded-[12px] bg-danger/10 px-4 py-3 font-sans text-[12px] text-danger">{{ $loan->rejection_reason }}</p>
                    @endif

                    @if ($canWrite)
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            @if ($loan->status === \App\Enums\LoanStatus::PROCESSING)
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'setLoanStatus', params: ['{{ $loan->id }}','rejected'], title: 'Reject loan', message: 'This loan request will be rejected. The application fee remains non-refundable.', confirmText: 'Reject', tone: 'danger' })"
                                        class="h-[40px] px-4 rounded-full bg-danger/10 text-danger font-sans text-[13px]">Reject</button>
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'setLoanStatus', params: ['{{ $loan->id }}','approved'], title: 'Approve loan', message: 'This loan request will be approved for the guarantor-form step. Manual disbursement stays locked until the applicant pays the form fee and submits the form.', confirmText: 'Approve', tone: 'primary' })"
                                        class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px]">Approve</button>
                            @elseif ($loan->status === \App\Enums\LoanStatus::APPROVED)
                                @if ($loan->hasCompletedGuarantorForm())
                                    <button type="button" wire:click="openExport('{{ $loan->id }}')" class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px] flex items-center gap-2"><i class="ri-file-download-line"></i> Export + mark paid</button>
                                    <button type="button" wire:click="openMarkPaid('{{ $loan->id }}')" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px]">Mark as paid</button>
                                @else
                                    <span class="h-[40px] px-4 rounded-full bg-muted/10 text-muted font-sans text-[13px] flex items-center">Waiting for guarantor form</span>
                                @endif
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'extendDue', params: ['{{ $loan->id }}'], title: 'Extend due date', message: 'The repayment due date will be extended by 30 days.', confirmText: 'Extend', tone: 'primary' })"
                                        class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Extend due date +30 days</button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>

        @if ($canWrite)
            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-loan-markpaid.window="open = true" @close-loan-markpaid.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[430px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Mark loan as paid</p>
                        <button type="button" @click="open = false" class="size-8 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-close-line"></i></button>
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Confirm the transfer has been made to the applicant's bank account. This records the manual disbursement only; it does not send funds.</p>
                    @if ($this->markPaidTarget)
                        @php $paidLoan = $this->markPaidTarget; @endphp
                        <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-4">
                            <div class="flex items-center justify-between"><span class="font-sans text-muted text-[12px]">Applicant</span><span class="font-sans text-text text-[13px]">{{ $paidLoan->user?->username ? '@'.$paidLoan->user->username : '—' }}</span></div>
                            <div class="flex items-center justify-between"><span class="font-sans text-muted text-[12px]">Amount disbursed</span><span class="font-heading text-text text-[13px]">{{ money($paidLoan->amount) }}</span></div>
                            <div class="flex items-start justify-between gap-3"><span class="font-sans text-muted text-[12px]">Bank account</span><span class="font-sans text-text text-[12px] text-right">{{ $paidLoan->disbursement_bank_name }} · {{ $paidLoan->disbursement_account_number }}<span class="block text-muted">{{ $paidLoan->disbursement_account_name }}</span></span></div>
                        </div>
                    @endif
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="confirmMarkPaid" wire:loading.attr="disabled" wire:target="confirmMarkPaid" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="confirmMarkPaid">Confirm paid</span>
                            <span wire:loading wire:target="confirmMarkPaid" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-loan-export.window="open = true" @close-loan-export.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[480px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)] max-h-[88vh] overflow-y-auto">
                    @php $exportPreview = $this->selectedApprovedPreview; @endphp
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Export {{ $exportPreview['count'] }} loan{{ $exportPreview['count'] === 1 ? '' : 's' }} &amp; mark paid</p>
                        <button type="button" @click="open = false" class="size-8 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-close-line"></i></button>
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">The CSV contains each applicant's bank details and requested USD amount. Exporting marks these loans as paid in the system; it does not make a bank transfer.</p>
                    <div class="flex items-start gap-2 rounded-[12px] bg-warning/10 border border-warning/20 px-3 py-2.5 mb-4">
                        <i class="ri-error-warning-line text-warning text-[16px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-text/80 text-[12px] leading-snug">This export immediately records the selected loans as paid/disbursed. It does not transfer funds; arrange or verify bank payments outside the system. This sensitive file is for authorized payout handling only.</p>
                    </div>
                    @if ($exportPreview['count'])
                        <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex flex-col gap-2 mb-4">
                            <div class="flex items-center justify-between"><span class="font-sans text-muted text-[12px]">Selected loans</span><span class="font-heading text-text text-[13px]">{{ $exportPreview['count'] }}</span></div>
                            <div class="flex items-center justify-between"><span class="font-sans text-muted text-[12px]">Total requested</span><span class="font-heading text-text text-[13px]">{{ money($exportPreview['amount']) }}</span></div>
                            <div class="max-h-[160px] overflow-y-auto flex flex-col gap-1.5 border-t border-muted/10 pt-2">
                                @foreach ($exportPreview['rows'] as $row)
                                    <div class="flex items-center justify-between gap-3 font-sans text-[11px]">
                                        <span class="min-w-0 truncate text-text">{{ $row->user?->username ? '@'.$row->user->username : 'Applicant' }} · {{ $row->disbursement_bank_name }} · {{ $row->disbursement_account_number }}</span>
                                        <span class="shrink-0 text-text">{{ money($row->amount) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="rounded-[12px] bg-danger/10 px-3 py-2.5 mb-4 font-sans text-danger text-[12px]">No selected loans are still approved for disbursement.</p>
                    @endif
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="exportSelected" @disabled(! $exportPreview['count']) wire:loading.attr="disabled" wire:target="exportSelected" class="h-[40px] px-4 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="exportSelected">Download CSV &amp; mark paid</span>
                            <span wire:loading wire:target="exportSelected" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Preparing CSV…</span>
                        </button>
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" x-cloak :data-lock-scroll="open ? '' : null" @open-loan-bulk-paid.window="open = true" @close-loan-bulk-paid.window="open = false" @keydown.escape.window="open = false">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[90] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[440px] z-[100] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    @php $paidPreview = $this->selectedApprovedPreview; @endphp
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <p class="font-heading font-medium text-text text-[16px]">Mark {{ $paidPreview['count'] }} loan{{ $paidPreview['count'] === 1 ? '' : 's' }} as paid</p>
                        <button type="button" @click="open = false" class="size-8 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-close-line"></i></button>
                    </div>
                    <p class="font-sans text-muted text-[13px] mb-4">Confirm the selected funds have been transferred manually. This status update does not initiate any payment.</p>
                    <div class="rounded-[14px] bg-background border border-muted/10 p-3.5 flex items-center justify-between mb-4">
                        <span class="font-sans text-muted text-[12px]">Total requested</span>
                        <span class="font-heading text-text text-[14px]">{{ money($paidPreview['amount']) }}</span>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="open = false" class="h-[40px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="markSelectedPaid" @disabled(! $paidPreview['count']) wire:loading.attr="disabled" wire:target="markSelectedPaid" class="h-[40px] px-4 rounded-full bg-success text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="markSelectedPaid">Confirm paid</span>
                            <span wire:loading wire:target="markSelectedPaid" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </x-backoffice.page>
</div>
