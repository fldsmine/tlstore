@php
    $earners = $this->earners();
    $first = $earners[0] ?? null;
    $rest = array_slice($earners, 1);
    $ordinal = fn (int $n) => $n.(['1' => 'st', '2' => 'nd', '3' => 'rd'][$n] ?? 'th');
    $pic = fn (?string $a) => $a ?: asset('images/top-earners/earner.jpg');
@endphp

<div>
    <x-public.page-hero badge="Top Earners" title="Our<br>Top Users" breadcrumb="Top Earners" />

    <x-public.shell class="bg-surface">
        <div x-data="{ cur: 'usd' }" class="max-w-[700px] mx-auto flex flex-col items-center gap-14">

            <div class="inline-flex items-center gap-1 rounded-full bg-background p-1">
                <button type="button" @click="cur = 'usd'"
                        :class="cur === 'usd' ? 'bg-primary text-surface shadow-sm' : 'text-muted hover:text-text'"
                        class="px-7 h-11 rounded-full font-heading font-semibold text-[14px] transition">USD</button>
                <button type="button" @click="cur = 'ngn'"
                        :class="cur === 'ngn' ? 'bg-primary text-surface shadow-sm' : 'text-muted hover:text-text'"
                        class="px-7 h-11 rounded-full font-heading font-semibold text-[14px] transition">NGN</button>
            </div>

            @if ($first)
                <div class="flex flex-col items-center gap-5">
                    <div class="relative">
                        <img src="{{ asset('images/top-earners/crown.png') }}" alt=""
                             class="absolute -top-9 -left-3 w-[70px] rotate-[-25deg] pointer-events-none select-none">
                        <img src="{{ $pic($first['avatar']) }}" alt="{{ $first['name'] }}"
                             class="w-[150px] h-[144px] rounded-[16px] object-cover object-top">
                    </div>
                    <div class="flex flex-col items-center gap-2.5">
                        <p class="font-heading font-bold text-text text-[26px] leading-tight tracking-tight">{{ $first['name'] }}</p>
                        <p class="font-heading font-bold text-primary text-[19px] leading-none tabular-nums">
                            <span x-show="cur === 'usd'">${{ $first['usd'] }}</span>
                            <span x-show="cur === 'ngn'" x-cloak>₦{{ $first['ngn'] }}</span>
                        </p>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-3 gap-x-8 gap-y-14 w-full place-items-center">
                @foreach ($rest as $e)
                    <div class="flex flex-col items-center gap-4">
                        <img src="{{ $pic($e['avatar']) }}" alt="{{ $e['name'] }}"
                             class="w-[124px] h-[118px] rounded-[15px] object-cover object-top">
                        <div class="flex flex-col items-center gap-2">
                            <p class="font-sans font-semibold text-primary text-[12px] uppercase tracking-[0.08em]">{{ $ordinal($e['rank']) }}</p>
                            <p class="font-heading font-bold text-text text-[20px] leading-tight tracking-tight">{{ $e['name'] }}</p>
                            <p class="font-heading font-semibold text-primary text-[16px] leading-none tabular-nums">
                                <span x-show="cur === 'usd'">${{ $e['usd'] }}</span>
                                <span x-show="cur === 'ngn'" x-cloak>₦{{ $e['ngn'] }}</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-public.shell>
</div>
