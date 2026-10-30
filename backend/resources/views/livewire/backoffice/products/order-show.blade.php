<div>
    <x-backoffice.page :title="$order->product_name.' order'" subtitle="{{ $order->reference }}" back="{{ route('backoffice.products.orders') }}">
        @php
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
            $canWrite = $this->canWrite();
        @endphp

        <div class="grid lg:grid-cols-[1.15fr_1fr] gap-5 items-start">
            <div class="flex flex-col gap-5 min-w-0">
                <x-backoffice.card title="Order">
                    <dl class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Status</dt>
                            <dd><x-backoffice.badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-backoffice.badge></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Product</dt>
                            <dd class="text-text">{{ $order->product_name }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Amount</dt>
                            <dd class="text-text tabular-nums">{{ $order->formattedAmount() }} <span class="text-muted">({{ $order->currency }})</span></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Provider</dt>
                            <dd class="text-text">{{ ucfirst((string) $order->provider) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Started</dt>
                            <dd class="text-text">{{ $order->created_at?->format('j M Y, g:i A') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Paid</dt>
                            <dd class="text-text">{{ $order->paid_at?->format('j M Y, g:i A') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Fulfilled</dt>
                            <dd class="text-text">{{ $order->fulfilled_at?->format('j M Y, g:i A') ?? '—' }}</dd>
                        </div>
                        @if ($order->failure_reason)
                            <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                                <dt class="text-muted">Reason</dt>
                                <dd class="text-danger text-right">{{ $order->failure_reason }}</dd>
                            </div>
                        @endif
                        @if ($order->fulfilment_note)
                            <div class="flex items-start justify-between gap-4 font-sans text-[13px]">
                                <dt class="text-muted shrink-0">Note</dt>
                                <dd class="text-text text-right">{{ $order->fulfilment_note }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-backoffice.card>

                <x-backoffice.card title="Buyer">
                    <dl class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Full name</dt>
                            <dd class="text-text">{{ $order->full_name }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Phone</dt>
                            <dd class="text-text tabular-nums">{{ $order->phone }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Email</dt>
                            <dd><a href="mailto:{{ $order->email }}" class="text-primary hover:underline">{{ $order->email }}</a></dd>
                        </div>
                    </dl>
                    <p class="font-sans text-muted text-[11.5px] mt-4 leading-relaxed">
                        This is a guest order — there is no account behind it. The product goes to the email above.
                    </p>
                </x-backoffice.card>

                <x-backoffice.card title="Payment trail">
                    <dl class="flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Our reference</dt>
                            <dd class="text-text tabular-nums">{{ $order->reference }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Gateway reference</dt>
                            <dd class="text-text tabular-nums">{{ $order->gateway_reference ?: '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Ledger</dt>
                            <dd>
                                @if ($reference)
                                    <x-backoffice.badge tone="success">Settled {{ $reference->settled_at?->format('j M Y, g:i A') }}</x-backoffice.badge>
                                @else
                                    <x-backoffice.badge tone="muted">No ledger row</x-backoffice.badge>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Confirmation sent</dt>
                            <dd class="text-text">{{ $order->confirmation_sent_at?->format('j M Y, g:i A') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">IP</dt>
                            <dd class="text-text tabular-nums">{{ $order->ip_address ?: '—' }}</dd>
                        </div>
                    </dl>

                    @if ($order->checkout_url)
                        <p class="font-sans text-muted text-[11.5px] mt-4 break-all">
                            Checkout link: <a href="{{ $order->checkout_url }}" target="_blank" rel="noopener" class="text-primary hover:underline">{{ $order->checkout_url }}</a>
                        </p>
                    @endif

                    @if (! empty($order->meta['reconcile_error']) || ! empty($order->meta['manual_review']))
                        <div class="mt-4 rounded-[12px] border border-[#d79937]/40 bg-[#d79937]/10 px-4 py-3">
                            <p class="font-sans text-[12px] text-[#d79937] font-medium">Flagged at settlement</p>
                            <p class="font-sans text-[11.5px] text-muted mt-1 break-words">{{ json_encode($order->meta['reconcile_error'] ?? $order->meta['manual_review']) }}</p>
                        </div>
                    @endif
                </x-backoffice.card>
            </div>

            <div class="flex flex-col gap-5 min-w-0">
                <x-backoffice.card title="Send the product">
                    @if (! $order->isPaid())
                        <p class="font-sans text-muted text-[13px]">
                            Nothing can be sent yet: this order has not been paid. A guest order is only marked paid after the gateway
                            confirms the payment server-side.
                        </p>
                    @elseif (! $canWrite)
                        <p class="font-sans text-muted text-[13px]">Your role cannot send emails. Ask a regular or super admin to fulfil this order.</p>
                    @else
                        <p class="font-sans text-muted text-[12px] -mt-1 mb-4 leading-relaxed">
                            What you type is emailed to <span class="text-text">{{ $order->email }}</span> exactly as written, along with the order reference and your note.
                            Line breaks are kept. Only the fact that it was sent is stored — the details themselves are not saved.
                        </p>

                        <form wire:submit="send" class="flex flex-col gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Product details to send</label>
                                <textarea wire:model="deliveryDetails" rows="9" spellcheck="false"
                                          placeholder="e.g.&#10;Activation code: NXT-XXXX-XXXX&#10;1. Download the Nextel app&#10;2. Sign up with this email address&#10;3. Enter the code to activate your {{ $order->product_name }}"
                                          class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 w-full"></textarea>
                                @error('deliveryDetails') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Internal note (optional, also shown to the buyer)</label>
                                <input type="text" wire:model="deliveryNote" maxlength="255"
                                       placeholder="e.g. Welcome aboard — activation support is open 9am–9pm."
                                       class="{{ $input }} w-full">
                                @error('deliveryNote') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>

                            <button type="submit" wire:loading.attr="disabled" wire:target="send"
                                    class="h-[46px] rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center justify-center gap-2 disabled:opacity-60">
                                <i wire:loading.remove wire:target="send" class="ri-mail-send-line text-[16px]"></i>
                                <i wire:loading wire:target="send" class="ri-loader-4-line animate-spin text-[16px]"></i>
                                {{ $order->isFulfilled() ? 'Send again' : 'Send to the buyer' }}
                            </button>

                            @if ($order->isFulfilled())
                                <p class="font-sans text-muted text-[11.5px]">
                                    Already sent {{ $order->fulfilled_at?->format('j M Y, g:i A') }} — sending again takes another delivery, it does not undo the first.
                                </p>
                            @endif
                        </form>
                    @endif
                </x-backoffice.card>
            </div>
        </div>
    </x-backoffice.page>
</div>
