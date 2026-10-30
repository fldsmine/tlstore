<div>
    <x-backoffice.page title="Announcements" subtitle="Messages shown to users in their dashboard">
        @if ($this->canWrite())
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="New announcement"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">New announcement</span>
                </button>
            </x-slot:action>
        @endif

        @php
            $canWrite = $this->canWrite();
            $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        <div class="flex flex-col gap-3">
            @forelse ($announcements as $a)
                <x-backoffice.card wire:key="ann-{{ $a->id }}">
                    <div class="flex items-start gap-3">
                        <span class="size-9 rounded-[10px] flex items-center justify-center shrink-0 {{ ['info'=>'bg-primary/10 text-primary','success'=>'bg-success/12 text-success','warning'=>'bg-[#d79937]/15 text-[#d79937]','danger'=>'bg-danger/12 text-danger'][$a->type->value] }}">
                            <i class="{{ $a->type->icon() }}"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-heading font-medium text-text text-[15px]">{{ $a->title }}</p>
                                @if ($a->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Hidden</x-backoffice.badge>@endif
                            </div>
                            <p class="font-sans text-muted text-[13px] mt-1">{{ $a->body }}</p>
                            <p class="font-sans text-muted text-[11px] mt-1.5">
                                {{ $a->starts_at ? 'From '.$a->starts_at->format('j M Y, g:i A') : 'Shows immediately' }}
                                {{ $a->ends_at ? ' · until '.$a->ends_at->format('j M Y, g:i A') : '' }}
                            </p>
                        </div>
                        @if ($canWrite)
                            <div class="relative shrink-0" x-data="{ open: false }">
                                <button type="button" x-on:click="open = !open" class="size-9 rounded-full bg-background text-muted hover:text-text flex items-center justify-center transition" aria-label="Actions">
                                    <i class="ri-more-2-fill text-[18px]"></i>
                                </button>
                                <div x-show="open" x-on:click.outside="open = false" x-transition.origin.top.right style="display:none"
                                     class="absolute right-0 top-[42px] z-20 w-44 rounded-[14px] bg-surface border border-muted/10 shadow-[0_12px_40px_rgba(0,0,0,0.16)] p-1.5 flex flex-col">
                                    <button type="button" x-on:click="open = false" wire:click="edit('{{ $a->id }}')" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="ri-edit-2-line text-muted text-[15px]"></i> Edit</button>
                                    <button type="button" x-on:click="open = false" wire:click="toggleActive('{{ $a->id }}')" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="{{ $a->is_active ? 'ri-eye-off-line' : 'ri-eye-line' }} text-muted text-[15px]"></i> {{ $a->is_active ? 'Hide' : 'Show' }}</button>
                                    <button type="button" x-on:click="open = false; $dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $a->id }}'], title: 'Delete announcement', message: 'This announcement will be permanently removed.', confirmText: 'Delete', tone: 'danger' })" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-danger/10 text-left font-sans text-danger text-[13px]"><i class="ri-delete-bin-6-line text-[15px]"></i> Delete</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-backoffice.card>
            @empty
                <x-backoffice.card><p class="font-sans text-muted text-[14px] text-center py-6">No announcements yet.</p></x-backoffice.card>
            @endforelse
        </div>

        @if ($announcements->hasPages())<div>{{ $announcements->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit announcement' : 'New announcement'">
            <form wire:submit="save"
                  x-data="{
                      p: { title: @js($title), body: @js($body), type: @js($type), actionUrl: @js($actionUrl), actionLabel: @js($actionLabel), dismissible: @js($isDismissible) },
                      tones: { info: 'bg-primary/8 text-primary', success: 'bg-success/10 text-success', warning: 'bg-[#d79937]/12 text-[#d79937]', danger: 'bg-danger/10 text-danger' },
                      icons: { info: 'ri-information-fill', success: 'ri-checkbox-circle-fill', warning: 'ri-alert-fill', danger: 'ri-error-warning-fill' },
                  }"
                  class="flex flex-col gap-3.5">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Title</label>
                    <input type="text" wire:model="title" x-on:input="p.title = $event.target.value" class="{{ $input }}">
                    @error('title')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Message</label>
                    <textarea wire:model="body" x-on:input="p.body = $event.target.value" rows="3" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                    @error('body')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Type</label>
                    <select wire:model="type" x-on:change="p.type = $event.target.value" class="{{ $input }}">@foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->getName() }}</option>@endforeach</select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Button link <span class="text-muted/50">(optional)</span></label>
                        <input type="url" wire:model="actionUrl" x-on:input="p.actionUrl = $event.target.value" placeholder="https://…" class="{{ $input }}">
                        @error('actionUrl')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Button text</label>
                        <input type="text" wire:model="actionLabel" x-on:input="p.actionLabel = $event.target.value" placeholder="Learn more" maxlength="60" class="{{ $input }}">
                        @error('actionLabel')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Start (optional)</label><input type="datetime-local" wire:model="startsAt" class="{{ $input }}"></div>
                    <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">End (optional)</label><input type="datetime-local" wire:model="endsAt" class="{{ $input }}">@error('endsAt')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-backoffice.toggle wire:model="isActive" label="Active" description="Show this announcement now" />
                    <x-backoffice.toggle wire:model="isDismissible" label="Dismissible" description="Let users close it" />
                </div>

                {{-- Must mirror the dashboard banner users actually see. --}}
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Live preview <span class="text-muted/50">— how users will see it</span></label>
                    <div class="rounded-[18px] border border-dashed border-muted/25 bg-background/40 p-3">
                        <div class="flex items-start gap-3 rounded-[16px] px-4 py-3.5" :class="tones[p.type] || tones.info">
                            <i class="text-[18px] shrink-0 mt-0.5" :class="icons[p.type] || icons.info"></i>
                            <div class="flex-1 min-w-0">
                                <p class="font-heading font-medium text-text text-[14px]" x-text="p.title || 'Your announcement title'"></p>
                                <p class="font-sans text-muted text-[13px] mt-0.5" x-text="p.body || 'Your announcement message will appear here.'"></p>
                                <span x-show="p.actionUrl && p.actionLabel" class="mt-2 inline-flex items-center gap-1 rounded-full bg-primary px-3 py-1.5 font-sans text-white text-[12px]">
                                    <span x-text="p.actionLabel || 'Learn more'"></span><i class="ri-arrow-right-line text-[13px]"></i>
                                </span>
                            </div>
                            <span x-show="$wire.isDismissible" class="shrink-0 size-7 rounded-full flex items-center justify-center text-muted"><i class="ri-close-line text-[16px]"></i></span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
