<div>
    <x-backoffice.page title="Popups" subtitle="Promotional dialogs shown over the user dashboard">
        @if ($this->canWrite())
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="New popup"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">New popup</span>
                </button>
            </x-slot:action>
        @endif

        @php
            $canWrite = $this->canWrite();
            $panel = \App\Models\Popup::PANEL_STYLE;
            $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        <div class="flex flex-col gap-3">
            @forelse ($popups as $popup)
                <x-backoffice.card wire:key="popup-{{ $popup->id }}">
                    <div class="flex items-start gap-3">
                        <span class="size-14 rounded-[14px] overflow-hidden shrink-0 flex items-center justify-center bg-background" style="{{ $panel }}">
                            @if ($popup->header_image_url)
                                <img src="{{ $popup->header_image_url }}" alt="" class="size-full object-cover">
                            @endif
                        </span>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-heading font-medium text-text text-[15px]">{{ $popup->heading ?: ($popup->title ?: 'Popup') }}</p>
                                @if ($popup->is_active)
                                    <x-backoffice.badge tone="success">Active</x-backoffice.badge>
                                @else
                                    <x-backoffice.badge tone="muted">Disabled</x-backoffice.badge>
                                @endif
                                @if ($popup->is_recursive)
                                    <x-backoffice.badge tone="info">Every visit</x-backoffice.badge>
                                @else
                                    <x-backoffice.badge tone="warning">Once only</x-backoffice.badge>
                                @endif
                            </div>

                            @if ($popup->title)
                                <p class="font-sans text-muted text-[12px] mt-1">{{ $popup->title }}</p>
                            @endif

                            <p class="font-sans text-muted text-[13px] mt-1 line-clamp-2">{{ $popup->body }}</p>

                            <p class="font-sans text-muted text-[11px] mt-1.5">
                                Seen by {{ number_format($popup->views_count) }} {{ \Illuminate\Support\Str::plural('user', $popup->views_count) }}
                                · closed {{ number_format($popup->dismissals_count) }} {{ \Illuminate\Support\Str::plural('time', $popup->dismissals_count) }}
                            </p>
                        </div>

                        @if ($canWrite)
                            <div class="relative shrink-0" x-data="{ open: false }">
                                <button type="button" x-on:click="open = !open" class="size-9 rounded-full bg-background text-muted hover:text-text flex items-center justify-center transition" aria-label="Actions">
                                    <i class="ri-more-2-fill text-[18px]"></i>
                                </button>
                                <div x-show="open" x-on:click.outside="open = false" x-transition.origin.top.right style="display:none"
                                     class="absolute right-0 top-[42px] z-20 w-52 rounded-[14px] bg-surface border border-muted/10 shadow-[0_12px_40px_rgba(0,0,0,0.16)] p-1.5 flex flex-col">
                                    <button type="button" x-on:click="open = false" wire:click="preview('{{ $popup->id }}')" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="ri-eye-line text-muted text-[15px]"></i> Preview</button>
                                    <button type="button" x-on:click="open = false" wire:click="edit('{{ $popup->id }}')" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="ri-edit-2-line text-muted text-[15px]"></i> Edit</button>
                                    <button type="button" x-on:click="open = false" wire:click="toggleActive('{{ $popup->id }}')" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="{{ $popup->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }} text-muted text-[15px]"></i> {{ $popup->is_active ? 'Disable' : 'Enable' }}</button>
                                    <button type="button" x-on:click="open = false" wire:click="toggleRecursive('{{ $popup->id }}')" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="ri-repeat-2-line text-muted text-[15px]"></i> {{ $popup->is_recursive ? 'Make one-off' : 'Make recursive' }}</button>
                                    <button type="button" x-on:click="open = false; $dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $popup->id }}'], title: 'Delete popup', message: 'This popup will be permanently removed.', confirmText: 'Delete', tone: 'danger' })" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-danger/10 text-left font-sans text-danger text-[13px]"><i class="ri-delete-bin-6-line text-[15px]"></i> Delete</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-backoffice.card>
            @empty
                <x-backoffice.card>
                    <div class="py-6 flex flex-col items-center gap-2 text-center">
                        <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-window-line text-[22px]"></i></span>
                        <p class="font-sans text-muted text-[14px]">No popups yet.</p>
                        <p class="font-sans text-muted text-[13px] max-w-[420px]">A popup opens over the dashboard the next time a user lands on it. Only the message is required — a headline, media, buttons and small print are optional.</p>
                    </div>
                </x-backoffice.card>
            @endforelse
        </div>

        @if ($popups->hasPages())<div>{{ $popups->links('vendor.pagination.nextel') }}</div>@endif

        {{-- ── Create / edit ─────────────────────────────────────────────────────────── --}}
        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit popup' : 'New popup'" size="lg">
            @php
                $headerPreview = $headerImage ? $headerImage->temporaryUrl() : ($removeHeaderImage ? null : $existingHeaderUrl);
                $backgroundPreview = $backgroundImage ? $backgroundImage->temporaryUrl() : ($removeBackgroundImage ? null : $existingBackgroundUrl);
            @endphp

            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Title <span class="text-muted/50">(pill above the headline)</span></label>
                        <input type="text" wire:model.live.debounce.600ms="title" maxlength="80" placeholder="Your Nextel experience, upgraded" class="{{ $input }}">
                        @error('title')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Heading</label>
                        <input type="text" wire:model.live.debounce.600ms="heading" maxlength="160" placeholder="Take Nextel with you." class="{{ $input }}">
                        @error('heading')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Content <span class="text-danger">(required)</span></label>
                    <textarea wire:model.live.debounce.600ms="body" rows="3" maxlength="2000" placeholder="Stay connected, discover more ways to earn, and enjoy Nextel wherever you go." class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                    @error('body')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Image caption <span class="text-muted/50">(optional — sits on the header image, so it needs one)</span></label>
                        <input type="text" wire:model.live.debounce.600ms="imageCaption" maxlength="80" placeholder="More ways to connect" class="{{ $input }}">
                        @error('imageCaption')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Footer text <span class="text-muted/50">(optional)</span></label>
                        <input type="text" wire:model.live.debounce.600ms="footerText" maxlength="160" placeholder="Using iPhone? See iOS availability" class="{{ $input }}">
                        @error('footerText')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Header image <span class="text-muted/50">(optional — the dialog shows no media panel without one)</span></label>
                        <div class="flex items-center gap-3 rounded-[14px] bg-background px-3 py-2.5">
                            <span class="size-11 rounded-[10px] overflow-hidden shrink-0 flex items-center justify-center bg-surface border border-muted/15">
                                @if ($headerPreview)
                                    <img src="{{ $headerPreview }}" alt="" class="size-full object-cover">
                                @else
                                    <i class="ri-image-line text-muted text-[16px]"></i>
                                @endif
                            </span>
                            <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                {{-- A <label> wrapping the hidden input is what makes the pill open the file
                                     dialog — a <span> swallows the click and nothing happens. --}}
                                <label class="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 h-[32px] font-sans text-[12px] text-text shadow-sm cursor-pointer hover:bg-surface/70 transition">
                                    <i class="ri-upload-2-line"></i>
                                    <span>{{ $headerPreview ? 'Replace image' : 'Choose image' }}</span>
                                    <input type="file" wire:model="headerImage" accept="image/*" class="hidden">
                                </label>

                                @if ($headerPreview)
                                    <button type="button" wire:click="clearHeaderImage" class="font-sans text-[12px] text-danger hover:underline">Remove</button>
                                @elseif ($removeHeaderImage && $existingHeaderUrl)
                                    <button type="button" wire:click="keepHeaderImage" class="font-sans text-[12px] text-primary hover:underline">Undo</button>
                                @endif

                                <span wire:loading wire:target="headerImage" class="font-sans text-[11px] text-muted">Uploading…</span>
                            </div>
                        </div>
                        @error('headerImage')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Background image <span class="text-muted/50">(optional — sits behind the text)</span></label>
                        <div class="flex items-center gap-3 rounded-[14px] bg-background px-3 py-2.5">
                            <span class="size-11 rounded-[10px] overflow-hidden shrink-0 flex items-center justify-center bg-surface border border-muted/15">
                                @if ($backgroundPreview)
                                    <img src="{{ $backgroundPreview }}" alt="" class="size-full object-cover">
                                @else
                                    <i class="ri-image-line text-muted text-[16px]"></i>
                                @endif
                            </span>
                            <div class="flex-1 min-w-0 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                <label class="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 h-[32px] font-sans text-[12px] text-text shadow-sm cursor-pointer hover:bg-surface/70 transition">
                                    <i class="ri-upload-2-line"></i>
                                    <span>{{ $backgroundPreview ? 'Replace image' : 'Choose image' }}</span>
                                    <input type="file" wire:model="backgroundImage" accept="image/*" class="hidden">
                                </label>

                                @if ($backgroundPreview)
                                    <button type="button" wire:click="clearBackgroundImage" class="font-sans text-[12px] text-danger hover:underline">Remove</button>
                                @elseif ($removeBackgroundImage && $existingBackgroundUrl)
                                    <button type="button" wire:click="keepBackgroundImage" class="font-sans text-[12px] text-primary hover:underline">Undo</button>
                                @endif

                                <span wire:loading wire:target="backgroundImage" class="font-sans text-[11px] text-muted">Uploading…</span>
                            </div>
                        </div>
                        @error('backgroundImage')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="flex flex-col gap-3 rounded-[16px] bg-background/60 p-3.5">
                    <p class="font-sans text-text text-[13px] font-medium">Bottom action links <span class="text-muted font-normal">(optional — a link needs both a label and a URL)</span></p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Main button label</label>
                            <input type="text" wire:model.live.debounce.600ms="primaryLabel" maxlength="60" placeholder="Download the app" class="{{ $input }}">
                            @error('primaryLabel')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Main button URL</label>
                            <input type="text" wire:model.live.debounce.600ms="primaryUrl" maxlength="255" placeholder="https://nextel.example/app" class="{{ $input }}">
                            @error('primaryUrl')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Second link label</label>
                            <input type="text" wire:model.live.debounce.600ms="secondaryLabel" maxlength="60" placeholder="Explore the Nextel app" class="{{ $input }}">
                            @error('secondaryLabel')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Second link URL</label>
                            <input type="text" wire:model.live.debounce.600ms="secondaryUrl" maxlength="255" placeholder="/dashboard/my-website" class="{{ $input }}">
                            @error('secondaryUrl')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-backoffice.toggle wire:model="isActive" label="Enabled" description="Open over the dashboard now" />
                    <x-backoffice.toggle wire:model="isRecursive" label="Recursive" description="Show again on every visit — off means once per user" />
                </div>

                {{-- Live preview: the same x-app.popup-card the dashboard renders. --}}
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Live preview <span class="text-muted/50">— exactly what users will see</span></label>
                    <div class="rounded-[20px] border border-dashed border-muted/25 bg-background/40 p-3">
                        <x-app.popup-card
                            :title="$title ?: null"
                            :heading="$heading ?: null"
                            :body="$body ?: 'Your message appears here.'"
                            :header-url="$headerPreview"
                            :background-url="$backgroundPreview"
                            :caption="$imageCaption ?: null"
                            :primary-label="$primaryLabel ?: null"
                            :primary-url="$primaryUrl ?: null"
                            :secondary-label="$secondaryLabel ?: null"
                            :secondary-url="$secondaryUrl ?: null"
                            :footer="$footerText ?: null" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save,headerImage,backgroundImage" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save popup</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>

        {{-- ── Preview exactly as users see it ───────────────────────────────────────── --}}
        @if ($preview)
            <x-backoffice.modal :show="true" title="Preview" size="xl" close-action="closePreview">
                <div class="relative">
                    <x-app.popup-card
                        :title="$preview->title"
                        :heading="$preview->heading"
                        :body="$preview->body"
                        :header-url="$preview->header_image_url"
                        :background-url="$preview->background_image_url"
                        :caption="$preview->image_caption"
                        :primary-label="$preview->primary_label"
                        :primary-url="$preview->primary_url"
                        :secondary-label="$preview->secondary_label"
                        :secondary-url="$preview->secondary_url"
                        :footer="$preview->footer_text" />

                    <span aria-hidden="true" class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full border border-black/10 bg-white/90 text-primary shadow-sm">
                        <i class="ri-close-line text-[20px]"></i>
                    </span>
                </div>
            </x-backoffice.modal>
        @endif
    </x-backoffice.page>
</div>