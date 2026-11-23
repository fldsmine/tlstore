<div>
    <x-backoffice.page title="Payment providers" subtitle="Read-only health from what this server has seen — no external provider calls are made">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            @foreach ($providers as $provider)
                <x-backoffice.card wire:key="provider-{{ $provider['name'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="size-11 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary"><i class="ri-secure-payment-line text-[22px]"></i></span>
                            <div>
                                <p class="font-heading font-semibold text-text text-[15px] leading-tight">{{ \Illuminate\Support\Str::headline($provider['name']) }}</p>
                                <p class="font-sans text-[11px] text-muted mt-0.5">{{ $provider['implemented'] ? 'Implemented gateway' : 'Not implemented in this app' }}</p>
                            </div>
                        </div>
                        <x-backoffice.badge :tone="$provider['secret_configured'] ? 'success' : 'muted'">{{ $provider['secret_configured'] ? 'Keys configured' : 'No keys' }}</x-backoffice.badge>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4 text-[12px]">
                        <div>
                            <p class="text-muted font-sans mb-1.5">Webhooks (30d)</p>
                            @forelse ($provider['webhooks'] as $status => $count)
                                <p class="flex items-center justify-between gap-2 py-0.5"><span class="font-sans text-text">{{ \Illuminate\Support\Str::headline($status) }}</span><span class="tabular-nums text-text">{{ $count }}</span></p>
                            @empty
                                <p class="text-muted italic">No webhooks received.</p>
                            @endforelse
                            @if ($provider['signature_failures'] > 0)
                                <p class="mt-1.5 font-sans text-danger">{{ $provider['signature_failures'] }} invalid signature(s)</p>
                            @endif
                            @if ($provider['last_webhook'])
                                <p class="mt-1.5 font-sans text-muted">Last: {{ \Illuminate\Support\Carbon::parse($provider['last_webhook'])->format('j M, H:i') }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-muted font-sans mb-1.5">Payment intents (30d)</p>
                            @forelse ($provider['intents'] as $status => $count)
                                <p class="flex items-center justify-between gap-2 py-0.5"><span class="font-sans text-text">{{ \Illuminate\Support\Str::headline($status) }}</span><span class="tabular-nums text-text">{{ $count }}</span></p>
                            @empty
                                <p class="text-muted italic">No intents.</p>
                            @endforelse
                        </div>
                    </div>
                </x-backoffice.card>
            @endforeach
        </div>

        <x-backoffice.card title="Recent webhook failures">
            @forelse ($recentErrors as $error)
                <div class="flex items-start gap-3 py-2.5 border-b border-muted/10 last:border-0">
                    <i class="ri-error-warning-line text-danger text-[16px] shrink-0 mt-0.5"></i>
                    <div class="min-w-0 flex-1">
                        <p class="font-sans text-text text-[12px]"><span class="font-semibold">{{ $error->provider }}</span> · {{ $error->event_type }} · {{ \Illuminate\Support\Str::headline($error->processing_status) }}</p>
                        <p class="font-sans text-muted text-[11px] break-words">{{ \Illuminate\Support\Str::limit($error->processing_error, 300) }}</p>
                    </div>
                    <span class="font-sans text-muted text-[11px] shrink-0">{{ optional($error->received_at)->format('j M, H:i') }}</span>
                </div>
            @empty
                <p class="font-sans text-muted text-[13px] py-4 text-center">No webhook failures recorded.</p>
            @endforelse
        </x-backoffice.card>

        <p class="font-sans text-[12px] text-muted">Provider keys live in the server environment and are never shown here. Sending money, checking live balances, or triggering payouts from this screen is intentionally not possible — use your provider dashboard.</p>
    </x-backoffice.page>
</div>
