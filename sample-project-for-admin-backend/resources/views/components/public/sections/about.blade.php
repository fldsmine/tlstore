<section id="about" class="md:hidden bg-background py-[100px] px-[20px]">

    <div class="flex flex-col items-center relative w-full mb-[30px] gap-[24px]">

        <div class="flex flex-col gap-[10px] items-start w-full">
            <div class="font-heading text-primary">
                <p class="font-heading text-[32px] leading-normal">What Sets Us Apart</p>
                <p class="font-heading text-[18px] leading-normal">AT NEXTEL CONCEPT.</p>
            </div>
            <p class="font-sans text-muted text-[14px] leading-relaxed">
                We deliver your campaigns directly over active mobile networks with 100% verified human reach and zero bot engagement.
            </p>
        </div>

        <img src="{{ asset('images/about/welcome-pack.jpg') }}" alt="Nextel Connect Welcome Back Pack"
             class="w-full h-auto rounded-[26px]" />

    </div>

    <div class="flex gap-[10px] overflow-x-auto pb-2 mb-[30px] w-full" style="-webkit-overflow-scrolling: touch;">
        @foreach(['Direct Placement', 'Global Scale', 'Verified Traffic', 'Custom Spending'] as $pill)
            <span class="flex-shrink-0 font-heading text-[16px] leading-normal text-secondary bg-primary border border-secondary rounded-[24px] px-[14px] py-[14px] whitespace-nowrap">{{ $pill }}</span>
        @endforeach
    </div>

    <div class="flex flex-col gap-[30px] w-full">

        <div class="bg-white rounded-[26px] pb-[30px] pt-[16px] px-[16px] flex flex-col gap-[30px] w-full">
            <div class="bg-[#eeeeee] rounded-[12px] size-[40px] flex items-center justify-center flex-shrink-0">
                <i class="ri-line-chart-line text-[22px] text-text"></i>
            </div>
            <div class="flex flex-col gap-[10px]">
                <p class="font-heading font-medium text-text text-[20px] leading-normal">Nextel Shares</p>
                <p class="font-sans text-muted text-[14px] leading-relaxed">Become our primary shareholder and unlock the ability to earn from every single minute of call activity generated. Our real-time engine ensures you never miss a second of potential revenue.</p>
            </div>
        </div>

        <div class="bg-white rounded-[26px] pb-[30px] pt-[16px] px-[16px] flex flex-col gap-[30px] w-full">
            <div class="bg-[#eeeeee] rounded-[12px] size-[40px] flex items-center justify-center flex-shrink-0">
                <i class="ri-global-line text-[22px] text-text"></i>
            </div>
            <div class="flex flex-col gap-[10px]">
                <p class="font-heading font-medium text-text text-[20px] leading-normal">Global ad distribution.</p>
                <p class="font-sans text-muted text-[14px] leading-relaxed">Reach targeted, real, business minded audiences worldwide with automated, real time ad placements that suit your budget.</p>
            </div>
        </div>

        <div class="bg-white rounded-[26px] pb-[30px] pt-[16px] px-[16px] flex flex-col gap-[30px] w-full">
            <div class="bg-[#eeeeee] rounded-[12px] size-[40px] flex items-center justify-center flex-shrink-0">
                <i class="ri-shield-check-line text-[22px] text-text"></i>
            </div>
            <div class="flex flex-col gap-[10px]">
                <p class="font-heading font-medium text-text text-[20px] leading-normal">Verified active users.</p>
                <p class="font-sans text-muted text-[14px] leading-relaxed">We protect your campaign with top notch network device verification that guarantees every connection delivers 100% real human interaction.</p>
            </div>
        </div>

        <div class="bg-white rounded-[26px] pb-[30px] pt-[16px] px-[16px] flex flex-col gap-[30px] w-full">
            <div class="bg-[#eeeeee] rounded-[12px] size-[40px] flex items-center justify-center flex-shrink-0">
                <i class="ri-bank-card-line text-[22px] text-text"></i>
            </div>
            <div class="flex flex-col gap-[10px]">
                <p class="font-heading font-medium text-text text-[20px] leading-normal">Physical Cards</p>
                <p class="font-sans text-muted text-[14px] leading-relaxed">Our physical card bridges the gap between earning and spending, giving you the freedom to spend your earnings easily, wherever you are. And with convenient doorstep delivery, your card comes straight to you.</p>
            </div>
        </div>

    </div>

</section>
