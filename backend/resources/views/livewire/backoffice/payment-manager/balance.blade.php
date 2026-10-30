{{-- Shared backoffice UI for a payment manager's wallet balance page. Included by the provider
     blades with $manager (e.g. "Korapay") and $routePrefix (e.g. "backoffice.korapay"). --}}
<div>
    <x-backoffice.page :title="$manager.' balance'" subtitle="Merchant wallet — available and pending funds">
        <x-slot:action>
            <div class="flex items-center gap-2">
                @if ($testMode)
                    <x-backoffice.badge tone="warning">Test mode</x-backoffice.badge>
                @else
                    <x-backoffice.badge tone="success">Live mode</x-backoffice.badge>
                @endif
                <button type="button" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh"
                        class="h-[38px] px-4 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                    <i wire:loading.remove wire:target="refresh" class="ri-refresh-line text-[16px]"></i>
                    <i wire:loading wire:target="refresh" class="ri-loader-4-line animate-spin text-[16px]"></i>
                    Refresh
                </button>
            </div>
        </x-slot:action>

        @unless ($configured)
            <x-backoffice.card>
                <div class="flex items-start gap-3">
                    <span class="size-10 rounded-[12px] bg-[#d79937]/15 text-[#d79937] flex items-center justify-center shrink-0"><i class="ri-key-2-line text-[20px]"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-heading font-medium text-text text-[15px]">{{ $manager }} secret key not set</p>
                        <p class="font-sans text-muted text-[13px] leading-relaxed">Add the merchant <span class="text-text">secret key</span> under Settings → Services to read balances and send payouts. Sending money stays disabled until then.</p>
                        @if (\Illuminate\Support\Facades\Route::has('backoffice.settings.services') && $this->canWrite())
                            <a href="{{ route('backoffice.settings.services') }}" wire:navigate class="inline-flex items-center gap-1.5 mt-3 font-sans text-[13px] text-primary underline">
                                Open service settings <i class="ri-arrow-right-line"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </x-backoffice.card>
        @endunless

        @if ($error)
            <x-backoffice.card>
                <div class="flex items-start gap-3">
                    <span class="size-10 rounded-[12px] bg-danger/12 text-danger flex items-center justify-center shrink-0"><i class="ri-error-warning-line text-[20px]"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-heading font-medium text-text text-[15px]">Balance unavailable</p>
                        <p class="font-sans text-muted text-[13px]">{{ $error }}</p>
                    </div>
                </div>
            </x-backoffice.card>
        @endif

        @if ($balances !== [])
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($balances as $balance)
                    @php
                        $notes = [];

                        if ($balance['pending'] > 0) {
                            $notes[] = 'pending '.number_format($balance['pending'], 2);
                        }

                        if ($balance['issuing'] !== null) {
                            $notes[] = 'issuing '.number_format($balance['issuing'], 2);
                        }

                        $explain = $balance['currency'] === 'USD' && $balance['issuing'] !== null
                            ? 'Issuing balance is loaded on your Korapay-issued card. Pending funds are received but not yet settled to the available balance.'
                            : 'Funds received but not yet settled to the available balance.';
                    @endphp
                    {{-- Exact figures, not the shared abbreviated stat: an operator reconciling the
                         wallet needs every digit, and the abbreviation only rounds cents away. --}}
                    <div class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="size-10 rounded-[12px] flex items-center justify-center bg-primary/10 text-primary shrink-0"><i class="ri-wallet-3-line text-[20px]"></i></span>
                            @if ($notes !== [])
                                <span class="font-sans text-[12px] text-muted text-right cursor-help" title="{{ $explain }}">{{ implode(' · ', $notes) }}</span>
                            @endif
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <p class="font-heading font-semibold text-text text-[24px] leading-none tabular-nums">{{ number_format($balance['available'], 2) }}</p>
                            <p class="font-sans text-muted text-[13px]">{{ $balance['currency'] }} available</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-backoffice.card title="Account snapshot">
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                        <span class="font-sans text-muted text-[12px]">Status</span>
                        <span class="font-sans text-text text-[13px]">{{ $configured ? 'Connected' : 'Not configured' }}{{ $testMode ? ' (test keys)' : '' }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="font-sans text-muted text-[12px]">Last fetched</span>
                        <span class="font-sans text-text text-[13px]">{{ $fetchedAt ? \Illuminate\Support\Carbon::parse($fetchedAt)->format('j M Y, g:i A') : '—' }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="font-sans text-muted text-[12px]">Settled references (24h)</span>
                        <span class="font-sans text-text text-[13px]">{{ number_format($settledLast24h) }}</span>
                    </div>
                </div>
                @if ($inFlightPayouts > 0)
                    <p class="font-sans text-[12px] text-[#d79937] mt-4 flex items-center gap-1.5">
                        <i class="ri-time-line"></i> {{ $inFlightPayouts }} payout{{ $inFlightPayouts === 1 ? '' : 's' }} still in flight — verify {{ $inFlightPayouts === 1 ? 'it' : 'them' }} before treating {{ $inFlightPayouts === 1 ? 'it' : 'them' }} as final.
                    </p>
                @endif
            </x-backoffice.card>
        @endif

        <x-backoffice.card title="Browse the {{ $manager }} ledger">
            <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
                @foreach ([
                    ['route' => $routePrefix.'.history', 'icon' => 'ri-history-line', 'label' => 'Balance history', 'hint' => 'Every credit and debit'],
                    ['route' => $routePrefix.'.send-money', 'icon' => 'ri-send-plane-line', 'label' => 'Send money', 'hint' => 'Single payout to a bank'],
                    ['route' => $routePrefix.'.verify', 'icon' => 'ri-shield-check-line', 'label' => 'Verify payout', 'hint' => 'Check a payout reference'],
                    ['route' => 'backoffice.payment-references', 'icon' => 'ri-link-m', 'label' => 'Payment references', 'hint' => 'Single-use settlement ledger'],
                ] as $link)
                    @if (\Illuminate\Support\Facades\Route::has($link['route']) && ($link['route'] !== $routePrefix.'.send-money' || auth('admin')->user()?->isSuper()))
                        <a href="{{ route($link['route']) }}" wire:navigate class="flex items-center gap-3 rounded-[16px] bg-background px-4 py-3.5 hover:bg-background/70 transition">
                            <span class="size-10 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="{{ $link['icon'] }} text-[20px]"></i></span>
                            <span class="min-w-0">
                                <span class="block font-heading font-medium text-text text-[14px]">{{ $link['label'] }}</span>
                                <span class="block font-sans text-muted text-[12px] truncate">{{ $link['hint'] }}</span>
                            </span>
                        </a>
                    @endif
                @endforeach
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="Recent payouts">
            @if ($recentPayouts->isEmpty())
                <p class="font-sans text-muted text-[13px]">No payouts have been sent from the backoffice yet.</p>
            @else
                <div class="overflow-x-auto -mx-5 lg:-mx-6 px-5 lg:px-6">
                    <table class="w-full min-w-[720px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-2 py-3 font-medium">Reference</th>
                                <th class="px-2 py-3 font-medium">Recipient</th>
                                <th class="px-2 py-3 font-medium text-right">Amount</th>
                                <th class="px-2 py-3 font-medium">Status</th>
                                <th class="px-2 py-3 font-medium">Sent by</th>
                                <th class="px-2 py-3 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentPayouts as $payout)
                                <tr wire:key="payout-{{ $payout->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $payout->reference }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $payout->created_at?->format('j M, g:i A') }}</p>
                                    </td>
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $payout->account_name ?: '—' }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $payout->bank_name ?: $payout->bank_code }} · {{ $payout->account_number }}</p>
                                    </td>
                                    <td class="px-2 py-3 text-right font-sans text-text text-[13px] whitespace-nowrap">
                                        {{ number_format((float) $payout->amount, 2) }} {{ $payout->currency }}
                                        @if ((float) $payout->fee > 0)<span class="block font-sans text-muted text-[11px]">fee {{ number_format((float) $payout->fee, 2) }}</span>@endif
                                    </td>
                                    <td class="px-2 py-3"><x-backoffice.badge :tone="$payout->statusTone()">{{ $payout->statusLabel() }}</x-backoffice.badge></td>
                                    <td class="px-2 py-3 font-sans text-muted text-[12px]">{{ $payout->admin?->name ?? '—' }}</td>
                                    <td class="px-2 py-3 text-right">
                                        @if (\Illuminate\Support\Facades\Route::has($routePrefix.'.verify'))
                                            <a href="{{ route($routePrefix.'.verify', ['reference' => $payout->reference]) }}" wire:navigate
                                               class="font-sans text-[12px] text-primary whitespace-nowrap">Verify</a>
                                        @endif
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
