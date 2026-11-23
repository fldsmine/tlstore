<div>
    @if ($showModal)
        @php($progress = $requiredCount > 0 ? (int) round(($completedCount / $requiredCount) * 100) : 100)

        <div x-data
             x-init="if (new URLSearchParams(window.location.search).has('showSocialFollows')) { const url = new URL(window.location.href); url.searchParams.delete('showSocialFollows'); window.history.replaceState(window.history.state, '', url); }"
             x-on:keydown.escape.window.prevent="$wire.close()"
             wire:click.self="close"
             class="fixed inset-0 z-[120] flex items-start justify-center overflow-auto overscroll-contain bg-black/60 p-3 backdrop-blur-sm sm:p-6">
            <section x-trap.inert.noscroll="true"
                     role="dialog"
                     aria-modal="true"
                     aria-labelledby="required-social-dialog-title"
                     tabindex="-1"
                     class="my-auto max-h-[calc(100dvh-1.5rem)] w-full max-w-[760px] overflow-auto overscroll-contain rounded-[24px] bg-background shadow-2xl sm:max-h-[calc(100dvh-3rem)]">
				<header class="mb-8 text-center">
					<div class="mx-auto flex w-fit items-center gap-2.5 text-primary">
						<span class="flex size-10 items-center justify-center rounded-[13px] bg-primary text-white shadow-sm">
							<i class="ri-shield-check-line text-[22px]" aria-hidden="true"></i>
						</span>
						<span class="font-heading text-[16px] font-bold tracking-[0.16em]">NEXTEL</span>
					</div>
					<p class="mt-6 font-sans text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">Interface preview</p>
					<h1 class="mt-2 font-heading text-[25px] font-semibold text-[#171717] sm:text-[30px]">Human verification</h1>
				</header>

                <div class="p-4 sm:p-6">
                    @if ($requiredCount > 0)
                        <div class="mb-5 rounded-[16px] bg-surface px-4 py-3">
                            <div class="mb-2 flex items-center justify-between gap-3 font-sans text-[12px]">
                                <span class="text-muted">{{ $completedCount }} of {{ $requiredCount }} required pages confirmed</span>
                                <span class="font-semibold text-primary">{{ $progress }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted/10" role="progressbar" aria-label="Social page follow progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                                <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if ($pendingHandles->isNotEmpty())
                        <section aria-labelledby="required-social-pages">
                            <div class="mb-3 flex items-end justify-between gap-3 px-1">
                                <div>
                                    <h2 id="required-social-pages" class="font-heading text-[17px] font-semibold text-text">Still to follow</h2>
                                    <p class="mt-1 font-sans text-[12px] text-muted">{{ $pendingHandles->count() }} page{{ $pendingHandles->count() === 1 ? '' : 's' }} remaining</p>
                                </div>
                                <span class="hidden rounded-full bg-warning/10 px-3 py-1 font-sans text-[11px] font-medium text-warning sm:inline-flex">Required for verification</span>
                            </div>

                            <div class="flex flex-col gap-3">
                                @foreach ($pendingHandles as $handle)
                                    <?php
                                        $platformIcon = match ($handle->platform) {
                                            'facebook' => 'ri-facebook-fill',
                                            'instagram' => 'ri-instagram-line',
                                            'tiktok' => 'ri-tiktok-fill',
                                            'telegram' => 'ri-telegram-2-line',
                                            'youtube' => 'ri-youtube-line',
                                            'x' => 'ri-twitter-x-line',
                                            'whatsapp' => 'ri-whatsapp-line',
                                            default => 'ri-global-line',
                                        };
                                    ?>
                                    <article wire:key="required-social-{{ $handle->id }}" class="rounded-[18px] border border-muted/10 bg-surface p-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-[14px] bg-background text-primary">
                                                <i class="{{ $platformIcon }} text-[22px]"></i>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="font-heading text-[15px] font-semibold text-text">{{ $handle->name }}</h3>
                                                    <span class="rounded-full bg-primary/8 px-2.5 py-1 font-sans text-[10px] font-medium uppercase tracking-wide text-primary">{{ \Illuminate\Support\Str::headline($handle->platform) }}</span>
                                                </div>
                                                @if ($handle->handle)
                                                    <p class="mt-1 font-sans text-[12px] text-muted">{{ $handle->handle }}</p>
                                                @endif
                                                @if ($handle->description)
                                                    <p class="mt-1 font-sans text-[12px] leading-5 text-muted">{{ $handle->description }}</p>
                                                @endif
                                            </div>
                                            <div x-data="{ opened: false }" class="flex shrink-0 flex-col gap-2 sm:w-[180px]">
                                                <a href="{{ $handle->url }}" target="_blank" rel="noopener noreferrer" x-on:click="opened = true"
                                                   class="inline-flex h-[40px] items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 font-sans text-[12px] font-medium text-primary transition hover:bg-primary/10">
                                                    <i class="ri-external-link-line text-[15px]"></i> Open social page
                                                </a>
                                                <button type="button" x-bind:disabled="!opened" wire:click="confirmFollow('{{ $handle->id }}')" wire:loading.attr="disabled" wire:target="confirmFollow('{{ $handle->id }}')"
                                                        class="inline-flex h-[40px] items-center justify-center gap-2 rounded-full bg-primary px-3 font-sans text-[12px] font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                                                    <i class="ri-check-line text-[15px]"></i> I followed this page
                                                </button>
                                            </div>
                                        </div>
                                        @include('livewire.dashboard.social-follow-proof-upload', ['handle' => $handle, 'proof' => $proofsByHandle->get($handle->id)])
                                    </article>
                                @endforeach
                            </div>
							<br />
                        </section>
                    @elseif ($requiredCount === 0)
                        <section class="rounded-[18px] border border-success/20 bg-success/5 p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-success/10 text-success"><i class="ri-check-double-line text-[20px]"></i></span>
                                <div>
                                    <h2 class="font-heading text-[15px] font-semibold text-text">No pages to follow right now</h2>
                                    <p class="mt-1 font-sans text-[12px] leading-5 text-muted">Your account dashboard is available.</p>
                                </div>
                            </div>
                        </section>
                    @else
                        <section class="rounded-[18px] border border-success/20 bg-success/5 p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-success/10 text-success"><i class="ri-check-double-line text-[20px]"></i></span>
                                <div>
                                    <h2 class="font-heading text-[15px] font-semibold text-text">You’re all set</h2>
                                    <p class="mt-1 font-sans text-[12px] leading-5 text-muted">All required social pages are confirmed. Your account dashboard is available.</p>
                                </div>
                            </div>
                        </section>
                    @endif

                </div>

            </section>

        </div>
    @endif
</div>
