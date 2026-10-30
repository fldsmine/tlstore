<div>
    @php $canWrite = $this->canWrite(); @endphp

    <div x-data="{ _a: null, play(src, text) {
            try {
                const speak = () => window.nextelSpeak && window.nextelSpeak(text);
                if (src) { if (this._a) { this._a.pause(); } this._a = new Audio(src); this._a.onerror = speak; this._a.play().catch(speak); }
                else { speak(); }
            } catch (e) {}
        } }"
         @play-audio.window="play($event.detail.src, $event.detail.text)"></div>

    <x-backoffice.page :title="$number->name"
                       :subtitle="trim(($number->brand ? $number->brand.' · ' : '').$number->line_number)"
                       :back="\Illuminate\Support\Facades\Route::has('backoffice.cloud.numbers.index') ? route('backoffice.cloud.numbers.index') : null">

        @if ($canWrite)
            <x-slot:action>
                <button type="button" wire:click="openEdit"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-edit-2-line text-[20px] sm:text-[16px]"></i><span class="hidden sm:inline">Edit details</span>
                </button>
            </x-slot:action>
        @endif

        <x-backoffice.card>
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                @if ($number->avatar_url)
                    <img src="{{ $number->avatar_url }}" alt="{{ $number->name }}" class="size-14 rounded-[14px] object-cover bg-background shrink-0">
                @else
                    <span class="size-14 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-cloud-line text-[24px]"></i></span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-heading text-text text-[16px] break-all">{{ $number->name }}</p>
                        @if ($number->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                    </div>
                    <p class="font-sans text-muted text-[13px] mt-1">
                        @if ($number->brand)<span class="text-text">{{ $number->brand }}</span> · @endif<span class="font-mono">{{ $number->line_number }}</span>
                    </p>
                </div>
                <div class="rounded-[14px] bg-background px-4 py-2.5 text-center shrink-0">
                    <p class="font-sans text-muted text-[12px]">Earning / call</p>
                    <p class="font-heading text-text text-[18px] mt-0.5 tabular-nums"><x-money :usd="$number->earning_per_call" :abbreviate="false" /></p>
                </div>
            </div>

            @if ($canWrite)
                <div class="flex items-center justify-between gap-3 mt-4 pt-4 border-t border-muted/10 flex-wrap">
                    <p class="font-sans text-muted text-[12px]">Send this as an incoming call to online Cloud users now.</p>
                    <button type="button" wire:click="sendBroadcast" wire:loading.attr="disabled" wire:target="sendBroadcast"
                            class="h-[40px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                        <span wire:loading.remove wire:target="sendBroadcast"><i class="ri-broadcast-line"></i> Send broadcast</span>
                        <span wire:loading wire:target="sendBroadcast" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Sending…</span>
                    </button>
                </div>
            @endif
        </x-backoffice.card>

        @if ($canWrite)
            @php $input = 'h-[44px] w-full rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary'; @endphp
            <x-backoffice.card title="Send sponsored SMS">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4">Drops a message into every Cloud subscriber's inbox. They earn the reward when they read + claim it.</p>
                <form wire:submit="sendSms" class="flex flex-col gap-3">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2 flex flex-col gap-1.5">
                            <label class="font-sans text-[12px] text-muted">Subject</label>
                            <input type="text" wire:model="smsSubject" placeholder="Your exclusive offer is ready" class="{{ $input }}">
                            @error('smsSubject')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-[12px] text-muted">Earn reward ($)</label>
                            <input type="number" step="0.000001" min="0" wire:model="smsEarnRate" placeholder="0.025" class="{{ $input }}">
                            @error('smsEarnRate')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-[12px] text-muted">Message</label>
                        <textarea wire:model="smsBody" rows="3" placeholder="Message body…" class="w-full rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        @error('smsBody')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="sendSms" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="sendSms"><i class="ri-mail-send-line"></i> Send SMS</span>
                            <span wire:loading wire:target="sendSms" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Sending…</span>
                        </button>
                    </div>
                </form>
            </x-backoffice.card>
        @endif

        @if ($canWrite)
            @php $binput = 'h-[44px] w-full rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary'; @endphp
            <x-backoffice.card title="Distribute a bonus">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4">Credit everyone who saved this contact ({{ $savedCount }} saver{{ $savedCount === 1 ? '' : 's' }}) the amount below — it becomes claimable on their Cloud page instantly. Promoters claim into their N-Cloud Calls wallet; shareholders claim into Nextel Shares (after activating).</p>
                <form wire:submit="distributeBonus" class="flex flex-col gap-3">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-[12px] text-muted">Type</label>
                            <select wire:model="bonusType" class="{{ $binput }}">
                                <option value="promoter">Promoter → N-Cloud Calls</option>
                                <option value="shareholder">Shareholder → Nextel Shares</option>
                            </select>
                            @error('bonusType')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-[12px] text-muted">Amount per saver ($)</label>
                            <input type="number" step="0.000001" min="0" wire:model="bonusAmount" placeholder="0.025" class="{{ $binput }}">
                            @error('bonusAmount')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-[12px] text-muted">Note (optional)</label>
                        <input type="text" wire:model="bonusNote" placeholder="e.g. Weekly promoter payout" class="{{ $binput }}">
                        @error('bonusNote')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex items-center justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="distributeBonus" @disabled($savedCount === 0)
                                class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="distributeBonus"><i class="ri-gift-line"></i> Distribute bonus</span>
                            <span wire:loading wire:target="distributeBonus" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Distributing…</span>
                        </button>
                    </div>
                </form>
            </x-backoffice.card>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-muted/10">
                <div class="flex items-center gap-2.5">
                    <span class="size-9 rounded-[10px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-mic-line text-[18px]"></i></span>
                    <div>
                        <p class="font-heading text-text text-[14px]">Voice messages</p>
                        <p class="font-sans text-muted text-[12px]">Add a few alternatives — only the one you set <span class="text-primary">active</span> is played (text-to-speech) on a call.</p>
                    </div>
                </div>
                <span class="font-sans text-muted text-[12px] tabular-nums">{{ $notes->count() }} message{{ $notes->count() === 1 ? '' : 's' }}</span>
            </div>

            @if ($canWrite)
                <form wire:submit="addNote" class="flex flex-col gap-3 px-5 py-4 border-b border-muted/10 bg-background/40">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Title</label>
                        <input type="text" wire:model="title" placeholder="e.g. Welcome message" class="h-[44px] rounded-[12px] bg-surface border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('title') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{
                            previewNote() { const t = ($refs.msg?.value || '').trim(); const v = $refs.voicesel?.value || ''; if (v) { $wire.previewVoice(); } else if (window.nextelSpeak) { window.nextelSpeak(t); } }
                        }">
                        <label class="font-sans text-muted text-[12px]">Sponsor message <span class="text-muted/70">(read aloud {{ $ttsEnabled ? 'in a realistic voice' : 'on the call' }})</span></label>
                        <textarea x-ref="msg" wire:model="message" rows="3" placeholder="The sponsored message the caller will hear…" class="rounded-[12px] bg-surface border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                        @error('message') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        <div class="flex items-center gap-2 flex-wrap">
                            <select x-ref="voicesel" wire:model="voice"
                                    class="h-[40px] rounded-[12px] bg-surface border border-muted/15 px-3 font-sans text-[12px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 max-w-[280px]">
                                @if ($ttsEnabled)
                                    @foreach (collect($voices)->groupBy('accent') as $accent => $group)
                                        <optgroup label="{{ $accent }}">
                                            @foreach ($group as $v)
                                                <option value="{{ $v['id'] }}">{{ $v['label'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                @else
                                    <option value="">Default</option>
                                @endif
                            </select>
                            <button type="button" x-on:click="previewNote()" wire:target="previewVoice" wire:loading.attr="disabled"
                                    class="font-sans text-[12px] text-primary inline-flex items-center gap-1.5 hover:underline disabled:opacity-50">
                                <span wire:loading.remove wire:target="previewVoice"><i class="ri-volume-up-line"></i> Preview</span>
                                <span wire:loading wire:target="previewVoice" class="inline-flex items-center gap-1.5"><i class="ri-loader-4-line animate-spin"></i> Generating…</span>
                            </button>
                            @if ($ttsEnabled)
                                <button type="button" wire:click="refreshVoices" wire:target="refreshVoices" wire:loading.attr="disabled"
                                        class="font-sans text-[12px] text-muted inline-flex items-center gap-1.5 hover:text-text disabled:opacity-50">
                                    <span wire:loading.remove wire:target="refreshVoices"><i class="ri-refresh-line"></i> Refresh</span>
                                    <span wire:loading wire:target="refreshVoices" class="inline-flex items-center gap-1.5"><i class="ri-loader-4-line animate-spin"></i> Refreshing…</span>
                                </button>
                            @endif
                        </div>
                        @error('voice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="addNote" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="addNote"><i class="ri-add-line"></i> Add message</span>
                            <span wire:loading wire:target="addNote" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                        </button>
                    </div>
                </form>
            @endif

            @if ($notes->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-mic-off-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No voice messages yet.</p>
                </div>
            @else
                <div class="flex flex-col">
                    @foreach ($notes as $note)
                        <div wire:key="note-{{ $note->id }}" class="flex flex-col lg:flex-row lg:items-center gap-3 px-5 py-4 border-b border-muted/5 last:border-0" x-data>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-sans text-text text-[13px] truncate">{{ $note->title }}</p>
                                    @php $voiceLabel = $note->voice_name ?: ($note->voice ? app(\App\Services\CloudVoiceService::class)->labelFor($note->voice) : null); @endphp
                                    @if ($voiceLabel)<span class="font-sans text-muted text-[11px] inline-flex items-center gap-1"><i class="ri-user-voice-line"></i> {{ $voiceLabel }}</span>@endif
                                    @if ($note->is_active)<x-backoffice.badge tone="success">Playing on calls</x-backoffice.badge>@endif
                                </div>
                                <p class="font-sans text-muted text-[12px] leading-relaxed mt-1.5">{{ $note->message }}</p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 self-end lg:self-auto">
                                <button type="button" x-on:click="$dispatch('play-audio', { src: @js($note->audioUrl()), text: @js($note->message) })"
                                        class="shrink-0 size-8 rounded-full bg-primary/10 text-primary hover:bg-primary/15 inline-flex items-center justify-center transition" aria-label="Play voice"><i class="ri-play-circle-line text-[16px]"></i></button>
                                @if ($canWrite)
                                    @if ($note->is_active)
                                        <button type="button" wire:click="toggleNote('{{ $note->id }}')"
                                                class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-primary/10 text-primary font-sans text-[12px] hover:bg-primary/15 transition"><i class="ri-checkbox-circle-fill"></i> Active</button>
                                    @else
                                        <button type="button" wire:click="toggleNote('{{ $note->id }}')"
                                                class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-background text-muted font-sans text-[12px] hover:text-text hover:bg-background/70 transition"><i class="ri-radio-button-line"></i> Set active</button>
                                    @endif
                                    <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteNote', params: ['{{ $note->id }}'], title: 'Delete voice message', message: 'Delete “{{ $note->title }}”?', confirmText: 'Delete', tone: 'danger' })"
                                            class="size-8 rounded-full bg-danger/10 text-danger hover:bg-danger/15 inline-flex items-center justify-center transition" aria-label="Delete message"><i class="ri-delete-bin-6-line text-[14px]"></i></button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-backoffice.card>

        <x-backoffice.modal :show="$showEditModal" title="Edit number" size="lg" closeAction="$set('showEditModal', false)">
            <form wire:submit="saveEdit" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Saved name</label>
                    <input type="text" wire:model="name" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Line number</label>
                        <input type="text" wire:model="lineNumber" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('lineNumber') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Brand <span class="text-muted/60">(optional)</span></label>
                        <input type="text" wire:model="brand" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('brand') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Caller name <span class="text-muted/60">(person on the call)</span></label>
                        <input type="text" wire:model="callerName" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('callerName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Earning per call ($)</label>
                        <input type="number" step="any" min="0" wire:model="earningPerCall" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('earningPerCall') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Pop-up message <span class="text-muted/60">(shown on the call screen)</span></label>
                    <textarea wire:model="popupMessage" rows="2" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                    @error('popupMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Claim link <span class="text-muted/60">(optional — the pop-up's button)</span></label>
                    <input type="url" wire:model="claimUrl" placeholder="https://sponsor.example/offer" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('claimUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Avatar <span class="text-muted/60">(optional)</span></label>
                    <div class="flex items-center gap-3">
                        @if ($avatar)
                            <img src="{{ $avatar->temporaryUrl() }}" alt="" class="size-12 rounded-full object-cover bg-background shrink-0">
                        @elseif ($existingAvatarUrl)
                            <img src="{{ $existingAvatarUrl }}" alt="" class="size-12 rounded-full object-cover bg-background shrink-0">
                        @else
                            <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-image-line text-[20px]"></i></span>
                        @endif
                        <label class="flex-1 cursor-pointer">
                            <input type="file" wire:model="avatar" accept="image/jpeg,image/png,image/webp" class="hidden">
                            <span class="inline-flex items-center gap-2 h-[40px] px-4 rounded-full bg-background text-text font-sans text-[13px]">
                                <i class="ri-upload-2-line"></i>
                                <span wire:loading.remove wire:target="avatar">Choose image</span>
                                <span wire:loading wire:target="avatar">Uploading…</span>
                            </span>
                        </label>
                    </div>
                    <p class="font-sans text-muted text-[11px]">JPG, PNG or WEBP — max 2 MB.</p>
                    @error('avatar') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>


                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Sort order</label>
                    <input type="number" min="0" wire:model="sortOrder" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('sortOrder') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <x-backoffice.toggle wire:model="isActive" label="Active" />

                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showEditModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveEdit" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveEdit">Save</span>
                        <span wire:loading wire:target="saveEdit" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
