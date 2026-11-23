<div>
    <x-backoffice.page title="Settings" subtitle="Platform configuration">
        <x-backoffice.settings-tabs active="general" />

        @php $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">
            <x-backoffice.card title="Application">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">App name</label>
                        <input type="text" wire:model="appName" class="{{ $input }}">
                        @error('appName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    @foreach ([['enableRegistration','Allow new registrations'],['enableEmailVerification','Require email verification'],['enableMaintenanceMode','Maintenance mode (public site offline)']] as [$field,$label])
                        <x-backoffice.toggle wire:model="{{ $field }}" :label="$label" />
                    @endforeach
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Referrals">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Referral link base URL</label>
                        <input type="url" wire:model="referralBaseUrl" placeholder="{{ config('app.url') }}" class="{{ $input }}">
                        <span class="font-sans text-[11px] text-muted">Base domain used to build each user's referral link. Leave blank to use the app URL.</span>
                        @error('referralBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <x-backoffice.toggle wire:model="referralPayOnUpgrade" label="Pay referral bonus on upgrade" description="Also pay the upline the new package's referral bonus (direct + indirect) when a downline upgrades — not only on first activation. The upline must hold a package of the same or higher tier." />
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Contact">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Support email</label>
                        <input type="email" wire:model="contactEmail" class="{{ $input }}">
                        @error('contactEmail') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Phone</label>
                            <input type="text" wire:model="contactPhone" class="{{ $input }}">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Telegram — Channel (social link)</label>
                            <input type="url" wire:model="contactTelegram" placeholder="https://t.me/nextelconnect" class="{{ $input }}">
                            @error('contactTelegram') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Telegram — General Support <span class="text-muted/60">(Contact Us)</span></label>
                            <input type="url" wire:model="contactTelegramGeneral" placeholder="Defaults to the channel link" class="{{ $input }}">
                            @error('contactTelegramGeneral') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Telegram — Activation Support <span class="text-muted/60">(activation + chat)</span></label>
                            <input type="url" wire:model="contactTelegramActivation" placeholder="Defaults to the channel link" class="{{ $input }}">
                            @error('contactTelegramActivation') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Telegram — Revenue Code Support <span class="text-muted/60">(revenue codes + payouts)</span></label>
                            <input type="text" wire:model="contactTelegramRevenue" placeholder="https://t.me/nextelSpecialSupport" class="{{ $input }}">
                            <span class="font-sans text-[11px] text-muted">Shown on the Revenue Authentication page as the fallback when a card payment cannot be issued automatically, and quoted to users who paid without receiving a code. Accepts a full link or just the handle. Leave blank to keep the built-in desk.</span>
                            @error('contactTelegramRevenue') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Address</label>
                            <input type="text" wire:model="contactAddress" class="{{ $input }}">
                        </div>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Social Links">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach ([['facebook','Facebook'],['instagram','Instagram'],['x','X (Twitter)'],['tiktok','TikTok'],['website','Website']] as [$field,$label])
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">{{ $label }}</label>
                            <input type="text" wire:model="{{ $field }}" class="{{ $input }}">
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <div class="flex justify-end">
                <button type="submit" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px]">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
