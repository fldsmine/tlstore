<div class="bg-background">

    <section id="home" class="relative overflow-hidden bg-background">
        <x-public.sections.hero-mobile />
        <x-public.sections.hero-desktop />
    </section>

    <x-public.sections.calculator />

    {{-- About us — mobile only --}}
    <x-public.sections.about />

    <x-public.sections.how-it-works />

    {{-- Packages — desktop only --}}
    <x-public.sections.packages />

    {{-- Order swaps: mobile shows FAQ first, desktop shows CTA first --}}
    <div class="flex flex-col">
        <div class="order-2 md:order-1"><x-public.sections.cta /></div>
        <div class="order-1 md:order-2"><x-public.sections.faq /></div>
    </div>

</div>
