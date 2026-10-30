<div>
    <section class="max-w-[760px] mx-auto px-5 md:px-8 py-10 md:py-16">
        @if ($order->isPaid())
            {{-- THE promise the checkout made, kept: the product is going to the email they gave us. --}}
            <div class="rounded-[20px] md:rounded-[28px] border border-primary/25 bg-surface/40 p-7 md:p-10 flex flex-col items-center text-center gap-5">
                <span class="size-16 rounded-full bg-primary/10 flex items-center justify-center">
                    <i class="ri-mail-check-line text-primary text-[30px]"></i>
                </span>

                <div class="flex flex-col gap-2">
                    <h1 class="font-heading font-semibold text-primary text-[26px] md:text-[32px] leading-tight">Payment successful</h1>
                    <p class="font-sans text-text text-[14px] md:text-[15px] leading-relaxed max-w-[560px]">
                        Thank you, {{ $order->full_name }}. Your <span class="text-primary">{{ $order->product_name }}</span> will be sent to the
                        email you provided:
                        <span class="font-semibold text-text">{{ $order->maskedEmail() }}</span>.
                    </p>
                </div>

                <dl class="w-full max-w-[440px] flex flex-col gap-2.5 rounded-[16px] border border-muted/15 bg-background px-5 py-4 text-left">
                    <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                        <dt class="text-muted">{{ $order->kindLabel() }}</dt>
                        <dd class="text-text">{{ $order->product_name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                        <dt class="text-muted">Amount paid</dt>
                        <dd class="text-text tabular-nums">{{ $order->formattedAmount() }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                        <dt class="text-muted">Reference</dt>
                        <dd class="text-text tabular-nums">{{ $order->reference }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                        <dt class="text-muted">Paid</dt>
                        <dd class="text-text">{{ $order->paid_at?->format('j M Y, g:i A') }}</dd>
                    </div>
                </dl>

                <p class="font-sans text-muted text-[12.5px] leading-relaxed max-w-[560px]">
                    A confirmation is already in your inbox. If you do not see the eSIM within a few hours,
                    check your spam folder first.
                    @if (config('settings.contact.email'))
                        Still nothing after that? Email
                        <a href="mailto:{{ config('settings.contact.email') }}" class="text-primary hover:underline">{{ config('settings.contact.email') }}</a>
                        with reference <span class="tabular-nums">{{ $order->reference }}</span> and we will sort it out.
                    @endif
                </p>

                <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                    <a href="{{ product_index_url() }}" wire:navigate
                       class="w-full sm:w-auto h-[48px] px-7 rounded-full border border-muted/20 font-sans text-[13px] text-text inline-flex items-center justify-center hover:border-primary/40 hover:text-primary transition-colors">
                        Back to the store
                    </a>
                    <a href="{{ product_main_url('auth/login') }}"
                       class="w-full sm:w-auto h-[48px] px-7 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center justify-center gap-2 hover:bg-primary/90 transition-colors">
                        Sign in to {{ config('app.name') }}
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            </div>
        @elseif ($order->status === \App\Models\ProductOrder::STATUS_PENDING)
            <div class="rounded-[20px] md:rounded-[28px] border border-muted/15 bg-surface/40 p-7 md:p-10 flex flex-col items-center text-center gap-4">
                <span class="size-16 rounded-full bg-[#d79937]/15 flex items-center justify-center">
                    <i class="ri-time-line text-[#d79937] text-[30px]"></i>
                </span>
                <h1 class="font-heading font-semibold text-primary text-[24px] md:text-[30px]">Confirming your payment</h1>
                <p class="font-sans text-text text-[14px] leading-relaxed max-w-[560px]">
                    We have not received the payment confirmation for order <span class="tabular-nums">{{ $order->reference }}</span> yet.
                    If you completed the payment, it will show up shortly — refresh this page in a moment, and the
                    {{ $order->product_name }} will be sent to {{ $order->maskedEmail() }} as soon as it does.
                </p>
                <p class="font-sans text-muted text-[12.5px]">
                    Nothing will be sent until the payment is confirmed. Do not pay twice.
                </p>
                <button type="button" onclick="window.location.reload()"
                        class="h-[46px] px-6 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 hover:bg-primary/90 transition-colors">
                    <i class="ri-refresh-line"></i>
                    Check again
                </button>
            </div>
        @else
            <div class="rounded-[20px] md:rounded-[28px] border border-danger/25 bg-surface/40 p-7 md:p-10 flex flex-col items-center text-center gap-4">
                <span class="size-16 rounded-full bg-danger/10 flex items-center justify-center">
                    <i class="ri-close-circle-line text-danger text-[30px]"></i>
                </span>
                <h1 class="font-heading font-semibold text-primary text-[24px] md:text-[30px]">Payment not completed</h1>
                <p class="font-sans text-text text-[14px] leading-relaxed max-w-[560px]">
                    Order <span class="tabular-nums">{{ $order->reference }}</span> for the {{ $order->product_name }} was not completed,
                    so nothing has been charged.
                    @if ($order->failure_reason)
                        {{ $order->failure_reason }}
                    @endif
                    You can start again whenever you are ready.
                </p>
                <a href="{{ product_index_url() }}" wire:navigate
                   class="h-[46px] px-6 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 hover:bg-primary/90 transition-colors">
                    Choose an eSIM
                    <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        @endif
    </section>
</div>
