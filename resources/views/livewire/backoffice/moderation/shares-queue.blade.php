<div>
    <x-backoffice.page title="Share queue" subtitle="Validate ad-share evidence before paying rewards">
        <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[420px]">
            <i class="ri-search-line text-muted text-[17px]"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user, ad or reference…"
                   class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
            @endif
        </div>

        @forelse ($events as $event)
            <x-backoffice.card wire:key="share-{{ $event->id }}">
                <div class="flex flex-col md:flex-row md:items-start gap-4">
                    <div class="flex-1 min-w-0 flex flex-col gap-3">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-share-line text-[20px]"></i></span>
                            <div class="min-w-0">
                                <p class="font-sans text-text text-[14px] font-medium truncate">{{ $event->task?->ad?->title ?? 'Unknown ad' }}</p>
                                <p class="font-sans text-muted text-[12px] truncate">by {{ $event->task?->user?->full_name }} ({{ '@'.$event->task?->user?->username }}) · {{ optional($event->shared_at)->format('j M Y, H:i') }}</p>
                            </div>
                            <x-backoffice.badge tone="info">{{ \Illuminate\Support\Str::headline($event->channel) }}</x-backoffice.badge>
                            @foreach ($event->riskSignals as $signal)
                                <x-backoffice.badge tone="danger">{{ $signal->severity }} · {{ $signal->signal_key }}</x-backoffice.badge>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[12px] font-sans">
                            <span class="text-muted">Method: <span class="text-text">{{ $event->verification_method ?: '—' }}</span></span>
                            <span class="text-muted">Ref: <code class="text-text">{{ $event->external_reference ?: '—' }}</code></span>
                            @if (is_array($event->evidence) && ! empty($event->evidence['screenshot_path']))
                                <a href="{{ route('backoffice.moderation.shares.proof', $event) }}" target="_blank" rel="noopener" class="text-primary underline inline-flex items-center gap-1"><i class="ri-image-line"></i> View screenshot proof</a>
                            @endif
                        </div>
                    </div>

                    <div class="md:w-[280px] shrink-0 flex flex-col gap-2">
                        <label class="label" for="share-reason-{{ $event->id }}">Reason <span class="text-muted">(required to reject)</span></label>
                        <textarea id="share-reason-{{ $event->id }}" wire:model="reasons.{{ $event->id }}" rows="2" class="input" placeholder="Why is this share being rejected?"></textarea>
                        @error('reasons.'.$event->id)<span class="error">{{ $message }}</span>@enderror
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="approve({{ $event->id }})" class="btn btn-primary btn-md flex-1">
                                <i class="ri-check-line"></i> Approve
                            </button>
                            <button type="button" wire:click="reject({{ $event->id }})" class="btn btn-danger btn-md flex-1">
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
                    <p class="font-sans text-muted text-[14px]">No pending share validations.</p>
                </div>
            </x-backoffice.card>
        @endforelse

        @if ($events->hasPages())
            <div>{{ $events->links('vendor.pagination.backoffice') }}</div>
        @endif
    </x-backoffice.page>
</div>
