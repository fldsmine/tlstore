<div>
    <x-backoffice.page title="Settings" subtitle="Platform configuration">
        <x-backoffice.settings-tabs active="services" />

        @php $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">
            <x-backoffice.card title="Payment routing">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Choose which gateway collects card charges for each flow. A provider needs its secret key set below before it can be selected. Switching only affects new charges — payments already in progress settle on the provider that started them.</p>
                @php
                    $providers = collect(\App\Enums\PaymentProvider::cases());
                    $providerReady = fn (\App\Enums\PaymentProvider $p): bool => $p->value === 'manual'
                        ? filled(config('settings.payment.manual_bank_account_number'))
                        : filled(config("services.{$p->value}.secret_key"));
                @endphp
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Website renewal</label>
                        <select wire:model="renewalProvider" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        @error('renewalProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Bill / wallet top-up</label>
                        <select wire:model="billProvider" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        @error('billProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Cloud storage purchase</label>
                        <select wire:model="storageProvider" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        @error('storageProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Package purchase</label>
                        <select wire:model="packageProvider" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        @error('packageProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Revenue Authentication code</label>
                        <select wire:model="specialCouponProvider" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        <span class="font-sans text-[11px] text-muted">Collects card payments made on the Revenue Authentication page (a special storage code for the buyer's tier, which also authorizes one N-Cloud withdrawal).</span>
                        @error('specialCouponProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Loan application fee</label>
                        <select wire:model="loanApplicationFeeProvider" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        <span class="font-sans text-[11px] text-muted">Collects the non-refundable fee before a loan is submitted for staff review.</span>
                        @error('loanApplicationFeeProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Manual bank transfer details">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Shown to buyers when 'Manual bank transfer' is selected as a payment provider for a flow. After paying, buyers click "I have paid" and are taken to the revenue support Telegram to share proof.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Account number</label>
                        <input type="text" wire:model="manualBankAccountNumber" class="{{ $input }} w-full">
                        @error('manualBankAccountNumber') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Bank name</label>
                        <input type="text" wire:model="manualBankName" class="{{ $input }} w-full">
                        @error('manualBankName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Account name</label>
                        <input type="text" wire:model="manualBankAccountName" class="{{ $input }} w-full">
                        @error('manualBankAccountName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">SWIFT code</label>
                        <input type="text" wire:model="manualBankSwiftCode" class="{{ $input }} w-full">
                        @error('manualBankSwiftCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Active payment manager">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Which payment manager the backoffice works with day to day. The sidebar shows ONLY this manager's wallet pages — balance, history, send money and verify payout — plus the shared Payment references ledger. Send money is offered to super admins only.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Manager</label>
                        <select wire:model.live="activePaymentManager" class="{{ $input }} w-full">
                            @foreach ($providers as $p)
                                <option value="{{ $p->value }}" @disabled(! $providerReady($p))>{{ $p->label() }}@unless ($providerReady($p)) — no key set @endunless</option>
                            @endforeach
                        </select>
                        @error('activePaymentManager') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span class="font-sans text-muted text-[12px]">What the sidebar will offer</span>
                        <div class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-3 flex flex-col gap-1.5">
                            @php
                                $managers = ['korapay' => 'Korapay', 'paystack' => 'Paystack', 'flutterwave' => 'Flutterwave', 'bachs' => 'Bachs'];
                                $activeLabel = $managers[strtolower($activePaymentManager)] ?? null;
                            @endphp
                            @if ($activeLabel)
                                @foreach (['balance', 'history', 'send money', 'verify payout'] as $page)
                                    <span class="font-sans text-text text-[12px] flex items-center gap-1.5"><i class="ri-check-line text-success"></i> {{ $activeLabel }} {{ $page }}</span>
                                @endforeach
                            @else
                                <span class="font-sans text-[12px] text-[#d79937] flex items-start gap-1.5"><i class="ri-alert-line mt-0.5"></i> {{ $providers->firstWhere('value', $activePaymentManager)?->label() ?? $activePaymentManager }} is not a payment manager this backoffice knows — pick one of the managers above.</span>
                            @endif
                            <span class="font-sans text-text text-[12px] flex items-center gap-1.5"><i class="ri-check-line text-success"></i> Payment references</span>
                        </div>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Flutterwave">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Base URL + API keys for Flutterwave (bank-account verification). Leave blank to fall back to the server's environment values.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="font-sans text-muted text-[12px]">Base URL</label>
                        <input type="text" wire:model="flutterwaveBaseUrl" autocomplete="off" spellcheck="false" placeholder="https://api.flutterwave.com/v3" class="{{ $input }} w-full">
                        @error('flutterwaveBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Public key</label>
                        <input type="text" wire:model="flutterwavePublicKey" autocomplete="off" spellcheck="false" placeholder="FLWPUBK-…" class="{{ $input }} w-full">
                        @error('flutterwavePublicKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Secret key</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="flutterwaveSecretKey" autocomplete="off" spellcheck="false" placeholder="FLWSECK-…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide secret key' : 'Show secret key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('flutterwaveSecretKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Charge currency</label>
                        <input type="text" wire:model="flutterwaveCurrency" autocomplete="off" spellcheck="false" maxlength="3" placeholder="NGN" class="{{ $input }} w-full uppercase">
                        @error('flutterwaveCurrency') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Webhook secret hash <span class="text-muted/60">(for collections)</span></label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="flutterwaveSecretHash" autocomplete="off" spellcheck="false" placeholder="from Flutterwave dashboard" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide hash' : 'Show hash'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('flutterwaveSecretHash') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Paystack">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Base URL + API keys for Paystack (checkout charges, bank-account verification and backoffice payouts). Leave blank to fall back to the server's environment values.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="font-sans text-muted text-[12px]">Base URL</label>
                        <input type="text" wire:model="paystackBaseUrl" autocomplete="off" spellcheck="false" placeholder="https://api.paystack.co" class="{{ $input }} w-full">
                        @error('paystackBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Public key</label>
                        <input type="text" wire:model="paystackPublicKey" autocomplete="off" spellcheck="false" placeholder="pk_live_…" class="{{ $input }} w-full">
                        @error('paystackPublicKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Secret key</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="paystackSecretKey" autocomplete="off" spellcheck="false" placeholder="sk_live_…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide secret key' : 'Show secret key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('paystackSecretKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Charge currency</label>
                        <input type="text" wire:model="paystackCurrency" autocomplete="off" spellcheck="false" maxlength="3" placeholder="NGN" class="{{ $input }} w-full uppercase">
                        @error('paystackCurrency') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Korapay">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Checkout keys for Korapay (bills-wallet top-ups). Leave blank to fall back to the server's environment values.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Base URL</label>
                        <input type="text" wire:model="korapayBaseUrl" autocomplete="off" spellcheck="false" placeholder="https://api.korapay.com" class="{{ $input }} w-full">
                        @error('korapayBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Charge currency</label>
                        <input type="text" wire:model="korapayCurrency" autocomplete="off" spellcheck="false" maxlength="3" placeholder="NGN" class="{{ $input }} w-full uppercase">
                        @error('korapayCurrency') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Public key</label>
                        <input type="text" wire:model="korapayPublicKey" autocomplete="off" spellcheck="false" placeholder="pk_…" class="{{ $input }} w-full">
                        @error('korapayPublicKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Secret key</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="korapaySecretKey" autocomplete="off" spellcheck="false" placeholder="sk_…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide secret key' : 'Show secret key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('korapaySecretKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Bachs">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Base URL + API keys for Bachs (hosted checkout charges and backoffice payouts). Use <span class="text-text">https://sandbox-api.bachs.io</span> with an <span class="text-text">sk_sandbox_…</span> key while testing. Leave blank to fall back to the server's environment values.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="font-sans text-muted text-[12px]">Base URL</label>
                        <input type="text" wire:model="bachsBaseUrl" autocomplete="off" spellcheck="false" placeholder="https://api.bachs.io" class="{{ $input }} w-full">
                        @error('bachsBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Public key</label>
                        <input type="text" wire:model="bachsPublicKey" autocomplete="off" spellcheck="false" placeholder="pk_live_…" class="{{ $input }} w-full">
                        @error('bachsPublicKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Secret key</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="bachsSecretKey" autocomplete="off" spellcheck="false" placeholder="sk_live_…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide secret key' : 'Show secret key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('bachsSecretKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Charge currency</label>
                        <input type="text" wire:model="bachsCurrency" autocomplete="off" spellcheck="false" maxlength="3" placeholder="NGN" class="{{ $input }} w-full uppercase">
                        @error('bachsCurrency') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Webhook signing secret</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="bachsWebhookSecret" autocomplete="off" spellcheck="false" placeholder="whsec_…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide webhook secret' : 'Show webhook secret'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('bachsWebhookSecret') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <p class="font-sans text-[11px] text-muted leading-relaxed">From the Bachs Developer portal → Webhooks, on the endpoint pointing at <span class="text-text">/webhooks/payments/bachs</span>. Signatures are HMAC-SHA256 over the raw body. <span class="text-text">With this blank, Bachs webhooks are refused</span> — charges still settle from the redirect callback and the requery sweep, both of which re-verify server-side.</p>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="VTpass">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Base URL + API keys for VTpass (airtime, data, TV, electricity &amp; education). Leave blank to fall back to the server's environment values.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="font-sans text-muted text-[12px]">Base URL <span class="text-muted/70">(sandbox: https://sandbox.vtpass.com · live: https://vtpass.com)</span></label>
                        <input type="text" wire:model="vtpassBaseUrl" autocomplete="off" spellcheck="false" placeholder="https://sandbox.vtpass.com" class="{{ $input }} w-full">
                        @error('vtpassBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="font-sans text-muted text-[12px]">API key</label>
                        <input type="text" wire:model="vtpassApiKey" autocomplete="off" spellcheck="false" placeholder="VTpass API key" class="{{ $input }} w-full">
                        @error('vtpassApiKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Public key (GET calls)</label>
                        <input type="text" wire:model="vtpassPublicKey" autocomplete="off" spellcheck="false" placeholder="PK_…" class="{{ $input }} w-full">
                        @error('vtpassPublicKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">Secret key (POST calls)</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="vtpassSecretKey" autocomplete="off" spellcheck="false" placeholder="SK_…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide secret key' : 'Show secret key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('vtpassSecretKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="ElevenLabs">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Realistic text-to-speech voices for Nextel Cloud sponsor calls. Leave the API key blank to fall back to the browser's own voice.</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5 sm:col-span-2">
                        <label class="font-sans text-muted text-[12px]">Base URL</label>
                        <input type="text" wire:model="elevenlabsBaseUrl" autocomplete="off" spellcheck="false" placeholder="https://api.elevenlabs.io" class="{{ $input }} w-full">
                        @error('elevenlabsBaseUrl') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5 sm:col-span-2" x-data="{ show: false }">
                        <label class="font-sans text-muted text-[12px]">API key</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="elevenlabsApiKey" autocomplete="off" spellcheck="false" placeholder="sk_…" class="{{ $input }} w-full pr-11">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide API key' : 'Show API key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                        </div>
                        @error('elevenlabsApiKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Mail">
                <div x-data="{ driver: $wire.entangle('mailDriver') }">
                    <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Choose how outgoing email is sent. Leave provider fields blank to fall back to the server's environment values.</p>

                    <div class="inline-flex items-center gap-1 bg-background rounded-[12px] p-1 mb-4">
                        <button type="button" @click="driver = 'smtp'" :class="driver === 'smtp' ? 'bg-surface text-primary shadow-sm' : 'text-muted'" class="h-9 px-4 rounded-[9px] font-sans text-[13px] font-medium transition">SMTP</button>
                        <button type="button" @click="driver = 'resend'" :class="driver === 'resend' ? 'bg-surface text-primary shadow-sm' : 'text-muted'" class="h-9 px-4 rounded-[9px] font-sans text-[13px] font-medium transition">Resend</button>
                    </div>

                    <div x-show="driver === 'smtp'" x-cloak class="grid sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">SMTP host</label>
                            <input type="text" wire:model="mailHost" autocomplete="off" spellcheck="false" placeholder="smtp.mailgun.org" class="{{ $input }} w-full">
                            @error('mailHost') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Port</label>
                            <input type="text" inputmode="numeric" wire:model="mailPort" autocomplete="off" placeholder="587" class="{{ $input }} w-full">
                            @error('mailPort') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Username</label>
                            <input type="text" wire:model="mailUsername" autocomplete="off" spellcheck="false" class="{{ $input }} w-full">
                            @error('mailUsername') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                            <label class="font-sans text-muted text-[12px]">Password</label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" wire:model="mailPassword" autocomplete="off" spellcheck="false" class="{{ $input }} w-full pr-11">
                                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide password' : 'Show password'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                            </div>
                            @error('mailPassword') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div x-show="driver === 'resend'" x-cloak class="grid gap-4">
                        <div class="flex flex-col gap-1.5" x-data="{ show: false }">
                            <label class="font-sans text-muted text-[12px]">Resend API key</label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" wire:model="resendKey" autocomplete="off" spellcheck="false" placeholder="re_..." class="{{ $input }} w-full pr-11">
                                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text" :aria-label="show ? 'Hide key' : 'Show key'"><i :class="show ? 'ri-eye-off-line' : 'ri-eye-line'" class="text-[16px]"></i></button>
                            </div>
                            @error('resendKey') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            <p class="font-sans text-muted text-[11px]">Get this from your Resend dashboard → API Keys.</p>
                        </div>
                    </div>

                    {{-- Sender identity — applies to both drivers --}}
                    <div class="grid sm:grid-cols-2 gap-4 mt-4 pt-4 border-t border-background">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">From address</label>
                            <input type="email" wire:model="mailFromAddress" autocomplete="off" spellcheck="false" placeholder="hello@nextelconnect.com" class="{{ $input }} w-full">
                            @error('mailFromAddress') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">From name</label>
                            <input type="text" wire:model="mailFromName" autocomplete="off" spellcheck="false" placeholder="Nextel Connect" class="{{ $input }} w-full">
                            @error('mailFromName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </x-backoffice.card>

            <div class="flex justify-end">
                <button type="submit" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px] disabled:opacity-60" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
