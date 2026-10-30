<div>
    <x-backoffice.page title="Banks" subtitle="Banks users can withdraw to">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 w-full lg:max-w-[560px]">
                <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm flex-1 min-w-0">
                    <i class="ri-search-line text-muted text-[17px]"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search banks…"
                           class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                </div>
                <select wire:model.live="countryFilter"
                        class="shrink-0 h-[44px] px-3 rounded-[14px] bg-surface shadow-sm font-sans text-[13px] text-text outline-none border-0 focus:ring-2 focus:ring-primary/40">
                    <option value="">All countries</option>
                    @foreach ($countries as $c)
                        <option value="{{ $c }}">{{ $countryNames[$c] ?? $c }}</option>
                    @endforeach
                </select>
            </div>
            @if ($canWrite)
                <button type="button" wire:click="openCreate" aria-label="Add bank" class="shrink-0 inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]"><i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add bank</span></button>
            @endif
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($banks->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No banks yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[920px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Bank</th>
                                <th class="px-5 py-3 font-medium">Country</th>
                                <th class="px-5 py-3 font-medium">Code</th>
                                <th class="px-5 py-3 font-medium">Institution code</th>
                                <th class="px-5 py-3 font-medium">Type</th>
                                <th class="px-5 py-3 font-medium">Tag</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($banks as $bank)
                                <tr wire:key="bank-{{ $bank->id }}" wire:click="openDetail('{{ $bank->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3 font-sans text-text text-[13px]">{{ $bank->name }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] whitespace-nowrap">{{ $countryNames[$bank->country] ?? $bank->country }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $bank->code }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">{{ $bank->institution_code ?: '—' }}</td>
                                    <td class="px-5 py-3"><x-backoffice.badge tone="muted">{{ \Illuminate\Support\Str::headline($bank->type) }}</x-backoffice.badge></td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $bank->tag ?: '—' }}</td>
                                    <td class="px-5 py-3">
                                        @if ($bank->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($banks->hasPages())<div>{{ $banks->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit bank' : 'Add bank'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Bank name</label>
                    <input type="text" wire:model="name" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Code</label>
                        <input type="text" wire:model="code" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('code') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Tag (optional)</label>
                        <input type="text" wire:model="tag" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Institution code (optional)</label>
                    <input type="text" inputmode="numeric" wire:model="institutionCode" placeholder="e.g. 000014" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                    <span class="font-sans text-[11px] text-muted">NIBSS/NIP code — used on payout sheets that require it (e.g. VFD).</span>
                    @error('institutionCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Country</label>
                        <select wire:model="country" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @foreach ($countries->merge([$country])->unique()->sort() as $c)
                                <option value="{{ $c }}">{{ $countryNames[$c] ?? $c }}</option>
                            @endforeach
                        </select>
                        @error('country') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Type</label>
                        <select wire:model="type" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @foreach ($types as $t)
                                <option value="{{ $t }}">{{ \Illuminate\Support\Str::headline($t) }}</option>
                            @endforeach
                        </select>
                        @error('type') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <x-backoffice.toggle wire:model="isActive" label="Active" />
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showDetail" title="Manage bank" closeAction="$set('showDetail', false)">
            @if ($detailBank)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-building-line text-[22px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px] truncate">{{ $detailBank->name }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">{{ $countryNames[$detailBank->country] ?? $detailBank->country }} · Code {{ $detailBank->code }}{{ $detailBank->institution_code ? ' · Inst '.$detailBank->institution_code : '' }}{{ $detailBank->tag ? ' · '.$detailBank->tag : '' }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                @if ($detailBank->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                <x-backoffice.badge tone="muted">{{ \Illuminate\Support\Str::headline($detailBank->type) }}</x-backoffice.badge>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <button type="button" wire:click="edit('{{ $detailBank->id }}')"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-edit-2-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Edit details</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleActive', params: ['{{ $detailBank->id }}'], title: '{{ $detailBank->is_active ? 'Disable bank' : 'Enable bank' }}', message: '{{ $detailBank->is_active ? 'Users will no longer be able to select this bank for payouts.' : 'This bank will be selectable for payouts again.' }}', confirmText: '{{ $detailBank->is_active ? 'Disable' : 'Enable' }}', tone: '{{ $detailBank->is_active ? 'warning' : 'primary' }}' })"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="{{ $detailBank->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }} text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">{{ $detailBank->is_active ? 'Disable bank' : 'Enable bank' }}</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $detailBank->id }}'], title: 'Delete bank', message: 'This bank will be removed and users will no longer be able to withdraw to it.', confirmText: 'Delete', tone: 'danger' })"
                                class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                            <i class="ri-delete-bin-6-line text-danger text-[18px]"></i>
                            <span class="flex-1 font-sans text-danger text-[13px]">Delete bank</span>
                            <i class="ri-arrow-right-s-line text-danger/60"></i>
                        </button>
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
