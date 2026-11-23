{{-- Shared backoffice UI for sending a payouts. Included by the provider blades with $manager
     ("Korapay"), $routePrefix (e.g. "backoffice.korapay"), $providerNote and $managerConfigured. --}}
<div>
    <x-backoffice.page title="Send money" :subtitle="'Single payout from the '.$manager.' wallet — not tied to users or withdrawals'">
        <x-slot:action>
            <div class="flex items-center gap-2">
                @if ($testMode)
                    <x-backoffice.badge tone="warning">Test mode</x-backoffice.badge>
                @endif
                <x-backoffice.badge tone="purple"><i class="ri-shield-user-line"></i> Super admin only</x-backoffice.badge>
            </div>
        </x-slot:action>

        @php $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        @unless ($managerConfigured)
            <x-backoffice.card>
                <div class="flex items-start gap-3">
                    <span class="size-10 rounded-[12px] bg-danger/12 text-danger flex items-center justify-center shrink-0"><i class="ri-key-2-line text-[20px]"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-heading font-medium text-text text-[15px]">{{ $manager }} secret key not set</p>
                        <p class="font-sans text-muted text-[13px]">Payouts cannot be sent until the merchant secret key is configured under Settings → Services.</p>
                    </div>
                </div>
            </x-backoffice.card>
        @endunless

        @if ($lastReference)
            <x-backoffice.card>
                <div class="flex items-start gap-3">
                    <span class="size-10 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-receipt-line text-[20px]"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-heading font-medium text-text text-[15px]">Last payout reference: {{ $lastReference }}</p>
                        <p class="font-sans text-muted text-[13px]">Verify it on the payout verification page before treating it as final — a payout can still be processing after the request returns.</p>
                        @if (\Illuminate\Support\Facades\Route::has($routePrefix.'.verify'))
                            <a href="{{ route($routePrefix.'.verify', ['reference' => $lastReference]) }}" wire:navigate class="inline-flex items-center gap-1.5 mt-2 font-sans text-[13px] text-primary underline">
                                Verify {{ $lastReference }} <i class="ri-arrow-right-line"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </x-backoffice.card>
        @endif

        <x-backoffice.card title="Payout details">
            <form wire:submit="confirm" class="flex flex-col gap-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Currency</label>
                        <select wire:model.live="currency" class="{{ $input }} w-full">
                            @foreach ($currencies as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        @error('currency') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Amount ({{ $currency }})</label>
                        <input type="text" inputmode="decimal" wire:model="amount" placeholder="0.00" class="{{ $input }} w-full tabular-nums">
                        <span class="font-sans text-[11px] text-muted">Maximum {{ number_format($maxAmount, 2) }} {{ $currency }} per payout.</span>
                        @error('amount') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if ($debitCurrencies !== [])
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Wallet to debit</label>
                        <select wire:model.live="debitCurrency" class="{{ $input }} w-full sm:max-w-xs">
                            <option value="">Debit the {{ $currency }} wallet</option>
                            @foreach ($debitCurrencies as $code)
                                @if ($code !== $currency)
                                    <option value="{{ $code }}">Debit the {{ $code }} wallet</option>
                                @endif
                            @endforeach
                        </select>
                        <span class="font-sans text-[11px] text-muted">Only for a cross-currency payout — the transfer still arrives in {{ $currency }}.</span>
                        @error('debitCurrency') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Destination bank</label>
                        <select wire:model.live="bankCode" class="{{ $input }} w-full">
                            <option value="">Select a bank…</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->code }}">{{ $bank->name }} ({{ $bank->code }})</option>
                            @endforeach
                        </select>
                        @if ($banks->isEmpty())
                            <span class="font-sans text-[11px] text-[#d79937]">No active {{ $country }} banks are configured — add them under Banks.</span>
                        @endif
                        @error('bankCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Account number</label>
                        <div class="flex items-center gap-2">
                            <input type="text" inputmode="numeric" wire:model.blur="accountNumber" placeholder="0123456789" class="{{ $input }} w-full tabular-nums">
                            <button type="button" wire:click="resolveAccount" wire:loading.attr="disabled" wire:target="resolveAccount"
                                    class="h-[44px] px-3.5 rounded-[12px] bg-primary/10 text-primary font-sans text-[12px] whitespace-nowrap inline-flex items-center gap-1.5 disabled:opacity-60">
                                <i wire:loading.remove wire:target="resolveAccount" class="ri-user-search-line text-[15px]"></i>
                                <i wire:loading wire:target="resolveAccount" class="ri-loader-4-line animate-spin text-[15px]"></i>
                                Resolve
                            </button>
                        </div>
                        @error('accountNumber') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Account name {{ $accountResolved ? '(verified)' : '' }}</label>
                    <input type="text" wire:model="accountName" placeholder="Resolve the account to fill this in" class="{{ $input }} w-full">
                    @if ($accountResolved)
                        <span class="font-sans text-[11px] text-success inline-flex items-center gap-1"><i class="ri-verified-badge-line"></i> Confirmed by {{ $manager }} account resolve.</span>
                    @elseif (filled($accountName))
                        <span class="font-sans text-[11px] text-[#d79937] inline-flex items-center gap-1"><i class="ri-alert-line"></i> Not verified by the bank — double-check before sending.</span>
                    @endif
                    @error('accountName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Narration (sent to {{ $manager }})</label>
                    <input type="text" wire:model="narration" maxlength="100" class="{{ $input }} w-full">
                    @error('narration') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Recipient name</label>
                        <input type="text" wire:model="customerName" class="{{ $input }} w-full">
                        @error('customerName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Recipient email</label>
                        <input type="email" wire:model="customerEmail" class="{{ $input }} w-full">
                        @error('customerEmail') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Internal note (never sent to {{ $manager }})</label>
                    <input type="text" wire:model="note" maxlength="200" placeholder="Why is this payout being made?" class="{{ $input }} w-full">
                    @error('note') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1 rounded-[14px] bg-background px-4 py-3">
                    <span class="font-sans text-muted text-[12px]">Reference (auto-generated, unique)</span>
                    <span class="font-sans text-text text-[13px] tabular-nums">{{ $reference }}</span>
                </div>

                <p class="font-sans text-[12px] text-muted leading-relaxed">{{ $providerNote }}</p>

                <div class="flex justify-end">
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirm"
                            @disabled(! $managerConfigured)
                            class="h-[44px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-50">
                        <i class="ri-arrow-right-line"></i> Review payout
                    </button>
                </div>
            </form>
        </x-backoffice.card>

        <x-backoffice.card title="Recent payouts">
            @if ($recentPayouts->isEmpty())
                <p class="font-sans text-muted text-[13px]">No payouts sent from the backoffice yet.</p>
            @else
                <div class="flex flex-col divide-y divide-muted/5">
                    @foreach ($recentPayouts as $payout)
                        <div wire:key="recent-{{ $payout->id }}" class="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                            <span class="size-10 rounded-[12px] bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-bank-card-line text-[18px]"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-sans text-text text-[13px] truncate">{{ $payout->account_name ?: $payout->account_number }} · {{ number_format((float) $payout->amount, 2) }} {{ $payout->currency }}</p>
                                <p class="font-sans text-muted text-[12px] truncate">{{ $payout->reference }} · {{ $payout->created_at?->diffForHumans() }}{{ $payout->admin ? ' · '.$payout->admin->name : '' }}</p>
                            </div>
                            <x-backoffice.badge :tone="$payout->statusTone()">{{ $payout->statusLabel() }}</x-backoffice.badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-backoffice.card>

        <x-backoffice.modal :show="$showConfirm" title="Confirm payout" closeAction="$set('showConfirm', false)">
            <div class="flex flex-col gap-4">
                <div class="rounded-[16px] bg-background px-4 py-3.5 flex flex-col gap-2.5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-sans text-muted text-[12px]">Recipient</span>
                        <span class="font-sans text-text text-[13px] text-right">{{ $accountName ?: '—' }}<br><span class="text-muted text-[12px]">{{ $selectedBank }} · {{ $accountNumber }}</span></span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-sans text-muted text-[12px]">Amount</span>
                        <span class="font-heading text-text text-[15px]">{{ number_format((float) $amount, 2) }} {{ $currency }}</span>
                    </div>
                    @if ($debitCurrency !== '')
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-sans text-muted text-[12px]">Wallet debited</span>
                            <span class="font-sans text-text text-[13px]">{{ $debitCurrency }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-sans text-muted text-[12px]">Narration</span>
                        <span class="font-sans text-text text-[13px] text-right">{{ $narration }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-sans text-muted text-[12px]">Reference</span>
                        <span class="font-sans text-text text-[12px] tabular-nums">{{ $reference }}</span>
                    </div>
                    @if ($note !== '')
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-sans text-muted text-[12px]">Internal note</span>
                            <span class="font-sans text-muted text-[12px] text-right">{{ $note }}</span>
                        </div>
                    @endif
                </div>

                @unless ($accountResolved)
                    <p class="font-sans text-[12px] text-[#d79937] flex items-start gap-1.5">
                        <i class="ri-alert-line mt-0.5"></i> The account name was not confirmed by the bank. Resolve the account first if you can.
                    </p>
                @endunless

                <p class="font-sans text-[12px] text-muted leading-relaxed">Money leaves the {{ $manager }} wallet immediately and cannot be recalled. The payout is recorded here and can be checked any time with the reference.</p>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelConfirm" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                            class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                        <span wire:loading.remove wire:target="send">Send {{ number_format((float) $amount, 2) }} {{ $currency }}</span>
                        <span wire:loading wire:target="send" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Sending…</span>
                    </button>
                </div>
            </div>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
