<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Debit Card'" />

        <div class="flex flex-col gap-[22px] w-full lg:max-w-[480px] lg:mx-auto">

            {{-- Masked here; full details behind PIN modal. --}}
            <div class="relative overflow-hidden rounded-[22px] p-5 aspect-[1.586/1] flex flex-col justify-between text-surface shadow-[0_10px_30px_rgba(24,68,62,0.25)]"
                 style="background: radial-gradient(130% 130% at 100% 0%, #2f7468 0%, #18443E 55%); background-color: #18443E;">
                <div class="pointer-events-none absolute -right-12 -top-12 w-[200px] h-[200px] rounded-full bg-secondary/25 blur-2xl"></div>
                <div class="pointer-events-none absolute right-6 bottom-6 w-[120px] h-[120px] rounded-full bg-secondary/10 blur-xl"></div>

                <div class="relative flex items-start justify-between">
                    <div class="flex flex-col leading-none">
                        <span class="font-heading font-semibold text-surface text-[16px] tracking-wide">NEXTEL</span>
                        <span class="font-sans text-surface/70 text-[8px] tracking-[0.3em]">CONNECT</span>
                    </div>
                    <i class="ri-sim-card-2-line text-secondary text-[30px]"></i>
                </div>

                <div class="relative flex flex-col gap-1">
                    <p class="font-sans text-surface/60 text-[10px]">Card Number</p>
                    <p class="font-heading font-medium text-surface text-[19px] tracking-[0.12em]">
                        {{ $card ? $card->masked_number : '•••• •••• •••• ••••' }}
                    </p>
                </div>

                <div class="relative flex items-end justify-between">
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <p class="font-sans text-surface/60 text-[10px]">Card Holder</p>
                        <p class="font-sans text-surface text-[13px] truncate">{{ $holder }}</p>
                    </div>
                    <div class="flex items-end gap-4 shrink-0">
                        <div class="flex flex-col gap-0.5 items-center">
                            <p class="font-sans text-surface/60 text-[10px]">Expires</p>
                            <p class="font-sans text-surface text-[13px]">{{ $card?->expiry ?? '--/--' }}</p>
                        </div>
                        <span class="font-heading font-semibold text-secondary text-[14px] italic">{{ $card->brand ?? 'Verve' }}</span>
                    </div>
                </div>
            </div>

            @if (! $card)
                <div class="bg-surface rounded-[20px] p-5 flex flex-col items-center text-center gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <span class="size-14 rounded-full bg-secondary/25 flex items-center justify-center text-primary"><i class="ri-bank-card-line text-[28px]"></i></span>
                    <p class="font-heading font-medium text-text text-[17px]">Request your virtual card</p>
                    <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[300px]">Get an instant Nextel virtual card to spend your earnings. You&rsquo;ll set a card PIN to keep your details safe.</p>
                    <button type="button" wire:click="startRequest"
                            class="mt-1 h-[50px] px-6 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition">
                        <i class="ri-add-line text-[18px]"></i> Request card
                    </button>
                </div>
            @else
                <div class="flex flex-col gap-2.5">
                    <button type="button" wire:click="startView"
                            class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition">
                        <i class="ri-eye-line text-[18px]"></i> View card details
                    </button>
                    <button type="button" wire:click="startReset"
                            class="h-[52px] rounded-[30px] bg-surface border border-muted/25 text-text font-sans text-[15px] flex items-center justify-center gap-2 hover:bg-background transition">
                        <i class="ri-lock-password-line text-[18px]"></i> Reset card PIN
                    </button>
                </div>
                <p class="font-sans text-muted text-[12px] text-center leading-relaxed">View your full card details any time with your card PIN. Forgot it? Reset it with your transaction PIN.</p>
            @endif
        </div>
    </div>

    @if ($requesting)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4" x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.closeRequest()"></div>
            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <button type="button" wire:click="closeRequest" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>

                @if ($requestStep === 'details')
                    <div class="flex flex-col gap-1 pr-8">
                        <p class="font-heading font-medium text-text text-[19px]">Request your card</p>
                        <p class="font-sans text-muted text-[13px]">Confirm your name and set a 4-digit card PIN.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-text text-[13px] font-medium">Name on card</label>
                        <input type="text" wire:model="holderName" maxlength="60"
                               class="w-full h-[48px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-sans text-[14px] text-text uppercase focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('holderName')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-text text-[13px] font-medium">Card PIN</label>
                        <input type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="pin" placeholder="••••"
                               class="w-full h-[50px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-heading text-[18px] tracking-[0.4em] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('pin')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-text text-[13px] font-medium">Confirm PIN</label>
                        <input type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="pinConfirm" placeholder="••••"
                               class="w-full h-[50px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-heading text-[18px] tracking-[0.4em] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('pinConfirm')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <button type="button" wire:click="requestNext" class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 mt-1">Continue <i class="ri-arrow-right-line"></i></button>
                @else
                    <div class="flex flex-col gap-1 pr-8">
                        <p class="font-heading font-medium text-text text-[19px]">Confirm it&rsquo;s you</p>
                        <p class="font-sans text-muted text-[13px]">Enter your transaction PIN to issue the card.</p>
                    </div>
                    <div class="flex flex-col gap-2 items-center py-2">
                        <x-app.pin-input target="requestTxnPin" :autofocus="true" />
                        @error('requestTxnPin')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button type="button" wire:click="$set('requestStep', 'details')" class="flex-1 h-[50px] rounded-[30px] border border-muted/30 text-text font-sans text-[14px]">Back</button>
                        <button type="button" wire:click="createCard" wire:loading.attr="disabled" wire:target="createCard"
                                class="flex-1 h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="createCard">Create card</span>
                            <span wire:loading wire:target="createCard"><i class="ri-loader-4-line animate-spin"></i></span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($viewing)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4" x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.closeView()"></div>
            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <button type="button" wire:click="closeView" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>

                @if (! $revealed)
                    <div class="flex flex-col gap-1 pr-8">
                        <p class="font-heading font-medium text-text text-[19px]">Enter your card PIN</p>
                        <p class="font-sans text-muted text-[13px]">Confirm it&rsquo;s you to reveal the full card details.</p>
                    </div>
                    <div class="flex flex-col gap-2 items-center py-2">
                        <x-app.pin-input target="viewPin" :autofocus="true" />
                        @error('viewPin')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <button type="button" wire:click="revealCard" wire:loading.attr="disabled" wire:target="revealCard"
                            class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 disabled:opacity-60">
                        <span wire:loading.remove wire:target="revealCard"><i class="ri-eye-line"></i> Reveal details</span>
                        <span wire:loading wire:target="revealCard"><i class="ri-loader-4-line animate-spin"></i></span>
                    </button>
                @else
                    <div class="flex flex-col gap-1 pr-8">
                        <p class="font-heading font-medium text-text text-[19px]">Card details</p>
                        <p class="font-sans text-muted text-[13px]">Tap any field to copy. Keep these private.</p>
                    </div>

                    @php
                        $fields = [
                            ['label' => 'Card Number', 'value' => $card->formatted_number, 'copy' => $card->card_number],
                            ['label' => 'Expiry', 'value' => $card->expiry, 'copy' => $card->expiry],
                            ['label' => 'CVV', 'value' => $card->cvv, 'copy' => $card->cvv],
                            ['label' => 'Card Holder', 'value' => $holder, 'copy' => $holder],
                        ];
                    @endphp
                    <div class="flex flex-col gap-2.5">
                        @foreach ($fields as $field)
                            <div x-data="{ copied: false }"
                                 @click="nextelCopy('{{ $field['copy'] }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1500) })"
                                 class="bg-surface rounded-[14px] px-4 py-3 flex items-center justify-between gap-3 cursor-pointer hover:bg-surface/70 transition">
                                <div class="min-w-0 flex flex-col gap-0.5">
                                    <p class="font-sans text-muted text-[11px]">{{ $field['label'] }}</p>
                                    <p class="font-heading font-medium text-text text-[15px] tracking-wide truncate">{{ $field['value'] }}</p>
                                </div>
                                <span class="shrink-0 flex items-center gap-1 font-sans text-[12px]" :class="copied ? 'text-success' : 'text-primary'">
                                    <i :class="copied ? 'ri-check-line' : 'ri-file-copy-line'" class="text-[15px]"></i>
                                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" wire:click="closeView" class="h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] mt-1">Done</button>
                @endif
            </div>
        </div>
    @endif

    @if ($resettingOpen)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4" x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.closeReset()"></div>
            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <button type="button" wire:click="closeReset" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>

                @if ($resetStep === 'details')
                    <div class="flex flex-col gap-1 pr-8">
                        <p class="font-heading font-medium text-text text-[19px]">Reset card PIN</p>
                        <p class="font-sans text-muted text-[13px]">Set a new 4-digit card PIN.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-text text-[13px] font-medium">New card PIN</label>
                        <input type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="newPin" placeholder="••••"
                               class="w-full h-[50px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-heading text-[18px] tracking-[0.4em] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('newPin')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-text text-[13px] font-medium">Confirm new PIN</label>
                        <input type="password" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="newPinConfirm" placeholder="••••"
                               class="w-full h-[50px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-heading text-[18px] tracking-[0.4em] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('newPinConfirm')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <button type="button" wire:click="resetNext"
                            class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 mt-1">Continue <i class="ri-arrow-right-line"></i></button>
                @else
                    <div class="flex flex-col gap-1 pr-8">
                        <p class="font-heading font-medium text-text text-[19px]">Confirm it&rsquo;s you</p>
                        <p class="font-sans text-muted text-[13px]">Enter your transaction PIN to reset the card PIN.</p>
                    </div>
                    <div class="flex flex-col gap-2 items-center py-2">
                        <x-app.pin-input target="resetTxnPin" :autofocus="true" />
                        @error('resetTxnPin')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex items-center gap-2.5">
                        <button type="button" wire:click="$set('resetStep', 'details')" class="flex-1 h-[50px] rounded-[30px] border border-muted/30 text-text font-sans text-[14px]">Back</button>
                        <button type="button" wire:click="submitReset" wire:loading.attr="disabled" wire:target="submitReset"
                                class="flex-1 h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submitReset">Reset PIN</span>
                            <span wire:loading wire:target="submitReset"><i class="ri-loader-4-line animate-spin"></i></span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <x-app.bottom-nav />
</div>
