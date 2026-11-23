<div class="flex flex-col gap-4">
    @if ($account && ! ($isVendor && $editing))
        <div class="flex flex-col gap-5">
            <p class="font-sans text-muted text-[13px]">{{ $copy['saved_hint'] }}</p>

            <div class="relative overflow-hidden rounded-[22px] p-5 text-white shadow-[0_14px_34px_-12px_rgba(24,68,62,0.5)]"
                 style="background: radial-gradient(125% 130% at 88% 12%, #2f7468 0%, #18443E 58%); background-color:#18443E;">
                <span class="absolute -right-8 -top-10 size-36 rounded-full bg-white/[0.06]"></span>
                <span class="absolute -right-2 top-16 size-24 rounded-full bg-white/[0.05]"></span>

                @php $acctVerified = $account->status === \App\Enums\BankAccountStatus::VERIFIED; @endphp
                <div class="relative flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="size-9 rounded-full bg-white/12 flex items-center justify-center shrink-0"><i class="{{ $account->bank?->isMobileMoney() ? 'ri-smartphone-line' : 'ri-bank-line' }} text-[18px] text-secondary"></i></span>
                        <span class="font-sans text-white/90 text-[14px] truncate">{{ $account->bank->name }}</span>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1 bg-white/15 rounded-full px-2.5 py-1 font-sans text-[11px]"><i class="{{ $acctVerified ? 'ri-verified-badge-fill' : 'ri-checkbox-circle-line' }} text-secondary text-[12px]"></i> {{ $acctVerified ? 'Verified' : 'Self-confirmed' }}</span>
                </div>

                <p class="relative font-heading font-semibold text-[26px] tracking-[4px] mt-7 mb-6">{{ $account->masked_number }}</p>

                <div class="relative flex items-end justify-between gap-3">
                    <div class="flex flex-col min-w-0">
                        <span class="font-sans text-white/55 text-[10px] uppercase tracking-wide">Account name</span>
                        <span class="font-sans font-medium text-white text-[15px] truncate">{{ $account->account_name }}</span>
                    </div>
                    <img src="{{ asset('images/public/logo-light.png') }}" alt="" class="h-4 w-auto opacity-70 shrink-0">
                </div>
            </div>

            @if ($isVendor)
                <button type="button" wire:click="edit"
                        class="w-full h-[50px] rounded-[16px] border border-primary/30 text-primary font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:bg-primary/5 transition">
                    <i class="ri-edit-2-line text-[16px]"></i> {{ $copy['edit_label'] }}
                </button>
            @else
                <div class="flex items-start gap-3 bg-secondary/20 rounded-[16px] px-4 py-3.5">
                    <i class="ri-shield-check-line text-primary text-[18px] shrink-0 mt-0.5"></i>
                    <p class="font-sans text-muted text-[12px] leading-relaxed">
                        For your security you can only have one payout account. To change it,
                        <a href="{{ route('contact') }}" class="text-primary font-semibold">contact support</a>.
                    </p>
                </div>
            @endif
        </div>
    @else
        <div class="bg-surface rounded-[24px] p-5 lg:p-7 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex flex-col gap-4">
            <div class="flex items-start gap-3 bg-secondary/20 rounded-[16px] px-4 py-3.5">
                <i class="{{ $isNigerian ? 'ri-bank-line' : 'ri-smartphone-line' }} text-primary text-[18px] shrink-0 mt-0.5"></i>
                <p class="font-sans text-muted text-[12px] leading-relaxed">{{ $isNigerian ? $copy['add_hint_ng'] : $copy['add_hint_foreign'] }}</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="font-sans text-[14px] text-text">{{ $isNigerian ? 'Bank' : 'Mobile-money provider' }}</label>
                <div class="relative">
                    <select wire:model.live="bankId"
                            class="w-full h-[48px] rounded-[16px] border-[0.5px] border-primary bg-transparent pl-4 pr-10 outline-none appearance-none font-sans text-[14px] text-text focus:ring-1 focus:ring-primary">
                        <option value="">{{ $isNigerian ? 'Select your bank' : 'Select your provider' }}</option>
                        @foreach ($banks as $bank)
                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    <i class="ri-arrow-down-s-line text-[20px] text-muted absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
                @error('bankId')<span class="font-sans text-[12px] text-danger px-1">{{ $message }}</span>@enderror
            </div>

            {{-- NG: account number / foreign: mobile-money number --}}
            <div class="flex flex-col gap-1.5">
                <label class="font-sans text-[14px] text-text">{{ $isNigerian ? 'Account Number' : 'Mobile money number' }}</label>
                <div class="flex items-center gap-2.5 h-[48px] px-4 rounded-[16px] border-[0.5px] border-primary focus-within:ring-1 focus-within:ring-primary">
                    <i class="{{ $isNigerian ? 'ri-hashtag' : 'ri-smartphone-line' }} text-[15px] text-muted shrink-0"></i>
                    <input type="text" wire:model.live.debounce.500ms="accountNumber" inputmode="{{ $isNigerian ? 'numeric' : 'tel' }}" maxlength="{{ $isNigerian ? '10' : '20' }}"
                           placeholder="{{ $isNigerian ? '10-digit account number' : 'e.g. 024 123 4567' }}"
                           class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[14px] text-text placeholder:text-muted placeholder:text-[13px] tracking-wide">
                    @if ($isNigerian)
                        <span wire:loading wire:target="accountNumber,verifyAccount,bankId" class="text-muted shrink-0"><i class="ri-loader-4-line animate-spin text-[16px]"></i></span>
                        @if ($verified)<i wire:loading.remove wire:target="accountNumber,verifyAccount,bankId" class="ri-checkbox-circle-fill text-success text-[17px] shrink-0"></i>@endif
                    @endif
                </div>
                @error('accountNumber')<span class="font-sans text-[12px] text-danger px-1">{{ $message }}</span>@enderror
            </div>

            @if ($isNigerian)
                @if ($verified && $accountName)
                    <div class="flex items-center gap-2.5 bg-success/10 rounded-[16px] px-4 py-3.5">
                        <i class="ri-shield-check-fill text-success text-[20px] shrink-0"></i>
                        <div class="flex flex-col min-w-0">
                            <span class="font-sans text-muted text-[11px]">Account name</span>
                            <span class="font-sans font-semibold text-text text-[15px] truncate">{{ $accountName }}</span>
                        </div>
                    </div>
                @elseif ($verifyError)
                    <div class="flex items-center gap-2.5 bg-danger/10 rounded-[16px] px-4 py-3.5">
                        <i class="ri-error-warning-fill text-danger text-[18px] shrink-0"></i>
                        <span class="font-sans text-danger text-[13px]">{{ $verifyError }}</span>
                    </div>
                @endif
            @else
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-text">Account name</label>
                    <div class="flex items-center gap-2.5 h-[48px] px-4 rounded-[16px] border-[0.5px] border-primary focus-within:ring-1 focus-within:ring-primary">
                        <i class="ri-user-line text-[15px] text-muted shrink-0"></i>
                        <input type="text" wire:model.live="accountName" maxlength="120" placeholder="Name registered on the wallet"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[14px] text-text placeholder:text-muted placeholder:text-[13px]">
                    </div>
                    @error('accountName')<span class="font-sans text-[12px] text-danger px-1">{{ $message }}</span>@enderror
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model.live="confirmed" class="size-4 mt-0.5 rounded border-muted/40 accent-[#18443E] shrink-0">
                    <span class="font-sans text-muted text-[12px] leading-relaxed">I confirm the provider, number and name above are correct. Payments to a wrong wallet can’t be reversed.</span>
                </label>
                @error('confirmed')<span class="font-sans text-[12px] text-danger px-1">{{ $message }}</span>@enderror
            @endif

            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    @disabled($isNigerian ? ! $verified : ! $confirmed)
                    class="mt-1 w-full h-[50px] rounded-[30px] bg-primary text-surface font-sans text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="save"><i class="ri-check-line"></i> Save Account</span>
                <span wire:loading wire:target="save" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
            </button>

            @if ($editing)
                <button type="button" wire:click="cancelEdit"
                        class="w-full h-[46px] rounded-[30px] border border-muted/25 text-muted font-sans text-[13px] hover:bg-background transition">
                    Cancel
                </button>
            @endif
        </div>
    @endif
</div>
