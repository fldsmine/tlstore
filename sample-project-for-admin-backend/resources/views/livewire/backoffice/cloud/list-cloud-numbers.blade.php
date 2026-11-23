<div>
    <x-backoffice.page title="Sponsor Numbers" subtitle="Sponsor lines users call to earn — every active line is callable by any Cloud user">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[340px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search numbers…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            @if ($canWrite)
                <button type="button" wire:click="openCreate" aria-label="Add number" class="shrink-0 inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]"><i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add number</span></button>
            @endif
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($numbers->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No sponsor numbers yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[820px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Number</th>
                                <th class="px-5 py-3 font-medium">Line</th>
                                <th class="px-5 py-3 font-medium">Earning / call</th>
                                <th class="px-5 py-3 font-medium">Notes</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($numbers as $number)
                                <tr wire:key="number-{{ $number->id }}" class="border-b border-muted/5 last:border-0 hover:bg-background/50 transition-colors">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('backoffice.cloud.numbers.show', $number) }}" wire:navigate class="flex items-center gap-3">
                                            @if ($number->avatar_url)
                                                <img src="{{ $number->avatar_url }}" alt="" class="size-9 rounded-full object-cover bg-background shrink-0">
                                            @else
                                                <span class="size-9 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-customer-service-2-line text-[17px]"></i></span>
                                            @endif
                                            <span class="min-w-0 flex flex-col">
                                                <span class="font-sans text-text text-[13px] truncate">{{ $number->name }}</span>
                                                @if ($number->brand)<span class="font-sans text-muted text-[12px] truncate">{{ $number->brand }}</span>@endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">{{ $number->line_number }}</td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] tabular-nums"><x-money :usd="$number->earning_per_call" :abbreviate="false" /></td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $number->notes_count }} {{ \Illuminate\Support\Str::plural('note', $number->notes_count) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($number->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($canWrite)
                                                <button type="button" wire:click="edit('{{ $number->id }}')" aria-label="Edit" class="size-8 rounded-full bg-background flex items-center justify-center text-muted hover:text-text transition-colors"><i class="ri-pencil-line text-[15px]"></i></button>
                                            @endif
                                            <a href="{{ route('backoffice.cloud.numbers.show', $number) }}" wire:navigate aria-label="View" class="size-8 rounded-full bg-background flex items-center justify-center text-muted hover:text-text transition-colors"><i class="ri-arrow-right-line text-[15px]"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($numbers->hasPages())<div>{{ $numbers->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit number' : 'Add number'" size="lg">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Saved name</label>
                    <input type="text" wire:model="name" placeholder="BetKing Bonus Drop" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Line number</label>
                        <input type="text" wire:model="lineNumber" placeholder="+1 555 010 4321" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('lineNumber') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Brand <span class="text-muted/60">(optional)</span></label>
                        <input type="text" wire:model="brand" placeholder="BetKing" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('brand') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Caller name <span class="text-muted/60">(person on the call)</span></label>
                        <input type="text" wire:model="callerName" placeholder="Adaeze from BetKing" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('callerName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Pop-up message <span class="text-muted/60">(shown on the call screen)</span></label>
                    <textarea wire:model="popupMessage" rows="2" placeholder="New deals live. Claim your reward before it ends!" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2.5 font-sans text-[13px] text-text leading-relaxed focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                    @error('popupMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Claim link <span class="text-muted/60">(optional — the pop-up's button)</span></label>
                    <input type="url" wire:model="claimUrl" placeholder="https://sponsor.example/offer" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('claimUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Earning per call ($)</label>
                        <input type="number" step="any" min="0" wire:model="earningPerCall" placeholder="0.50" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('earningPerCall') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Sort order</label>
                        <input type="number" min="0" wire:model="sortOrder" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('sortOrder') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
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
    </x-backoffice.page>
</div>
