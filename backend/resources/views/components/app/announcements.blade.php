{{-- Admin-managed banners (App\Models\Announcement); dismissal persists via localStorage. --}}
@php
    $announcements = \App\Models\Announcement::query()->live()->latest()->get();
    $tones = [
        'info' => 'bg-primary/8 text-primary',
        'success' => 'bg-success/10 text-success',
        'warning' => 'bg-[#d79937]/12 text-[#d79937]',
        'danger' => 'bg-danger/10 text-danger',
    ];
@endphp

@if ($announcements->isNotEmpty())
    <div x-data="{
            dismissed: JSON.parse(localStorage.getItem('nx_announcements') || '[]'),
            hide(id) { this.dismissed.push(id); localStorage.setItem('nx_announcements', JSON.stringify(this.dismissed)); }
         }"
         class="flex flex-col gap-2.5">
        @foreach ($announcements as $a)
            <div @if ($a->is_dismissible) x-show="!dismissed.includes('{{ $a->id }}')" x-cloak @endif
                 class="flex items-start gap-3 rounded-[16px] px-4 py-3.5 {{ $tones[$a->type->value] ?? $tones['info'] }}">
                <i class="{{ $a->type->icon() }} text-[18px] shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[14px]">{{ $a->title }}</p>
                    <p class="font-sans text-muted text-[13px] mt-0.5">{{ $a->body }}</p>
                    @if ($a->action_url && $a->action_label)
                        <a href="{{ $a->action_url }}" target="_blank" rel="noopener noreferrer"
                           class="mt-2 inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1.5 font-sans text-white text-[12px] hover:opacity-90 transition">
                            {{ $a->action_label }}<i class="ri-arrow-right-line text-[13px]"></i>
                        </a>
                    @endif
                </div>
                @if ($a->is_dismissible)
                    <button type="button" @click="hide('{{ $a->id }}')" class="shrink-0 size-7 rounded-full hover:bg-black/5 flex items-center justify-center text-muted" aria-label="Dismiss">
                        <i class="ri-close-line text-[16px]"></i>
                    </button>
                @endif
            </div>
        @endforeach
    </div>
@endif
