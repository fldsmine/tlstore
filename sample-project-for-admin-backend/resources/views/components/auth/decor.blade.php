{{-- Mobile decorative header. Hidden on desktop (the brand rail takes over). --}}
@props(['back' => null])

<div class="absolute inset-x-0 top-0 h-[210px] overflow-hidden lg:hidden pointer-events-none">
    <div class="absolute -top-[75px] -right-[45px] size-[260px] rounded-full bg-[#e9e4dc]"></div>
    <div class="absolute top-[55px] -right-[15px] size-[150px] rounded-full bg-[#c9c4b8]"></div>
    <div class="absolute top-[10px] -left-[6px] size-[122px] rounded-full bg-primary"></div>
    <div class="absolute inset-0 opacity-[0.06]" style="background-image:url('data:image/svg+xml,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20width=%27140%27%20height=%27140%27%3E%3Cfilter%20id=%27n%27%3E%3CfeTurbulence%20type=%27fractalNoise%27%20baseFrequency=%270.8%27%20numOctaves=%272%27%20stitchTiles=%27stitch%27/%3E%3C/filter%3E%3Crect%20width=%27100%25%27%20height=%27100%25%27%20filter=%27url(%23n)%27/%3E%3C/svg%3E')"></div>
</div>

<a href="{{ $back ?? route('home') }}" wire:navigate class="absolute top-[50px] left-[28px] z-20 inline-flex items-center gap-2.5 text-surface lg:hidden">
    <svg class="w-2.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
    <span class="font-sans text-[16px]">Back</span>
</a>

{{-- Brand mark over the beige header circles; the dark variant reads against that light backdrop. --}}
<img src="{{ asset('images/public/logo-dark.png') }}" alt="Nextel Connect"
     class="absolute top-[44px] right-[24px] z-20 h-[30px] w-auto object-contain lg:hidden">

