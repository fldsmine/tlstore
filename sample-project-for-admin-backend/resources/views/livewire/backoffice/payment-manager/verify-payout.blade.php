{{-- Shared backoffice UI for verifying a payout by reference. Included with $manager ("Korapay"),
     $routePrefix (e.g. "backoffice.korapay") and $referenceHint. --}}
<div>
    <x-backoffice.page :title="'Verify '.$manager.' payout'" subtitle="Ask {{ $manager }} for the authoritative status of a payout reference">
        @php
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
            $tone = fn (string $status) => match ($status) {
                'success' => 'success',
                'failed' => 'danger',
                'processing' => 'blue',
                'unknown' => 'warning',
                default => 'muted',
            };
        @endphp

        <x-backoffice.card title="Check a payout">
            <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">{{ $referenceHint }} When a payout request errors out mid-flight the money may still have moved — verify the reference before treating it as failed or sending again.</p>
            <form wire:submit="verify" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                <div class="flex flex-col gap-1.5 flex-1 min-w-0">
                    <label class="font-sans text-muted text-[12px]">Payout reference</label>
                    <input type="text" wire:model="reference" autocomplete="off" spellcheck="false" placeholder="{{ $referencePlaceholder }}" class="{{ $input }} w-full tabular-nums">
                    @error('reference') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled" wire:target="verify"
                        class="h-[44px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center justify-center gap-2 disabled:opacity-60">
                    <i wire:loading.remove wire:target="verify" class="ri-shield-check-line text-[16px]"></i>
                    <i wire:loading wire:target="verify" class="ri-loader-4-line animate-spin text-[16px]"></i>
                    Verify
                </button>
            </form>
        </x-backoffice.card>

        @if ($result !== null)
            <x-backoffice.card title="Verification result">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="size-11 rounded-[14px] flex items-center justify-center shrink-0 {{ $result['ok'] ? 'bg-primary/10 text-primary' : 'bg-[#d79937]/15 text-[#d79937]' }}">
                                <i class="{{ $result['status'] === 'success' ? 'ri-checkbox-circle-line' : ($result['status'] === 'failed' ? 'ri-close-circle-line' : 'ri-time-line') }} text-[22px]"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="font-heading font-medium text-text text-[15px] truncate">{{ $result['reference'] }}</p>
                                <p class="font-sans text-muted text-[12px]">Checked {{ \Illuminate\Support\Carbon::parse($result['checked_at'])->format('j M Y, g:i A') }}</p>
                            </div>
                        </div>
                        <x-backoffice.badge :tone="$tone($result['status'])">{{ ucfirst($result['status']) }}</x-backoffice.badge>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div class="rounded-[14px] bg-background px-4 py-3">
                            <p class="font-sans text-muted text-[12px]">Amount</p>
                            <p class="font-heading text-text text-[15px]">{{ number_format((float) $result['amount'], 2) }} {{ $result['currency'] ?: '' }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background px-4 py-3">
                            <p class="font-sans text-muted text-[12px]">Fee</p>
                            <p class="font-heading text-text text-[15px]">{{ number_format((float) $result['fee'], 2) }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background px-4 py-3">
                            <p class="font-sans text-muted text-[12px]">{{ $identifierLabel }}</p>
                            <p class="font-sans text-text text-[12px] break-all">{{ $result['trace_id'] ?: '—' }}</p>
                        </div>
                    </div>

                    @if (filled($result['message']))
                        <div class="rounded-[14px] bg-background px-4 py-3">
                            <p class="font-sans text-muted text-[12px]">{{ $manager }} says</p>
                            <p class="font-sans text-text text-[13px]">{{ $result['message'] }}</p>
                        </div>
                    @endif

                    @if (! $result['ok'])
                        <p class="font-sans text-[12px] text-[#d79937] flex items-start gap-1.5">
                            <i class="ri-alert-line mt-0.5"></i> The status could not be confirmed — do not treat this payout as failed, and do not re-send it. Try again in a moment.
                        </p>
                    @endif

                    @if ($localPayoutId)
                        <p class="font-sans text-[12px] text-muted flex items-center gap-1.5">
                            <i class="ri-database-2-line"></i> This reference belongs to a payout recorded in the backoffice — its audit record was updated with the result above.
                        </p>
                    @endif

                    @if (filled($result['raw'] ?? null))
                        <details class="rounded-[14px] bg-background px-4 py-3">
                            <summary class="font-sans text-[12px] text-muted cursor-pointer">Raw {{ $manager }} response</summary>
                            <pre class="mt-3 font-sans text-[11px] text-text whitespace-pre-wrap break-all">{{ json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @endif
                </div>
            </x-backoffice.card>
        @endif

        <x-backoffice.card title="Recent payouts from this backoffice">
            @if ($recentPayouts->isEmpty())
                <p class="font-sans text-muted text-[13px]">No payouts have been sent yet.</p>
            @else
                <div class="overflow-x-auto -mx-5 lg:-mx-6 px-5 lg:px-6">
                    <table class="w-full min-w-[760px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-2 py-3 font-medium">Reference</th>
                                <th class="px-2 py-3 font-medium">Recipient</th>
                                <th class="px-2 py-3 font-medium text-right">Amount</th>
                                <th class="px-2 py-3 font-medium">Status</th>
                                <th class="px-2 py-3 font-medium">Last verified</th>
                                <th class="px-2 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentPayouts as $payout)
                                <tr wire:key="verify-{{ $payout->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px] tabular-nums">{{ $payout->reference }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $payout->created_at?->format('j M, g:i A') }}@if ($payout->admin) · {{ $payout->admin->name }}@endif</p>
                                    </td>
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $payout->account_name ?: $payout->account_number }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $payout->bank_name ?: $payout->bank_code }}</p>
                                    </td>
                                    <td class="px-2 py-3 text-right font-sans text-text text-[13px] whitespace-nowrap">{{ number_format((float) $payout->amount, 2) }} {{ $payout->currency }}</td>
                                    <td class="px-2 py-3"><x-backoffice.badge :tone="$payout->statusTone()">{{ $payout->statusLabel() }}</x-backoffice.badge></td>
                                    <td class="px-2 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $payout->last_verified_at?->diffForHumans() ?? 'never' }}</td>
                                    <td class="px-2 py-3 text-right">
                                        <button type="button" wire:click="useReference('{{ $payout->reference }}')"
                                                class="font-sans text-[12px] text-primary whitespace-nowrap">Verify</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
