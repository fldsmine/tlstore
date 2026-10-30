{{-- Global image lightbox — open from anywhere with: $dispatch('open-image', { url: '…' }). --}}
<div x-data="{ url: null }"
     @open-image.window="url = $event.detail.url"
     @keydown.escape.window="url = null"
     x-show="url" x-cloak x-transition.opacity
     @click="url = null"
     class="fixed inset-0 z-[120] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
    <img :src="url" @click.stop alt="Preview"
         class="max-w-full max-h-[88vh] rounded-[14px] object-contain shadow-2xl">
    <button type="button" @click="url = null" aria-label="Close"
            class="absolute top-4 right-4 size-10 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition">
        <i class="ri-close-line text-[22px]"></i>
    </button>
</div>
