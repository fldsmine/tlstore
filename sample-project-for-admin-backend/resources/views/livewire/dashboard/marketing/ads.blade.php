<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Ads Revenue'" :subtitle="'Ads you\'ve shared and what you earned'" />

        @if (! $enabled)
            <x-app.feature-locked class="mt-2" :title="config('settings.ads.disabled_title')" :message="config('settings.ads.disabled_message')" />
        @else
            @php
                $fmt = fn ($n) => $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'k' : number_format($n);
            @endphp

            <div class="relative overflow-hidden bg-surface border border-[#f8f8f8] rounded-[20px] p-5 flex flex-col gap-[15px] items-center shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <div class="pointer-events-none absolute -left-10 top-[44px] w-[420px] h-[200px] rounded-full blur-2xl opacity-50"
                     style="background: radial-gradient(circle at 50% 50%, rgba(220,235,106,0.55), rgba(220,235,106,0) 70%);"></div>

                <div class="relative flex flex-col gap-[15px] items-center">
                    <p class="font-sans text-muted text-[12px]">Earned Today</p>
                    <x-money :usd="$today" :decimals="'auto'" class="font-heading font-semibold text-text text-[32px] leading-none" />
                </div>

                <div class="relative w-full h-px bg-[#e7e4dc]"></div>

                <div class="relative w-full flex items-center justify-between">
                    <div class="flex flex-col gap-[15px] items-center flex-1">
                        <p class="font-sans text-muted text-[12px]">This Week</p>
                        <x-money :usd="$week" :decimals="'auto'" class="font-heading font-medium text-text text-[24px] leading-none" />
                    </div>
                    <div class="w-px h-[60px] bg-[#e7e4dc] shrink-0"></div>
                    <div class="flex flex-col gap-[15px] items-center flex-1">
                        <p class="font-sans text-muted text-[12px]">This Month</p>
                        <x-money :usd="$month" :decimals="'auto'" class="font-heading font-medium text-text text-[24px] leading-none" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5 rounded-[16px] bg-secondary/15 px-4 py-3.5">
                <i class="ri-money-dollar-circle-line text-primary text-[18px] shrink-0"></i>
                <p class="font-sans text-[12px] text-muted leading-relaxed"><span class="font-semibold text-text">{{ money($lifetime, 'auto') }} earned from ads so far.</span> Share the sponsored slots on your website to earn — rewards land in your Ads wallet instantly.</p>
            </div>

            <a href="{{ route('my-website') }}" wire:navigate class="h-[46px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition">
                <i class="ri-share-forward-line text-[16px]"></i> Go to your website to share
            </a>

            <div class="bg-surface rounded-[20px] overflow-hidden shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <div class="px-5 py-4 border-b border-muted/10">
                    <p class="font-heading font-medium text-text text-[15px]">Share history</p>
                    <p class="font-sans text-muted text-[12px] mt-0.5">{{ $shares->total() }} share{{ $shares->total() === 1 ? '' : 's' }} recorded</p>
                </div>

                @forelse ($shares as $share)
                    <div wire:key="share-{{ $share->id }}" class="flex items-center gap-3 px-5 py-3.5 border-b border-muted/5 last:border-0">
                        <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0 overflow-hidden">
                            @if ($share->ad?->image_url)
                                <img src="{{ $share->ad->image_url }}" alt="" class="size-full object-cover">
                            @else
                                <i class="ri-advertisement-line text-[18px]"></i>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-sans text-text text-[14px] truncate">{{ $share->ad?->name ?? 'Removed ad' }}</p>
                            <p class="font-sans text-muted text-[12px] truncate">{{ $share->ad?->advertiser }} · {{ $share->shared_at?->format('j M Y, g:i A') }}</p>
                            @if ($share->ad?->description)
                                <p class="font-sans text-muted text-[12px] mt-1 leading-snug line-clamp-2">{{ $share->ad->description }}</p>
                            @endif
                            {{-- The AD's running totals, not this user's — one campaign is shared by many
                                 people, so these say how the ad is doing, not how far your share reached. --}}
                            @if ($share->ad)
                                <div class="flex items-center gap-3 mt-1.5 font-sans text-muted text-[11.5px]">
                                    @foreach ([
                                        ['ri-slideshow-line', $share->ad->impressions, 'impressions'],
                                        ['ri-eye-line', $share->ad->views, 'views'],
                                        ['ri-share-forward-line', $share->ad->shares, 'shares'],
                                    ] as [$icon, $count, $label])
                                        <span class="inline-flex items-center gap-1 tabular-nums" title="{{ number_format((float) $count) }} {{ $label }} on this ad">
                                            <i class="{{ $icon }} text-[13px]"></i><x-figure :value="$count" />
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="shrink-0 font-heading font-semibold text-success text-[14px]">+<x-money :usd="$share->amount" :decimals="'auto'" /></span>
                    </div>
                @empty
                    <div class="px-5 py-10 flex flex-col items-center gap-2 text-center">
                        <i class="ri-share-forward-line text-muted text-[28px]"></i>
                        <p class="font-sans text-muted text-[14px]">You haven&rsquo;t shared any ads yet.</p>
                        <p class="font-sans text-muted/70 text-[12px]">Sponsored slots appear on your website page — share one to start earning.</p>
                    </div>
                @endforelse

                @if ($shares->hasPages())
                    <div class="px-5 py-3 border-t border-muted/10">{{ $shares->links() }}</div>
                @endif
            </div>
        @endif
    </div>

    <x-app.bottom-nav />
</div>
