<div>
    <x-backoffice.page title="Ads" subtitle="Share-to-earn placements">
        @php
            $canWrite = $this->canWrite();
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        @if ($canWrite && $tab === 'ads')
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="Add ad"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add ad</span>
                </button>
            </x-slot:action>
        @endif

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ $tab === 'shares' ? 'User or ad…' : 'Ad name or advertiser…' }}"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['ads' => ['Ads', $adsCount], 'shares' => ['Ad Shares', $sharesCount]] as $key => [$label, $count])
                    <button type="button" wire:click="setTab('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $tab === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                        {{ $label }} <span class="opacity-70">({{ number_format($count) }})</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($tab === 'shares')
            <x-backoffice.card class="!p-0 overflow-hidden">
                @if ($shares->isEmpty())
                    <div class="p-10 text-center font-sans text-muted text-[14px]">No ad shares{{ trim($search) !== '' ? ' match your search' : ' yet' }}.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[640px]">
                            <thead>
                                <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">User</th>
                                    <th class="px-5 py-3 font-medium">Ad</th>
                                    <th class="px-5 py-3 font-medium text-right">Version</th>
                                    <th class="px-5 py-3 font-medium text-right">Earned</th>
                                    <th class="px-5 py-3 font-medium">Shared At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shares as $s)
                                    <tr wire:key="as-{{ $s->id }}" class="border-b border-muted/5 last:border-0">
                                        <td class="px-5 py-3"><x-backoffice.user-cell :user="$s->user" /></td>
                                        <td class="px-5 py-3 font-sans text-text text-[13px]">{{ $s->ad?->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-right font-sans text-muted text-[13px]">v{{ $s->ad_version }}</td>
                                        <td class="px-5 py-3 text-right font-heading text-success text-[13px] whitespace-nowrap">{{ money($s->amount) }}</td>
                                        <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $s->shared_at?->format('j M Y, g:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-backoffice.card>

            @if ($shares->hasPages())<div class="pt-1">{{ $shares->links('vendor.pagination.nextel') }}</div>@endif
        @else
            <x-backoffice.card class="!p-0 overflow-hidden">
                @if ($ads->isEmpty())
                    <div class="p-10 text-center font-sans text-muted text-[14px]">No ads{{ trim($search) !== '' ? ' match your search' : ' yet' }}.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[760px]">
                            <thead>
                                <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">Ad</th>
                                    <th class="px-5 py-3 font-medium text-right">Reward</th>
                                    <th class="px-5 py-3 font-medium text-right">Impr · Views · Shares</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ads as $ad)
                                    <tr wire:key="ad-{{ $ad->id }}" wire:click="openDetail('{{ $ad->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="size-11 rounded-[12px] bg-primary/10 overflow-hidden flex items-center justify-center text-primary shrink-0">
                                                    @if ($ad->image_url)<img src="{{ $ad->image_url }}" alt="" class="size-full object-cover">@else<i class="ri-advertisement-line text-[18px]"></i>@endif
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="font-sans text-text text-[13px] truncate">{{ $ad->name }}</p>
                                                    <p class="font-sans text-muted text-[12px] truncate">{{ $ad->advertiser }} · v{{ $ad->version }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 text-right font-heading text-success text-[13px] whitespace-nowrap">{{ money($ad->reward) }}</td>
                                        <td class="px-5 py-3 text-right font-sans text-muted text-[12px] whitespace-nowrap">{{ number_format($ad->impressions) }} · {{ number_format($ad->views) }} · {{ number_format($ad->shares) }}</td>
                                        <td class="px-5 py-3">
                                            @if ($ad->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-backoffice.card>

            @if ($ads->hasPages())<div class="pt-1">{{ $ads->links('vendor.pagination.nextel') }}</div>@endif
        @endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit ad' : 'Add ad'">
            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Name</label>
                        <input type="text" wire:model="name" class="{{ $input }}">
                        @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Advertiser</label>
                        <input type="text" wire:model="advertiser" class="{{ $input }}">
                        @error('advertiser') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Description <span class="text-muted/60">— shared in the message and shown on the user's site (optional)</span></label>
                    <textarea wire:model="description" rows="2" maxlength="280" class="{{ $input }} resize-none"></textarea>
                    @error('description') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Image (optional)</label>
                    <div class="flex items-center gap-4">
                        <span class="size-16 rounded-[12px] bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                            @if ($image)
                                <img src="{{ $image->temporaryUrl() }}" alt="" class="size-full object-cover">
                            @elseif ($existingImageUrl)
                                <img src="{{ $existingImageUrl }}" alt="" class="size-full object-cover">
                            @else
                                <i class="ri-image-line text-primary text-[20px]"></i>
                            @endif
                        </span>
                        <div class="flex flex-col gap-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-sans text-[13px] text-primary">
                                <i class="ri-upload-2-line"></i> Upload image
                                <input type="file" wire:model="image" accept="image/*" class="hidden">
                            </label>
                            <span wire:loading wire:target="image" class="font-sans text-[11px] text-muted">Uploading…</span>
                        </div>
                    </div>
                    @error('image') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Target URL (optional)</label>
                    <input type="url" wire:model="targetUrl" class="{{ $input }}">
                    @error('targetUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['reward','Reward / share ($)'],['impressions','Impressions'],['views','Views'],['growthPercent','Growth % / interval'],['growthIntervalHours','Grow every (hours)']] as [$field,$label])
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">{{ $label }}</label>
                            <input type="number" step="any" wire:model="{{ $field }}" class="{{ $input }}">
                            @error($field) <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>

                <x-backoffice.toggle wire:model="isActive" label="Active" />

                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save,image" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showDetail" title="Ad details" closeAction="$set('showDetail', false)">
            @if ($detailAd)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-[12px] bg-primary/10 overflow-hidden flex items-center justify-center text-primary shrink-0">
                            @if ($detailAd->image_url)<img src="{{ $detailAd->image_url }}" alt="" class="size-full object-cover">@else<i class="ri-advertisement-line text-[20px]"></i>@endif
                        </span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px] truncate">{{ $detailAd->name }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">{{ $detailAd->advertiser }} · v{{ $detailAd->version }} · {{ money($detailAd->reward) }}/share</p>
                            <div class="mt-1.5">@if ($detailAd->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif</div>
                        </div>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Reward / share', money($detailAd->reward)],
                            ['Impressions', number_format($detailAd->impressions)],
                            ['Views', number_format($detailAd->views)],
                            ['Shares', number_format($detailAd->shares)],
                            ['Reward / share', money($detailAd->reward).' (base '.money($detailAd->base_reward).')'],
                            ['Growth', rtrim(rtrim(number_format((float) $detailAd->growth_percent, 2), '0'), '.').'% every '.$detailAd->growth_interval_hours.'h — impressions, views, shares & reward'],
                            ['Last grown', $detailAd->last_grown_at?->format('j M Y, g:i A') ?? 'not yet'],
                            $detailAd->description ? ['Description', $detailAd->description] : null,
                            $detailAd->target_url ? ['Target URL', $detailAd->target_url] : null,
                            ['Created', $detailAd->created_at?->format('j M Y, g:i A')],
                        ]) as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right break-all">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->canWrite())
                    <div class="flex flex-col gap-2">
                        <button type="button" wire:click="edit('{{ $detailAd->id }}')"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-edit-2-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Edit details</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'bumpVersion', params: ['{{ $detailAd->id }}'], title: 'Bump ad version', message: 'Bumps the version so everyone can earn from this ad again.', confirmText: 'Bump', tone: 'primary' })"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-refresh-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Bump version</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'resetReward', params: ['{{ $detailAd->id }}'], title: 'Reset reward', message: 'Restores the reward per share to its base of {{ money($detailAd->base_reward) }}, undoing any growth.', confirmText: 'Reset', tone: 'warning' })"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-arrow-go-back-line text-warning text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Reset reward to base</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button"
                                x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleActive', params: ['{{ $detailAd->id }}'], title: '{{ $detailAd->is_active ? 'Disable ad' : 'Enable ad' }}', message: '{{ $detailAd->is_active ? 'Users will stop seeing this ad and can no longer earn from it.' : 'Users will start seeing this ad and can earn from it again.' }}', confirmText: '{{ $detailAd->is_active ? 'Disable' : 'Enable' }}', tone: '{{ $detailAd->is_active ? 'warning' : 'primary' }}' })"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="{{ $detailAd->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }} text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">{{ $detailAd->is_active ? 'Disable ad' : 'Enable ad' }}</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $detailAd->id }}'], title: 'Delete ad', message: 'This permanently removes the ad and stops new earnings.', confirmText: 'Delete', tone: 'danger' })"
                                class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                            <i class="ri-delete-bin-6-line text-danger text-[18px]"></i>
                            <span class="flex-1 font-sans text-danger text-[13px]">Delete ad</span>
                            <i class="ri-arrow-right-s-line text-danger/60"></i>
                        </button>
                    </div>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
