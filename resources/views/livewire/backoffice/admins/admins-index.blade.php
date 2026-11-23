<div>
    <x-backoffice.page title="Administrators" subtitle="Super administrator access control">
        @if ($generatedPassword)
            <div class="rounded-[14px] border border-success/30 bg-success/8 px-4 py-3">
                <p class="font-sans text-[12px] text-success font-semibold mb-1">Generated password (shown once)</p>
                <code class="block font-mono text-[13px] text-text select-all bg-surface rounded-[10px] px-3 py-2 break-all">{{ $generatedPassword }}</code>
                <div class="flex items-center justify-between mt-2 gap-2">
                    <p class="font-sans text-[11px] text-muted">Share it through a secure channel. It is not stored and cannot be retrieved.</p>
                    <button type="button" wire:click="dismissGeneratedPassword" class="btn btn-quiet btn-sm shrink-0">Dismiss</button>
                </div>
            </div>
        @endif

        <x-backoffice.card title="Create administrator">
            <form wire:submit="create" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-3 flex flex-col gap-1.5">
                    <label class="label" for="newName">Full name</label>
                    <input id="newName" type="text" wire:model="newName" class="input" required>
                    @error('newName')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="md:col-span-3 flex flex-col gap-1.5">
                    <label class="label" for="newEmail">Email</label>
                    <input id="newEmail" type="email" wire:model="newEmail" class="input" required>
                    @error('newEmail')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="md:col-span-2 flex flex-col gap-1.5">
                    <label class="label" for="newRole">Role</label>
                    <select id="newRole" wire:model="newRole" class="input">
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super admin</option>
                    </select>
                    @error('newRole')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="md:col-span-3 flex flex-col gap-1.5">
                    <label class="label" for="newPassword">Password <span class="text-muted">(blank = generate)</span></label>
                    <input id="newPassword" type="text" wire:model="newPassword" class="input" placeholder="At least 12 characters" autocomplete="off">
                    @error('newPassword')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="md:col-span-1 flex items-end">
                    <button type="submit" class="btn btn-primary btn-md w-full"><i class="ri-user-add-line"></i> Create</button>
                </div>
            </form>
        </x-backoffice.card>

        <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
            <i class="ri-search-line text-muted text-[17px]"></i>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or email…"
                   class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
            @endif
        </div>

        @php($currentAdminId = auth('admin')->id())

        <x-backoffice.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-muted/10">
                            <th class="th">Administrator</th>
                            <th class="th">Role</th>
                            <th class="th">Status</th>
                            <th class="th">Last login</th>
                            <th class="th-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admins as $admin)
                            <tr wire:key="admin-{{ $admin->id }}" class="tr">
                                <td class="td">
                                    <p class="font-sans text-text text-[13px]">{{ $admin->full_name }}@if ($admin->id === $currentAdminId)<span class="text-muted"> (you)</span>@endif</p>
                                    <p class="font-sans text-muted text-[11px]">{{ $admin->email }}</p>
                                </td>
                                <td class="td"><x-backoffice.badge :tone="$admin->isSuperAdmin() ? 'warning' : 'info'">{{ $admin->roleLabel() }}</x-backoffice.badge></td>
                                <td class="td"><x-backoffice.badge :tone="$admin->status === 'active' ? 'success' : 'muted'">{{ \Illuminate\Support\Str::headline($admin->status) }}</x-backoffice.badge></td>
                                <td class="td text-muted">{{ $admin->last_login_at?->format('j M, H:i') ?? 'Never' }}</td>
                                <td class="td text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" wire:click="toggleRole({{ $admin->id }})" class="btn btn-quiet btn-sm" title="Toggle admin / super admin" @if ($admin->id === $currentAdminId) disabled @endif>
                                            <i class="ri-shield-user-line"></i> Role
                                        </button>
                                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'resetPassword', params: [{{ $admin->id }}], title: 'Reset password?', message: 'A new temporary password is generated and shown once.', confirmText: 'Reset', tone: 'warning' })" class="btn btn-quiet btn-sm" title="Reset password">
                                            <i class="ri-key-line"></i> Reset
                                        </button>
                                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleStatus', params: [{{ $admin->id }}], title: '{{ $admin->status === 'active' ? 'Disable' : 'Enable' }} administrator?', message: '{{ $admin->status === 'active' ? 'This admin loses backoffice access immediately.' : 'This admin regains backoffice access.' }}', confirmText: '{{ $admin->status === 'active' ? 'Disable' : 'Enable' }}', tone: 'warning' })" class="btn {{ $admin->status === 'active' ? 'btn-quiet' : 'btn-primary' }} btn-sm" @if ($admin->id === $currentAdminId) disabled @endif title="Toggle access">
                                            <i class="{{ $admin->status === 'active' ? 'ri-user-unfollow-line' : 'ri-user-follow-line' }}"></i> {{ $admin->status === 'active' ? 'Disable' : 'Enable' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($admins->hasPages())
                <div class="px-5 py-4">{{ $admins->links('vendor.pagination.backoffice') }}</div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
