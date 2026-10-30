<div>
    <x-backoffice.page title="My Account" subtitle="Manage your profile and password">
        <x-backoffice.account-tabs active="profile" />

        <div class="max-w-[820px] w-full flex flex-col gap-6">
            <x-backoffice.card>
                <form wire:submit="save" class="flex flex-col gap-5">
                    <div class="flex items-center gap-4">
                        <span class="size-16 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                            @if ($photo)
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-full object-cover">
                            @elseif ($admin->avatar)
                                <img src="{{ $admin->avatar }}" alt="" class="size-full object-cover">
                            @else
                                <span class="font-heading font-semibold text-primary text-[20px]">{{ strtoupper(substr($admin->name, 0, 2)) }}</span>
                            @endif
                        </span>
                        <div class="flex flex-col gap-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-sans text-[13px] text-primary">
                                <i class="ri-upload-2-line"></i> Change photo
                                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            </label>
                            <span wire:loading wire:target="photo" class="font-sans text-[12px] text-muted">Uploading…</span>
                            @error('photo') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="name" class="font-sans text-text text-[13px]">Name</label>
                        <input id="name" type="text" wire:model="name"
                               class="w-full h-[48px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="email" class="font-sans text-text text-[13px]">Email</label>
                        <input id="email" type="email" wire:model="email"
                               class="w-full h-[48px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('email') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px] hover:opacity-95 transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Save changes</span>
                            <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                        </button>
                    </div>
                </form>
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
