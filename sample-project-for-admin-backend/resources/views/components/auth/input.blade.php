{{-- Auth pill input (Figma 381:2514). --}}
@props(['label' => null, 'type' => 'text', 'name' => null])

<div class="flex flex-col gap-1.5 w-full">
    @if ($label)
        <label class="font-sans text-[14px] text-text">{{ $label }}</label>
    @endif

    <div class="group flex items-center gap-2.5 h-[45px] px-[15px] rounded-[30px] border-[0.5px] border-primary focus-within:ring-1 focus-within:ring-primary transition">
        @isset($leading)
            <span class="shrink-0 text-muted flex items-center">{{ $leading }}</span>
        @endisset

        <input type="{{ $type }}"
               {{ $attributes->merge(['class' => 'flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted placeholder:text-[12px] focus:ring-0']) }}>

        @isset($trailing)
            <span class="shrink-0 flex items-center">{{ $trailing }}</span>
        @endisset
    </div>

    @if ($name)
        @error($name)<span class="font-sans text-[12px] text-danger px-2">{{ $message }}</span>@enderror
    @endif
</div>
