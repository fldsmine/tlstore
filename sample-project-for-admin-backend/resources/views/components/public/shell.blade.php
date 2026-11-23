{{-- Shares the 1280px / 60px gutter with the navbar + footer. --}}
<section {{ $attributes->merge(['class' => 'py-[30px] md:py-[70px]']) }}>
    <div class="max-w-[1280px] mx-auto px-5 md:px-[60px]">
        {{ $slot }}
    </div>
</section>
