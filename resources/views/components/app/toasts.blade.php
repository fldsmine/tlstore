{{-- Generic backoffice toast. Dispatch from any component with:
       $this->dispatch('toast', message: '...', type: 'success')
     Types: success | error | warning | info.
     Before a redirect, flash: session()->flash('toast', ['type' => '...', 'message' => '...']). --}}
<div x-data="{
        toasts: [],
        push(t) {
            if (!t || !t.message) return;
            const id = (window.__boToastId = (window.__boToastId || 0) + 1);
            this.toasts.push({ id, type: t.type || 'info', message: t.message });
            setTimeout(() => this.dismiss(id), t.duration || 4500);
        },
        dismiss(id) { this.toasts = this.toasts.filter((x) => x.id !== id); },
        styles: {
            success: { ring: 'ring-success/30', icon: 'ri-checkbox-circle-fill text-success' },
            error:   { ring: 'ring-danger/30',  icon: 'ri-error-warning-fill text-danger' },
            warning: { ring: 'ring-[#FF9500]/30', icon: 'ri-alert-fill text-[#FF9500]' },
            info:    { ring: 'ring-primary/20',  icon: 'ri-information-fill text-primary' },
        },
     }"
     @toast.window="push($event.detail)"
     x-init="@if (session('toast')) push(@js(session('toast'))) @endif"
     aria-live="polite" aria-atomic="false"
     class="fixed top-3 inset-x-0 z-[100] flex flex-col items-center gap-2 px-3 pointer-events-none sm:top-4 sm:inset-x-auto sm:right-5 sm:items-end sm:px-0">
    <template x-for="t in toasts" :key="t.id">
        <div x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 translate-x-4"
             class="pointer-events-auto w-full sm:w-[360px] max-w-[94vw] flex items-center gap-2.5 rounded-[16px] bg-surface px-4 py-3 shadow-[0_8px_30px_rgba(0,0,0,0.12)] ring-1"
             :class="styles[t.type].ring">
            <i class="text-[20px] shrink-0 leading-none" :class="styles[t.type].icon"></i>
            <p class="flex-1 min-w-0 font-sans text-[13px] text-text" x-text="t.message"></p>
            <button type="button" @click="dismiss(t.id)" class="shrink-0 text-muted hover:text-text" aria-label="Dismiss">
                <i class="ri-close-line text-[16px]"></i>
            </button>
        </div>
    </template>
</div>
