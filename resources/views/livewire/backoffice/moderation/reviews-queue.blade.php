<div>
    <x-backoffice.page title="Review queue" subtitle="Decide pending app-review submissions">
        <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[420px]">
            <i class="ri-search-line text-muted text-[17px]"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search reviewer or app…"
                   class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
            @endif
        </div>

        @forelse ($submissions as $submission)
            <x-backoffice.card wire:key="review-{{ $submission->id }}" class="flex flex-col gap-4">
                <div class="flex flex-col md:flex-row md:items-start gap-4">
                    <div class="flex-1 min-w-0 flex flex-col gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-star-smile-line text-[20px]"></i></span>
                            <div class="min-w-0">
                                <p class="font-sans text-text text-[14px] font-medium truncate">{{ $submission->task?->campaign?->app?->name ?? 'Unknown app' }}</p>
                                <p class="font-sans text-muted text-[12px] truncate">by {{ $submission->reviewer?->full_name }} ({{ '@'.$submission->reviewer?->username }}) · {{ optional($submission->submitted_at)->format('j M Y, H:i') }}</p>
                            </div>
                            @if ($submission->duration_seconds)
                                <x-backoffice.badge tone="info">{{ intdiv((int) $submission->duration_seconds, 60) }}m {{ $submission->duration_seconds % 60 }}s</x-backoffice.badge>
                            @endif
                            @foreach ($submission->riskSignals as $signal)
                                <x-backoffice.badge tone="danger" title="{{ $signal->signal_type }}">{{ $signal->severity }} · {{ $signal->signal_key }}</x-backoffice.badge>
                            @endforeach
                        </div>

                        @if ($submission->answers->isNotEmpty())
                            <div class="rounded-[14px] bg-background p-3 flex flex-col gap-2 max-h-56 overflow-y-auto">
                                @foreach ($submission->answers as $answer)
                                    @php
                                        $value = $answer->answer_value;
                                        if (is_array($value) && array_key_exists('text', $value) && is_scalar($value['text'])) {
                                            $rendered = (string) $value['text'];
                                        } elseif (is_scalar($value)) {
                                            $rendered = (string) $value;
                                        } else {
                                            $rendered = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                        }
                                    @endphp
                                    <div class="grid grid-cols-[1fr_auto] gap-3 items-start border-b border-muted/10 last:border-0 pb-2 last:pb-0">
                                        <p class="font-sans text-[12px] text-muted leading-snug">{{ $answer->question?->prompt ?? $answer->question?->question_key ?? 'Question' }}</p>
                                        <code class="font-mono text-[11px] text-text text-right max-w-[45%] break-words">{{ $rendered }}</code>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="md:w-[280px] shrink-0 flex flex-col gap-2">
                        <label class="label" for="reason-{{ $submission->id }}">Reason <span class="text-muted">(required to reject)</span></label>
                        <textarea id="reason-{{ $submission->id }}" wire:model="reasons.{{ $submission->id }}" rows="2" class="input" placeholder="Why is this being rejected?"></textarea>
                        @error('reasons.'.$submission->id)<span class="error">{{ $message }}</span>@enderror
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="approve({{ $submission->id }})" class="btn btn-primary btn-md flex-1">
                                <i class="ri-check-line"></i> Approve
                            </button>
                            <button type="button" wire:click="reject({{ $submission->id }})" class="btn btn-danger btn-md flex-1">
                                <i class="ri-close-line"></i> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </x-backoffice.card>
        @empty
            <x-backoffice.card>
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-success"><i class="ri-checkbox-circle-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">The review queue is clear.</p>
                </div>
            </x-backoffice.card>
        @endforelse

        @if ($submissions->hasPages())
            <div>{{ $submissions->links('vendor.pagination.backoffice') }}</div>
        @endif
    </x-backoffice.page>
</div>
