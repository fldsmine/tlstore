<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Receipt'" :back="true" />

        @php
            $s = $order->status;
            $hero = [
                'delivered' => ['bg-success/12', 'text-success', 'ri-checkbox-circle-fill', 'Purchase successful'],
                'pending'   => ['bg-warning/15', 'text-warning', 'ri-loader-4-line', 'Processing your purchase'],
                'failed'    => ['bg-danger/12', 'text-danger', 'ri-close-circle-fill', 'Purchase failed'],
                'reversed'  => ['bg-info/12', 'text-info', 'ri-arrow-go-back-line', 'Purchase reversed'],
            ][$s->value] ?? ['bg-muted/12', 'text-muted', 'ri-information-line', $s->getName()];
        @endphp

        {{-- Centered receipt column; app-bar above stays full-width. --}}
        <div class="flex flex-col gap-[22px] w-full lg:max-w-[480px] lg:mx-auto">

            <div class="bg-surface rounded-[24px] p-7 flex flex-col items-center text-center gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] mt-2">
                <span class="size-16 rounded-full {{ $hero[0] }} flex items-center justify-center"><i class="{{ $hero[2] }} {{ $hero[1] }} text-[32px]"></i></span>
                <p class="font-heading font-medium text-text text-[19px]">{{ $hero[3] }}</p>
                <p class="font-heading font-semibold text-text text-[34px] leading-none">₦{{ number_format((float) $order->amount_ngn, 0) }}</p>
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 {{ $hero[0] }}">
                    <span class="font-sans text-[12px] {{ $hero[1] }}">{{ $s->getName() }}</span>
                </span>

                @if ($s->isPending())
                    <p class="font-sans text-muted text-[12px] leading-relaxed max-w-[300px]">This is taking a moment to confirm. We’ll update it automatically — check your transactions shortly.</p>
                @elseif ($s->isRefunded())
                    <p class="font-sans text-muted text-[12px] leading-relaxed max-w-[300px]">Your wallet was not charged. You can try again.</p>
                @endif
            </div>

            @if (filled($order->token))
                <div class="bg-secondary/15 rounded-[18px] p-4 flex flex-col gap-1.5">
                    <p class="font-sans text-[12px] text-muted">Token</p>
                    <p class="font-heading font-semibold text-text text-[18px] break-all select-all">{{ $order->token }}</p>
                </div>
            @endif

            <div class="bg-surface rounded-[18px] divide-y divide-background shadow-sm">
                @foreach (array_filter([
                    ['Service', $category['label'] ?? ucfirst($order->service)],
                    ['Provider', $order->meta['provider_label'] ?? $order->service_id],
                    ! empty($order->meta['plan_label']) ? ['Plan', $order->meta['plan_label']] : null,
                    ['Recipient', $order->billers_code ?: $order->phone],
                    ! empty($order->meta['customer_name']) ? ['Name', $order->meta['customer_name']] : null,
                    ['Amount', '₦'.number_format((float) $order->amount_ngn, 2)],
                    ['Debited', money((float) $order->amount_usd)],
                    ['Date', $order->created_at?->format('j M, Y · g:i A')],
                    ['Reference', $order->request_id],
                ]) as [$label, $value])
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                        <span class="font-sans text-text text-[13px] text-right truncate max-w-[220px]">{{ $value }}</span>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('transactions') }}" wire:navigate class="flex-1 h-[50px] rounded-full bg-surface text-text font-sans font-semibold text-[14px] flex items-center justify-center shadow-sm">Transactions</a>
                <a href="{{ route('vas') }}" wire:navigate class="flex-1 h-[50px] rounded-full bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center hover:opacity-95 transition">Done</a>
            </div>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
