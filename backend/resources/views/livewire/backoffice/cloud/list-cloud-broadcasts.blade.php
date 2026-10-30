<div>
    <x-backoffice.page title="Broadcasts" subtitle="Incoming sponsored calls pushed to online users">

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($broadcasts->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-broadcast-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No broadcasts yet.</p>
                    <p class="font-sans text-muted text-[12px]">Open a sponsor number and tap “Send broadcast”.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="font-sans text-muted text-[12px] border-b border-muted/10">
                                <th class="px-5 py-3 font-medium">Sponsor</th>
                                <th class="px-5 py-3 font-medium">Sent</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium text-right">Delivered</th>
                                <th class="px-5 py-3 font-medium text-right">Answered</th>
                                <th class="px-5 py-3 font-medium text-right">Declined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($broadcasts as $b)
                                <tr class="border-b border-muted/5 last:border-0">
                                    <td class="px-5 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $b->number_name }}</p>
                                        @if ($b->caller_name)<p class="font-sans text-muted text-[12px]">{{ $b->caller_name }}</p>@endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $b->created_at->format('j M Y, g:i A') }}</td>
                                    <td class="px-5 py-3">
                                        @if ($b->isExpired())
                                            <x-backoffice.badge tone="muted">Expired</x-backoffice.badge>
                                        @else
                                            <x-backoffice.badge tone="success">Live · ends {{ $b->expires_at->format('g:i A') }}</x-backoffice.badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-sans text-text text-[13px] tabular-nums">{{ $b->delivered_count }}</td>
                                    <td class="px-5 py-3 text-right font-heading text-success text-[13px] tabular-nums">{{ $b->answered_count }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] tabular-nums">{{ $b->declined_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($broadcasts->hasPages())
                    <div class="px-5 py-3 border-t border-muted/10">{{ $broadcasts->links() }}</div>
                @endif
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
