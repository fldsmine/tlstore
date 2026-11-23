<div>
    <x-backoffice.page title="Administrators" subtitle="Manage backoffice access">
        @php $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        {{-- Password is shown once here and not retrievable later. --}}
        @if ($generatedCredentials)
            <div x-data="{ copied: '' }" class="flex items-start gap-3 rounded-[16px] bg-success/10 border border-success/25 px-4 py-3.5">
                <i class="ri-key-2-line text-success text-[20px] shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[14px]">New credentials</p>
                    <div class="mt-1.5 flex flex-col gap-1.5">
                        @foreach (['email' => 'Email', 'password' => 'Password'] as $key => $lbl)
                            <div class="flex items-center gap-2">
                                <span class="font-sans text-muted text-[12px] w-[68px] shrink-0">{{ $lbl }}</span>
                                <span x-ref="cred_{{ $key }}" class="font-mono text-text text-[13px] break-all min-w-0">{{ $generatedCredentials[$key] }}</span>
                                <button type="button"
                                        x-on:click="nextelCopy($refs.cred_{{ $key }}.textContent).then(ok => { if (!ok) return; copied = '{{ $key }}'; setTimeout(() => { if (copied === '{{ $key }}') copied = '' }, 1500) })"
                                        class="shrink-0 size-7 rounded-lg bg-success/15 text-success flex items-center justify-center hover:bg-success/25 transition" aria-label="Copy {{ $lbl }}">
                                    <i class="ri-file-copy-line text-[14px]" x-show="copied !== '{{ $key }}'"></i>
                                    <i class="ri-check-line text-[14px]" x-show="copied === '{{ $key }}'" style="display:none"></i>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <button type="button" wire:click="$set('generatedCredentials', null)" class="shrink-0 text-success" aria-label="Dismiss"><i class="ri-close-line"></i></button>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[340px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search admins…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <button type="button" wire:click="openCreate" aria-label="Add admin" class="shrink-0 inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]"><i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add admin</span></button>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($admins->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No admins found.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Admin</th>
                                <th class="px-5 py-3 font-medium">Role</th>
                                <th class="px-5 py-3 font-medium">Joined</th>
                                <th class="px-5 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($admins as $admin)
                                <tr wire:key="adm-{{ $admin->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                                                @if ($admin->avatar)<img src="{{ $admin->avatar }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($admin->name, 0, 2)) }}@endif
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate">{{ $admin->name }}</p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ $admin->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$admin->role->color()">{{ $admin->role->getName() }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $admin->created_at?->format('j M Y, g:i A') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end">
                                            @if ($admin->isSuper())
                                                <span class="inline-flex items-center gap-1 font-sans text-muted text-[12px]"><i class="ri-shield-keyhole-line"></i> Protected</span>
                                            @else
                                                <button type="button" wire:click="openDetail('{{ $admin->id }}')" class="size-9 rounded-full bg-background text-muted hover:text-text flex items-center justify-center transition" aria-label="Manage admin">
                                                    <i class="ri-more-2-fill text-[18px]"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($admins->hasPages())<div class="pt-1">{{ $admins->links('vendor.pagination.nextel-numbered') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit admin' : 'Add admin'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="flex items-center gap-4">
                    <span class="size-14 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[16px] shrink-0">
                        @if ($photo)<img src="{{ $photo->temporaryUrl() }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($name ?: 'NA', 0, 2)) }}@endif
                    </span>
                    <label class="inline-flex items-center gap-2 cursor-pointer font-sans text-[13px] text-primary">
                        <i class="ri-upload-2-line"></i> Avatar (optional)
                        <input type="file" wire:model="photo" accept="image/*" class="hidden">
                    </label>
                </div>
                @error('photo') <span class="font-sans text-[12px] text-danger -mt-2">{{ $message }}</span> @enderror

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Name</label>
                    <input type="text" wire:model="name" class="{{ $input }}">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Email</label>
                    <input type="email" wire:model="email" class="{{ $input }}">
                    @error('email') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Role</label>
                    <select wire:model="role" class="{{ $input }}">
                        <option value="regular">Regular (full access except admins)</option>
                        <option value="support">Support (read-only, no settings)</option>
                    </select>
                    @error('role') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                @unless ($editingId)
                    <p class="font-sans text-[12px] text-muted">A random password is generated and shown once after creating.</p>
                @endunless
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save' : 'Create admin' }}</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> {{ $editingId ? 'Saving' : 'Creating' }}…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showDetail" title="Manage admin" closeAction="$set('showDetail', false)">
            @if ($detailAdmin)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[16px] shrink-0">
                            @if ($detailAdmin->avatar)<img src="{{ $detailAdmin->avatar }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($detailAdmin->name, 0, 2)) }}@endif
                        </span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px] truncate">{{ $detailAdmin->name }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">{{ $detailAdmin->email }}</p>
                            <div class="mt-1.5"><x-backoffice.badge :tone="$detailAdmin->role->color()">{{ $detailAdmin->role->getName() }}</x-backoffice.badge></div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button type="button" wire:click="edit('{{ $detailAdmin->id }}')"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-edit-2-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Edit details</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'resetPassword', params: ['{{ $detailAdmin->id }}'], title: 'Change password', message: 'A new password is generated and shown once.', confirmText: 'Change password', tone: 'primary' })"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-key-2-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Change password</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $detailAdmin->id }}'], title: 'Delete admin', message: 'This admin will lose all backoffice access. This cannot be undone.', confirmText: 'Delete', tone: 'danger' })"
                                class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                            <i class="ri-delete-bin-6-line text-danger text-[18px]"></i>
                            <span class="flex-1 font-sans text-danger text-[13px]">Delete admin</span>
                            <i class="ri-arrow-right-s-line text-danger/60"></i>
                        </button>
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
