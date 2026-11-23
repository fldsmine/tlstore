{{-- Shared conversation composer: text + image/PDF attachment. wire:* directives bind to the parent
     Livewire component ($body/$attachment + send/sendAttachment/removeAttachment/typing). --}}
@props(['attachment' => null])

<div class="border-t border-background bg-surface px-3 py-2.5 shrink-0">
    @if ($attachment)
        <div class="mb-2 flex items-center gap-3 rounded-[14px] border border-background bg-background p-2.5">
            @if ($attachment->isPreviewable())
                <img src="{{ $attachment->temporaryUrl() }}" class="size-12 rounded-[10px] object-cover shrink-0" alt="preview">
            @else
                <span class="size-12 rounded-[10px] bg-surface flex items-center justify-center shrink-0"><i class="ri-file-pdf-2-line text-danger text-[22px]"></i></span>
            @endif
            <p class="flex-1 min-w-0 font-sans text-[12px] text-text truncate">{{ $attachment->getClientOriginalName() }}</p>
            <button type="button" wire:click="removeAttachment" class="size-8 rounded-full flex items-center justify-center text-muted hover:bg-surface transition" aria-label="Remove"><i class="ri-close-line text-[18px]"></i></button>
            <button type="button" wire:click="sendAttachment" wire:loading.attr="disabled" wire:target="sendAttachment" class="h-9 px-4 rounded-full bg-primary text-surface font-sans font-semibold text-[12.5px] disabled:opacity-60">
                <span wire:loading.remove wire:target="sendAttachment">Send</span><span wire:loading wire:target="sendAttachment">…</span>
            </button>
        </div>
    @endif
    @error('attachment') <p class="text-danger text-[11px] font-sans mb-1.5 px-1">{{ $message }}</p> @enderror

    <form wire:submit="send" class="flex items-end gap-2">
        <label class="size-10 shrink-0 rounded-full bg-background flex items-center justify-center text-primary cursor-pointer hover:bg-primary/10 transition" aria-label="Attach a file">
            <i class="ri-attachment-2 text-[19px]"></i>
            <input type="file" wire:model="attachment" class="hidden" accept=".jpg,.jpeg,.png,.pdf">
        </label>
        <div class="flex-1 min-w-0 rounded-[22px] bg-background">
            <textarea wire:model="body" rows="1" placeholder="Type a message…" x-data
                      @keydown.enter.prevent="$wire.send()" @keydown.debounce.700ms="$wire.typing()"
                      class="w-full bg-transparent px-4 py-2.5 font-sans text-[13.5px] text-text placeholder:text-muted resize-none outline-none max-h-28"></textarea>
        </div>
        <button type="submit" wire:loading.attr="disabled" wire:target="send" class="size-10 shrink-0 rounded-full bg-primary text-surface flex items-center justify-center disabled:opacity-50"><i class="ri-send-plane-2-fill text-[18px]"></i></button>
    </form>
    @error('body') <p class="text-danger text-[11px] font-sans mt-1.5 px-1">{{ $message }}</p> @enderror
</div>
