<div>
    <x-backoffice.page title="Social follows" subtitle="Manage required pages, user confirmations, and proof documentation">
        @if ($this->canWrite() && $tab === 'handles')
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="Add social page"
                        class="inline-flex h-[44px] w-[44px] items-center justify-center gap-1.5 rounded-[14px] bg-primary text-white sm:w-auto sm:px-4">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add social page</span>
                </button>
            </x-slot:action>
        @endif

        @php
            $canWrite = $this->canWrite();
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-backoffice.card>
                <p class="font-sans text-[12px] text-muted">Active required pages</p>
                <p class="mt-1 font-heading text-[24px] font-semibold text-text">{{ number_format($activeHandleCount) }}</p>
            </x-backoffice.card>
            <x-backoffice.card>
                <p class="font-sans text-[12px] text-muted">Current user confirmations</p>
                <p class="mt-1 font-heading text-[24px] font-semibold text-text">{{ number_format($currentFollowCount) }}</p>
            </x-backoffice.card>
            <x-backoffice.card>
                <p class="font-sans text-[12px] text-muted">Documentation uploads</p>
                <p class="mt-1 font-heading text-[24px] font-semibold text-text">{{ number_format($proofCount) }}</p>
            </x-backoffice.card>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex h-[44px] w-full items-center gap-2 rounded-[14px] bg-surface px-3.5 shadow-sm lg:max-w-[340px]">
                <i class="ri-search-line text-[17px] text-muted"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search pages, users, or proofs…"
                       class="min-w-0 flex-1 border-0 bg-transparent p-0 font-sans text-[13px] text-text outline-none placeholder:text-muted">
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" wire:click="setTab('handles')" class="h-[36px] rounded-full px-4 font-sans text-[12px] transition {{ $tab === 'handles' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                    Social pages
                </button>
                <button type="button" wire:click="setTab('follows')" class="h-[36px] rounded-full px-4 font-sans text-[12px] transition {{ $tab === 'follows' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                    User follow records
                </button>
                <button type="button" wire:click="setTab('proofs')" class="h-[36px] rounded-full px-4 font-sans text-[12px] transition {{ $tab === 'proofs' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                    Documentation uploads
                </button>
            </div>
        </div>

        @if ($tab === 'handles')
            <x-backoffice.card class="!p-0 overflow-hidden">
                @if ($handles->isEmpty())
                    <div class="flex flex-col items-center gap-2 p-10 text-center">
                        <span class="flex size-12 items-center justify-center rounded-full bg-background text-muted"><i class="ri-user-follow-line text-[22px]"></i></span>
                        <p class="font-sans text-[14px] text-muted">No social pages{{ trim($search) !== '' ? ' match your search' : ' have been added' }}.</p>
                        <p class="max-w-[440px] font-sans text-[12px] leading-5 text-muted">Every active page is a required follow. Add the official page link, and users who have not confirmed it will be asked before entering the dashboard.</p>
                        @if ($canWrite)
                            <button type="button" wire:click="openCreate" class="mt-2 inline-flex h-[40px] items-center gap-2 rounded-full bg-primary px-4 font-sans text-[13px] text-white"><i class="ri-add-line"></i>Add social page</button>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[740px] text-left">
                            <thead>
                                <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">Social page</th>
                                    <th class="px-5 py-3 font-medium">Platform</th>
                                    <th class="px-5 py-3 text-right font-medium">Confirmed</th>
                                    <th class="px-5 py-3 font-medium">Requirement</th>
                                    <th class="px-5 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($handles as $handle)
                                    <tr wire:key="social-handle-{{ $handle->id }}" class="border-b border-muted/5 last:border-0">
                                        <td class="px-5 py-3">
                                            <a href="{{ $handle->url }}" target="_blank" rel="noopener noreferrer" class="font-heading text-[14px] font-medium text-text hover:text-primary">{{ $handle->name }}</a>
                                            @if ($handle->handle)<p class="mt-0.5 font-sans text-[12px] text-muted">{{ $handle->handle }}</p>@endif
                                            @if ($handle->description)<p class="mt-1 max-w-[420px] font-sans text-[12px] leading-5 text-muted">{{ $handle->description }}</p>@endif
                                        </td>
                                        <td class="px-5 py-3 font-sans text-[13px] text-muted">{{ \Illuminate\Support\Str::headline($handle->platform) }}</td>
                                        <td class="px-5 py-3 text-right font-heading text-[14px] text-text">{{ number_format($handle->current_followers_count) }}</td>
                                        <td class="px-5 py-3">
                                            @if ($handle->is_active)
                                                <x-backoffice.badge tone="success">Required</x-backoffice.badge>
                                            @else
                                                <x-backoffice.badge tone="muted">Disabled</x-backoffice.badge>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($canWrite)
                                                <div class="flex justify-end gap-1.5">
                                                    <button type="button" wire:click="edit('{{ $handle->id }}')" aria-label="Edit {{ $handle->name }}" class="flex size-9 items-center justify-center rounded-full bg-background text-muted hover:text-primary"><i class="ri-edit-2-line"></i></button>
                                                    <button type="button" aria-label="{{ $handle->is_active ? 'Disable' : 'Enable' }} {{ $handle->name }}"
                                                            x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleActive', params: ['{{ $handle->id }}'], title: '{{ $handle->is_active ? 'Disable required page' : 'Enable required page' }}', message: '{{ $handle->is_active ? 'Users will no longer be asked to follow this page.' : 'Users who have not confirmed this page will be required to follow it.' }}', confirmText: '{{ $handle->is_active ? 'Disable' : 'Enable' }}', tone: '{{ $handle->is_active ? 'warning' : 'info' }}' })"
                                                            class="flex size-9 items-center justify-center rounded-full bg-background {{ $handle->is_active ? 'text-warning' : 'text-success' }}"><i class="{{ $handle->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i></button>
                                                </div>
                                            @else
                                                <span class="block text-right font-sans text-[12px] text-muted">Read only</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-backoffice.card>
            @if ($handles->hasPages())<div>{{ $handles->links('vendor.pagination.nextel') }}</div>@endif
        @elseif ($tab === 'follows')
            <x-backoffice.card class="!p-0 overflow-hidden">
                @if ($follows->isEmpty())
                    <div class="p-10 text-center font-sans text-[14px] text-muted">No follow confirmations{{ trim($search) !== '' ? ' match your search' : ' recorded yet' }}.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left">
                            <thead>
                                <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">User</th>
                                    <th class="px-5 py-3 font-medium">Social page</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium">Confirmed</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($follows as $follow)
                                    <tr wire:key="social-follow-{{ $follow->id }}" class="border-b border-muted/5 last:border-0">
                                        <td class="px-5 py-3">
                                            @if ($follow->user)
                                                <p class="font-heading text-[13px] font-medium text-text">{{ $follow->user->full_name }}</p>
                                                <p class="font-sans text-[12px] text-muted">{{ '@'.$follow->user->username }} · {{ $follow->user->email }}</p>
                                            @else
                                                <span class="font-sans text-[13px] text-muted">Deleted user</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <p class="font-sans text-[13px] text-text">{{ $follow->socialHandle?->name ?? 'Removed social page' }}</p>
                                            @if ($follow->socialHandle)<p class="font-sans text-[11px] text-muted">{{ \Illuminate\Support\Str::headline($follow->socialHandle->platform) }} · {{ $follow->socialHandle->handle }}</p>@endif
                                        </td>
                                        <td class="px-5 py-3">
                                            @if ($follow->unfollowed_at === null)
                                                <x-backoffice.badge tone="success">Following</x-backoffice.badge>
                                            @elseif ($follow->unfollowed_by_admin_id)
                                                <x-backoffice.badge tone="warning">Reset by admin</x-backoffice.badge>
                                            @else
                                                <x-backoffice.badge tone="muted">Marked unfollowed</x-backoffice.badge>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 font-sans text-[12px] text-muted">
                                            {{ $follow->followed_at?->format('j M Y, g:i A') }}
                                            @if ($follow->unfollowed_at)<p class="mt-0.5">Removed {{ $follow->unfollowed_at->format('j M Y, g:i A') }}</p>@endif
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            @if ($canWrite && $follow->unfollowed_at === null)
                                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'resetFollow', params: ['{{ $follow->id }}'], title: 'Reset follow confirmation', message: 'This user will be asked to follow this page again before they can access the dashboard.', confirmText: 'Reset', tone: 'warning' })" class="rounded-full border border-warning/20 px-3 py-2 font-sans text-[11px] text-warning hover:bg-warning/5">Reset</button>
                                            @else
                                                <span class="font-sans text-[12px] text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-backoffice.card>
            @if ($follows->hasPages())<div>{{ $follows->links('vendor.pagination.nextel') }}</div>@endif
        @else
            <p class="rounded-[14px] bg-surface px-4 py-3 font-sans text-[12px] leading-5 text-muted">
                User-submitted proofs are kept for documentation only. They do not approve a follow or change dashboard access.
            </p>
            <x-backoffice.card class="!p-0 overflow-hidden">
                @if ($proofs->isEmpty())
                    <div class="p-10 text-center font-sans text-[14px] text-muted">No documentation uploads{{ trim($search) !== '' ? ' match your search' : ' have been submitted' }}.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[980px] text-left">
                            <thead>
                                <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">User</th>
                                    <th class="px-5 py-3 font-medium">Social page</th>
                                    <th class="px-5 py-3 font-medium">Documented target</th>
                                    <th class="px-5 py-3 font-medium">File</th>
                                    <th class="px-5 py-3 font-medium">Uploaded</th>
                                    <th class="px-5 py-3 text-right font-medium">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($proofs as $proof)
                                    <tr wire:key="social-follow-proof-{{ $proof->id }}" class="border-b border-muted/5 last:border-0">
                                        <td class="px-5 py-3">
                                            <p class="font-heading text-[13px] font-medium text-text">{{ $proof->user?->full_name ?? 'Deleted user' }}</p>
                                            @if ($proof->user)<p class="font-sans text-[12px] text-muted">{{ '@'.$proof->user->username }} · {{ $proof->user->email }}</p>@endif
                                        </td>
                                        <td class="px-5 py-3 font-sans text-[13px] text-text">{{ $proof->socialHandle?->name ?? 'Removed social page' }}</td>
                                        <td class="px-5 py-3">
                                            <a href="{{ $proof->target_url }}" target="_blank" rel="noopener noreferrer" class="break-all font-sans text-[12px] text-primary hover:underline">{{ $proof->target_url }}</a>
                                        </td>
                                        <td class="px-5 py-3 font-sans text-[12px] text-text">{{ $proof->original_name }}</td>
                                        <td class="px-5 py-3 font-sans text-[12px] text-muted">{{ $proof->created_at?->format('j M Y, g:i A') }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <button type="button" wire:click="downloadProof('{{ $proof->id }}')" wire:loading.attr="disabled" wire:target="downloadProof('{{ $proof->id }}')" class="rounded-full bg-primary/10 px-3 py-2 font-sans text-[11px] font-medium text-primary hover:bg-primary/15 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="downloadProof('{{ $proof->id }}')">Download</span>
                                                <span wire:loading wire:target="downloadProof('{{ $proof->id }}')">Preparing…</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-backoffice.card>
            @if ($proofs->hasPages())<div>{{ $proofs->links('vendor.pagination.nextel') }}</div>@endif
        @endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit social page' : 'Add social page'" size="lg">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="social-platform" class="font-sans text-[12px] text-muted">Platform</label>
                        <select id="social-platform" wire:model="platform" class="{{ $input }}">
                            @foreach (\App\Livewire\Backoffice\SocialFollows\ManageSocialFollows::PLATFORMS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('platform')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="social-name" class="font-sans text-[12px] text-muted">Display name</label>
                        <input id="social-name" type="text" maxlength="120" wire:model="name" placeholder="Nextel Connect on Instagram" class="{{ $input }}">
                        @error('name')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="social-handle" class="font-sans text-[12px] text-muted">Handle <span class="text-muted/60">(optional)</span></label>
                        <input id="social-handle" type="text" maxlength="150" wire:model="handle" placeholder="@nextelconnect" class="{{ $input }}">
                        @error('handle')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="social-sort-order" class="font-sans text-[12px] text-muted">Display order</label>
                        <input id="social-sort-order" type="number" min="0" max="65535" wire:model="sortOrder" class="{{ $input }}">
                        @error('sortOrder')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="social-url" class="font-sans text-[12px] text-muted">Public page URL</label>
                    <input id="social-url" type="url" maxlength="500" wire:model="url" placeholder="https://www.instagram.com/nextelconnect" class="{{ $input }}">
                    @error('url')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="social-description" class="font-sans text-[12px] text-muted">Instructions <span class="text-muted/60">(optional)</span></label>
                    <textarea id="social-description" rows="3" maxlength="1000" wire:model="description" placeholder="Follow the official page, then return and confirm below." class="rounded-[12px] border border-muted/15 bg-background px-3 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                    @error('description')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="rounded-[14px] bg-background px-4 py-3">
                    <label class="flex items-start gap-3 font-sans text-[13px] text-text">
                        <input type="checkbox" wire:model="isActive" class="mt-0.5 rounded border-muted/30 text-primary focus:ring-primary/30">
                        <span><strong class="font-medium">Active — required for dashboard access</strong><span class="mt-1 block text-[12px] leading-5 text-muted">Disabling a page stops it from blocking users but keeps existing follow records.</span></span>
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] rounded-full bg-background px-4 font-sans text-[13px] text-muted">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] rounded-full bg-primary px-5 font-sans text-[13px] text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save page</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
