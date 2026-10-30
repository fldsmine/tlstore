{{--
    Unified app header for every authenticated screen. Row 1 is sticky (brand nav); Row 2 scrolls away.
    back: true (→ the parent page) or a URL/route string; badge: pill under the title (e.g. active package).
--}}
@props(['title' => null, 'subtitle' => null, 'badge' => null, 'back' => null, 'currency' => false])

@php
    $u = auth()->user();
    $initials = strtoupper(substr($u->first_name ?: $u->username, 0, 2));
    // Dashboard search and account settings are available before optional eSIM activation.
    $activated = (bool) $u->package_id;
    // `back="true"` arrives as the STRING "true"; treated as a URL it renders href="true",
    // a relative link that resolves to <current-path>/true. Only a real URL counts as one.
    $backIsUrl = is_string($back) && ! in_array(strtolower($back), ['true', '1', 'false', '0'], true);
    $backHref = $backIsUrl ? $back : \App\Support\Nav::parentUrl();
@endphp

@unless (session('mini_app'))
{{-- Two siblings (nav + title) into the page flex column, so the sticky nav's containing block
     is the whole page and it stays pinned for the full scroll. --}}
<div class="sticky top-0 z-30 -mx-4 md:-mx-8 lg:-mx-10 px-4 md:px-8 lg:px-10 bg-background/90 backdrop-blur-md border-b border-muted/10">
        <div class="h-[58px] flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0 lg:flex-1">
                <button type="button" @click="$dispatch('open-app-menu')"
                        class="lg:hidden size-10 rounded-full bg-surface shadow-sm flex items-center justify-center text-text shrink-0" aria-label="Open menu">
                    <i class="ri-menu-2-line text-[20px]"></i>
                </button>
                <a href="{{ route('home') }}" wire:navigate class="lg:hidden flex items-center min-w-0" aria-label="Nextel Connect home">
                    <img src="{{ asset('images/public/logo-dark.png') }}" alt="Nextel Connect" class="h-[26px] w-auto object-contain">
                </a>

                <button type="button" @click="$dispatch('open-search')"
                        class="hidden lg:flex items-center gap-2.5 h-10 w-full max-w-[360px] rounded-full bg-surface px-4 shadow-sm text-muted hover:bg-surface/70 transition" aria-label="Search">
                        <i class="ri-search-line text-[18px]"></i>
                        <span class="font-sans text-[13px]">Search anything…</span>
                        <span class="ml-auto font-sans text-muted/70 text-[10px] border border-muted/25 rounded px-1 leading-tight">⌘K</span>
                </button>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                {{-- Users without an activated package have no dashboard wallet switcher. --}}
                @if ($currency && ! $activated)
                    <livewire:dashboard.currency-switcher :key="'app-bar-currency'" />
                @endif
                <button type="button" @click="$dispatch('open-search')"
                        class="lg:hidden size-10 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Search">
                    <i class="ri-search-line text-[19px]"></i>
                </button>
                <a href="{{ route('profile') }}" wire:navigate
                   class="size-10 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]" aria-label="Your profile">
                    @if ($u->avatar)
                        <img src="{{ $u->avatar }}" alt="" class="size-full object-cover">
                    @else
                        <span class="font-heading font-semibold text-primary text-[14px]">{{ $initials }}</span>
                    @endif
                </a>
            </div>
        </div>
    </div>
@endunless

{{-- Row 2 — page header (scrolls away). --}}
@if ($title)
	<div class="flex items-center gap-2.5 pt-3">
		@if ($back)
			<a href="{{ $backHref }}" wire:navigate
			   class="lg:hidden size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text shrink-0" aria-label="Back">
				<i class="ri-arrow-left-s-line text-[22px]"></i>
			</a>
		@endif
		<div class="min-w-0 flex flex-col gap-1.5">
			<p class="font-heading font-medium text-text text-[22px] leading-tight truncate">{{ $title }}</p>
			@if ($badge)
				<span class="self-start inline-flex items-center gap-1.5 bg-primary/10 text-primary font-sans text-[12px] rounded-full px-2.5 py-1">
					<span class="size-1.5 rounded-full bg-primary"></span>{{ $badge }}
				</span>
			@elseif ($subtitle)
				<p class="font-sans text-muted text-[13px] truncate">{{ $subtitle }}</p>
			@endif
		</div>
	</div>
@endif
