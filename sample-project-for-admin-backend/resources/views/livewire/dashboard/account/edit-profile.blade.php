<x-app.settings-shell title="Edit Profile" :back="route('profile')">

    @php $initials = strtoupper(substr($user->first_name ?: $user->username, 0, 2)); @endphp

    <div class="flex flex-col gap-[22px]">

        <div class="self-center flex flex-col items-center gap-2">
            <div class="relative">
                <span class="size-[105px] rounded-full overflow-hidden bg-primary/10 flex items-center justify-center ring-2 ring-surface shadow-sm">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-full object-cover">
                    @elseif ($user->avatar)
                        <img src="{{ $user->avatar }}" alt="Profile photo" class="size-full object-cover">
                    @else
                        <span class="font-heading font-semibold text-primary text-[34px]">{{ $initials }}</span>
                    @endif
                </span>
                <label class="absolute -right-1 bottom-1 size-9 rounded-full bg-primary text-surface flex items-center justify-center shadow-md cursor-pointer hover:opacity-95 transition" aria-label="Change photo">
                    <i class="ri-camera-line text-[17px]" wire:loading.remove wire:target="photo"></i>
                    <i class="ri-loader-4-line text-[17px] animate-spin" wire:loading wire:target="photo"></i>
                    <input type="file" wire:model="photo" accept="image/*" class="hidden">
                </label>
            </div>
            @error('photo')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
            <p class="font-sans text-[12px] text-muted">Tap the camera to change your photo</p>
        </div>

        @if ($saved)
            <div class="flex items-center gap-2 bg-success/10 text-success rounded-[14px] px-4 py-3 font-sans text-[13px]">
                <i class="ri-checkbox-circle-fill text-[18px]"></i> Your profile has been updated.
            </div>
        @endif

        <div class="flex flex-col gap-2.5">
            <div class="flex flex-col sm:flex-row gap-2.5">
                <x-auth.input label="First Name" name="first_name" wire:model="first_name" placeholder="John" />
                <x-auth.input label="Last Name" name="last_name" wire:model="last_name" placeholder="Dee" />
            </div>

            <x-auth.input label="Phone Number" type="tel" name="phone" wire:model="phone" placeholder="0906 345 6720">
                <x-slot:leading>
                    <i class="ri-phone-line text-[14px]"></i>
                </x-slot:leading>
            </x-auth.input>

            @if ($user->is_agent)
                <x-auth.input label="Telegram" name="telegram" wire:model="telegram" placeholder="@yourhandle or https://t.me/…">
                    <x-slot:leading>
                        <i class="ri-telegram-2-line text-[14px]"></i>
                    </x-slot:leading>
                </x-auth.input>
                <p class="-mt-1 px-2 font-sans text-[12px] text-muted">Shown to buyers on your vendor profile.</p>
            @endif

            {{-- Locked: username + email cannot be changed --}}
            @foreach ([
                ['label' => 'Username', 'icon' => 'ri-at-line', 'value' => '@'.$user->username],
                ['label' => 'Email', 'icon' => 'ri-mail-line', 'value' => $user->email],
            ] as $locked)
                <div class="flex flex-col gap-1.5 w-full">
                    <label class="font-sans text-[14px] text-text">{{ $locked['label'] }}</label>
                    <div class="flex items-center gap-2.5 h-[45px] px-[15px] rounded-[30px] bg-background border-[0.5px] border-muted/30">
                        <i class="{{ $locked['icon'] }} text-[14px] text-muted shrink-0"></i>
                        <span class="flex-1 min-w-0 font-sans text-[13px] text-muted truncate">{{ $locked['value'] }}</span>
                        <i class="ri-lock-2-line text-[14px] text-muted shrink-0"></i>
                    </div>
                </div>
            @endforeach

            <div class="flex flex-col gap-1.5 w-full">
                <label class="font-sans text-[14px] text-text">Bio</label>
                <textarea wire:model="biography" rows="3" maxlength="300" placeholder="Tell us a little about yourself"
                          class="w-full rounded-[20px] border-[0.5px] border-primary px-4 py-3 bg-transparent outline-none font-sans text-[13px] text-text placeholder:text-muted placeholder:text-[12px] focus:ring-1 focus:ring-primary resize-none"></textarea>
                @error('biography')<span class="font-sans text-[12px] text-danger px-2">{{ $message }}</span>@enderror
            </div>
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="w-full h-[48px] rounded-[30px] bg-primary text-surface font-sans text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Save Changes</span>
            <span wire:loading wire:target="save" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
        </button>
    </div>
</x-app.settings-shell>
