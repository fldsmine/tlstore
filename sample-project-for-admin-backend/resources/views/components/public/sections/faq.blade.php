<section id="faq" class="bg-background" x-data="{ open: null }">

    @php
        $faqs = [
            ['q' => 'What is Nextel?', 'a' => 'Nextel is a virtual telecom operator and performance advertising network with a verified audience that brands pay to reach.'],
            ['q' => 'How does Nextel work?', 'a' => 'Nextel connects top brands with a verified, high-value audience. It\'s a win-win ecosystem built on mutual growth: brands spend to reach, and our audience engages to earn!'],
            ['q' => 'How do I get started?', 'a' => 'Register for free and activate your eSIM. Stay connected, grow, and earn.'],
            ['q' => 'What ad formats are available on Nextel?', 'a' => 'The three ad formats available on Nextel are: Sponsored Calls, Sponsored SMS, and Interactive Push Alerts.'],
            ['q' => 'What is Nextel eSIM?', 'a' => 'Nextel eSIM is a unique virtual number. It serves as your membership card, your financial access pass, and your proof of belonging on the network.'],
            ['q' => 'What are the eSIM plans?', 'a' => 'There are two distinct eSIM plans: Diamond and Royal. The eSIM plan you choose determines the level of services and products you\'ll get.'],
            ['q' => 'What is Nextel\'s personalized website?', 'a' => 'NexPage is a free personal website that Nextel builds and launches for you the moment you activate your eSIM. You do not design it, host it, or manage it; Nextel handles all of that. It earns money for you through ad revenue and VTU commissions as Nextel drives traffic to it daily.'],
            ['q' => 'How do I make money on Nextel?', 'a' => 'You can confidently make money on Nextel through Sponsored Calls, Sponsored SMS, becoming a shareholder, and having a personalized website!'],
            ['q' => 'How do I withdraw?', 'a' => 'You can spend your earnings using a personalized card, or withdraw via bank transfer.'],
        ];
    @endphp

    <div class="md:hidden flex flex-col gap-[30px] px-[16px] py-[100px]">

        <div class="flex flex-col gap-[10px] pb-[20px]">
            <p class="font-heading text-[24px] text-muted leading-normal">FAQ</p>
            <p class="font-heading font-semibold leading-tight">
                <span class="text-primary text-[32px]">Common questions </span><span class="text-[32px]" style="color: #8c8c8c">answered</span>
            </p>
        </div>

        <div class="w-full">
            @foreach($faqs as $i => $faq)
            <div class="border-t border-[#cacaca]">
                <button
                    @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                    class="w-full flex items-center justify-between px-[20px] py-[25px] text-left"
                    :class="open === {{ $i }} ? 'gap-[20px] py-[30px]' : 'gap-[20px]'">
                    <span class="font-heading font-medium text-text text-[18px] leading-snug flex-1 min-w-0">
                        {{ $faq['q'] }}
                    </span>
                    <div class="w-[35px] h-[35px] rounded-full border flex-shrink-0 flex items-center justify-center transition-all duration-200"
                         :class="open === {{ $i }} ? 'bg-text border-text' : 'bg-transparent border-[#cacaca]'">
                        <svg x-show="open !== {{ $i }}" class="w-[11px] h-[11px] text-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
                        </svg>
                        <svg x-show="open === {{ $i }}" class="w-[11px] h-[11px] text-surface" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/>
                        </svg>
                    </div>
                </button>
                <div x-show="open === {{ $i }}" x-collapse class="px-[20px] pb-[30px]">
                    <p class="font-sans text-muted text-[16px] leading-relaxed">{{ $faq['a'] }}</p>
                </div>
            </div>
            @endforeach
        </div>

    </div>

    <div class="hidden md:block py-[150px]" style="border-top: 1px solid #8c8c8c;">
        <div class="max-w-[1280px] mx-auto px-[60px]">

        <div class="mb-16">
            <p class="font-heading text-[24px] text-muted mb-4">FAQ</p>
            <h2 class="font-heading font-semibold leading-tight">
                <span class="text-primary text-[50px]">Common questions </span><span class="text-[50px]" style="color: #8c8c8c">answered</span>
            </h2>
        </div>

        <div class="max-w-5xl">
            @foreach($faqs as $i => $faq)
            <div class="border-t border-border">
                <button
                    @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                    class="w-full flex items-center justify-between p-[30px] text-left gap-6">
                    <span class="font-heading font-medium text-text text-[26px] leading-snug">
                        {{ $faq['q'] }}
                    </span>
                    <div class="w-[60px] h-[60px] rounded-full border flex-shrink-0 flex items-center justify-center transition-all duration-200"
                         :class="open === {{ $i }} ? 'bg-text border-text' : 'bg-transparent border-border'">
                        <svg x-show="open !== {{ $i }}" class="w-[14px] h-[14px] text-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 5v14M5 12h14"/>
                        </svg>
                        <svg x-show="open === {{ $i }}" class="w-[14px] h-[14px] text-surface" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/>
                        </svg>
                    </div>
                </button>
                <div x-show="open === {{ $i }}" x-collapse class="px-[30px] pb-[30px]">
                    <p class="font-sans text-muted text-[20px] leading-relaxed max-w-4xl">{{ $faq['a'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
        </div>

    </div>

</section>
