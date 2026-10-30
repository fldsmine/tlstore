{{-- Generic toast:  $this->dispatch('toast', message: '...', type: 'success')  // success|error|warning|info
     Rich chat toast: $this->dispatch('chat-toast', name:, username:, avatar:, initials:, preview:, url:, duration:)
     Before a redirect: session()->flash('toast', ['type' => '...', 'message' => '...']). --}}
<div x-data="{
        toasts: [],
        push(t) {
            if (!t || (!t.message && !t.preview)) return;
            // Chat toasts: collapse any existing toast for the same chat (no stacking) + sound/vibrate.
            if (t.variant === 'chat') {
                if (t.key) this.toasts = this.toasts.filter((x) => x.key !== t.key);
                if (window.nextelChatPing) window.nextelChatPing();
            }
            const id = (window.__toastId = (window.__toastId || 0) + 1);
            this.toasts.push({
                id,
                key: t.key || null,
                variant: t.variant || 'basic',
                type: t.type || 'info',
                message: t.message || '',
                url: t.url || null,
                name: t.name || '',
                username: t.username || '',
                avatar: t.avatar || null,
                initials: t.initials || '',
                preview: t.preview || '',
            });
            setTimeout(() => this.dismiss(id), t.duration || 4500);
        },
        dismiss(id) { this.toasts = this.toasts.filter((x) => x.id !== id); },
        open(t) { if (t.url) window.Livewire.navigate(t.url); this.dismiss(t.id); },
        styles: {
            success: { ring: 'ring-success/30', icon: 'ri-checkbox-circle-fill text-success' },
            error:   { ring: 'ring-danger/30',  icon: 'ri-error-warning-fill text-danger' },
            warning: { ring: 'ring-[#FF9500]/30', icon: 'ri-alert-fill text-[#FF9500]' },
            info:    { ring: 'ring-primary/20',  icon: 'ri-information-fill text-primary' },
            chat:    { ring: 'ring-primary/25',  icon: 'ri-chat-smile-2-fill text-primary' },
        },
     }"
     @toast.window="push($event.detail)"
     @chat-toast.window="push({ ...$event.detail, variant: 'chat', type: 'chat' })"
     x-init="@if (session('toast')) push(@js(session('toast'))) @endif"
     aria-live="polite" aria-atomic="false"
     class="fixed top-3 inset-x-0 z-[100] flex flex-col items-center gap-2 px-3 pointer-events-none [padding-top:env(safe-area-inset-top)] sm:top-4 sm:inset-x-auto sm:right-5 sm:items-end sm:px-0 sm:pt-0">
    <template x-for="t in toasts" :key="t.id">
        <div x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2 sm:translate-y-0 sm:translate-x-4"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 translate-x-4"
             class="pointer-events-auto w-full sm:w-[360px] max-w-[94vw] flex gap-2.5 rounded-[16px] bg-surface px-4 py-3 shadow-[0_8px_30px_rgba(0,0,0,0.12)] ring-1"
             {{-- Chat toasts are multi-line (align to top); basic toasts are a single line (center the icon with the text). --}}
             :class="[styles[t.type].ring, t.variant === 'chat' ? 'items-start' : 'items-center']">

            {{-- leading: avatar (chat) or status icon (basic) --}}
            <template x-if="t.variant === 'chat'">
                <span class="size-9 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center shrink-0 mt-0.5">
                    <template x-if="t.avatar"><img :src="t.avatar" class="size-full object-cover" alt=""></template>
                    <template x-if="!t.avatar"><span class="font-heading font-semibold text-primary text-[12px]" x-text="t.initials"></span></template>
                </span>
            </template>
            <template x-if="t.variant !== 'chat'">
                <i class="text-[20px] shrink-0 leading-none" :class="styles[t.type].icon"></i>
            </template>

            <div class="flex-1 min-w-0">
                {{-- chat: name + @username + preview + actions --}}
                <template x-if="t.variant === 'chat'">
                    <div>
                        <div class="flex items-baseline gap-1.5">
                            <span class="font-heading font-semibold text-text text-[13px] truncate" x-text="t.name"></span>
                            <span class="font-sans text-muted text-[11px] truncate" x-show="t.username" x-text="'@' + t.username"></span>
                        </div>
                        <p class="font-sans text-text/80 text-[12.5px] leading-snug line-clamp-2" x-text="t.preview"></p>
                        <div class="flex items-center gap-2 mt-2.5">
                            <button type="button" @click="open(t)" class="h-8 px-4 rounded-full bg-primary text-surface font-sans font-semibold text-[12px] inline-flex items-center gap-1.5"><i class="ri-reply-line text-[14px]"></i> Open chat</button>
                            <button type="button" @click="dismiss(t.id)" class="h-8 px-4 rounded-full bg-background text-muted font-sans font-semibold text-[12px]">Dismiss</button>
                        </div>
                    </div>
                </template>
                {{-- basic: plain (optionally clickable) message --}}
                <template x-if="t.variant !== 'chat'">
                    <p class="font-sans text-text text-[13px] leading-snug" :class="t.url ? 'cursor-pointer hover:underline' : ''" @click="if (t.url) open(t)" x-text="t.message"></p>
                </template>
            </div>

            <button type="button" @click="dismiss(t.id)" class="shrink-0 size-6 rounded-full hover:bg-background flex items-center justify-center text-muted" aria-label="Dismiss">
                <i class="ri-close-line text-[16px]"></i>
            </button>
        </div>
    </template>
</div>
