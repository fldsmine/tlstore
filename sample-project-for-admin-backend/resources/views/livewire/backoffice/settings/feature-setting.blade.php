<div>
    <x-backoffice.page title="Settings" subtitle="Platform configuration">
        <x-backoffice.settings-tabs active="features" />

        @php $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">
            <x-backoffice.card title="Additional Minutes (share purchases)">
                <div class="flex flex-col gap-4">
                    <x-backoffice.toggle wire:model="enableMinutes" label="Additional Minutes enabled" description="When off, users see the title + message below instead of the buy flow." />
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Disabled title</label>
                        <input type="text" wire:model="minutesDisabledTitle" maxlength="120" class="{{ $input }}">
                        @error('minutesDisabledTitle') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Disabled message (shown to users)</label>
                        <textarea wire:model="minutesDisabledMessage" rows="4" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                        @error('minutesDisabledMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Pay Later">
                <div class="flex flex-col gap-4">
                    <x-backoffice.toggle wire:model="enablePaylater" label="Pay Later enabled" />
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Disabled title</label>
                            <input type="text" wire:model="paylaterDisabledTitle" maxlength="120" class="{{ $input }}">
                            @error('paylaterDisabledTitle') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Disabled message</label>
                            <textarea wire:model="paylaterDisabledMessage" rows="2" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                            @error('paylaterDisabledMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Audience</label>
                            <select wire:model="paylaterAudience" class="{{ $input }}">
                                <option value="everyone">Everyone</option>
                                <option value="subset">Subset</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Subset limit</label>
                            <input type="number" wire:model="paylaterLimit" class="{{ $input }}">
                            @error('paylaterLimit') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Repayment days</label>
                            <input type="number" wire:model="paylaterRepaymentDays" class="{{ $input }}">
                            @error('paylaterRepaymentDays') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Loan">
                <div class="flex flex-col gap-4">
                    <x-backoffice.toggle wire:model="enableLoan" label="Loan enabled" />
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Disabled title</label>
                            <input type="text" wire:model="loanDisabledTitle" maxlength="120" class="{{ $input }}">
                            @error('loanDisabledTitle') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Disabled message</label>
                            <textarea wire:model="loanDisabledMessage" rows="2" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                            @error('loanDisabledMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Min amount ($)</label>
                            <input type="number" min="1" step="any" wire:model="loanMinAmount" class="{{ $input }}">
                            @error('loanMinAmount') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Max amount ($)</label>
                            <input type="number" step="any" wire:model="loanMaxAmount" class="{{ $input }}">
                            @error('loanMaxAmount') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 col-span-2">
                            <label class="font-sans text-muted text-[12px]">Amount presets (USD)</label>
                            <input type="text" maxlength="255" wire:model="loanPresets" placeholder="20, 50, 100, 250, 500" class="{{ $input }}">
                            <span class="font-sans text-muted text-[11px]">Enter comma-separated amounts. Presets outside the current minimum/maximum are hidden from applicants.</span>
                            @error('loanPresets') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Repayment days</label>
                            <input type="number" wire:model="loanRepaymentDays" class="{{ $input }}">
                            @error('loanRepaymentDays') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Service fee (%)</label>
                            <input type="number" step="any" wire:model="loanServiceFeePercent" class="{{ $input }}">
                            @error('loanServiceFeePercent') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="border-t border-muted/10 pt-4 flex flex-col gap-4">
                        <div>
                            <p class="font-sans text-text text-[13px] font-medium">Loan types</p>
                            <p class="font-sans text-muted text-[11px] mt-1">Applicants can only choose enabled types. Existing applications keep their original type.</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach (\App\Enums\LoanType::cases() as $type)
                                <label class="flex items-center gap-2.5 rounded-[12px] bg-background px-3.5 py-3 font-sans text-[13px] text-text">
                                    <input type="checkbox" wire:model="loanTypes.{{ $type->value }}" value="1" class="rounded border-muted/30 text-primary focus:ring-primary">
                                    {{ $type->label() }}
                                </label>
                            @endforeach
                        </div>
                        @error('loanTypes') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="border-t border-muted/10 pt-4 flex flex-col gap-3">
                        <div>
                            <p class="font-sans text-text text-[13px] font-medium">Non-refundable application fee</p>
                            <p class="font-sans text-muted text-[11px] mt-1">Collected before an application is submitted for manual review. Default fixed fee: $1 / ₦1,000.</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Fee calculation</label>
                                <select wire:model.live="loanApplicationFeeMode" class="{{ $input }}">
                                    <option value="fixed">Fixed amount</option>
                                    <option value="percentage">Percentage of loan amount</option>
                                </select>
                                @error('loanApplicationFeeMode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            @if ($loanApplicationFeeMode === 'percentage')
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Percentage of principal (%)</label>
                                    <input type="number" min="0.0001" max="100" step="0.0001" wire:model="loanApplicationFeePercentage" class="{{ $input }}">
                                    @error('loanApplicationFeePercentage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                            @endif
                        </div>
                        @if ($loanApplicationFeeMode === 'percentage')
                            <p class="font-sans text-muted text-[11px]">Percentage fees are calculated from the requested principal and can only be paid through the active payment channel. Vendor fee codes are disabled in this mode.</p>
                        @else
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Customer fee (USD)</label>
                                    <input type="number" min="0.01" step="0.01" wire:model="loanApplicationFeeUsd" class="{{ $input }}">
                                    @error('loanApplicationFeeUsd') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Customer fee (NGN)</label>
                                    <input type="number" min="0.01" step="0.01" wire:model="loanApplicationFeeNgn" class="{{ $input }}">
                                    @error('loanApplicationFeeNgn') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Vendor price (USD)</label>
                                    <input type="number" min="0" step="0.01" wire:model="loanApplicationFeeAgentUsd" class="{{ $input }}">
                                    @error('loanApplicationFeeAgentUsd') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Vendor price (NGN)</label>
                                    <input type="number" min="0" step="0.01" wire:model="loanApplicationFeeAgentNgn" class="{{ $input }}">
                                    @error('loanApplicationFeeAgentNgn') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <p class="font-sans text-muted text-[11px]">Fixed fees can be paid through the active channel or a vendor fee code.</p>
                        @endif
                    </div>

                    <div class="border-t border-muted/10 pt-4 flex flex-col gap-3">
                        <div>
                            <p class="font-sans text-text text-[13px] font-medium">Guarantor form fee</p>
                            <p class="font-sans text-muted text-[11px] mt-1">One-time NGN fee paid after loan approval to unlock the guarantor form. Payments use the provider selected for loan application fees.</p>
                        </div>

						<div class="grid sm:grid-cols-2 gap-3">
							<div class="flex flex-col gap-1.5">
								<label class="font-sans text-muted text-[12px]">Fee (NGN)</label>
								<input type="number" min="0.01" step="0.01" wire:model="loanGuarantorFormFeeNgn" class="{{ $input }}">
								@error('loanGuarantorFormFeeNgn') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
							</div>

							<div class="flex flex-col gap-1.5">
								<label class="font-sans text-muted text-[12px]">Guarantor Status</label>
								<x-backoffice.toggle wire:model="enableGuarantorForm" label="Enable Guarantor Form" />
							</div>
						</div>
                    </div>

                    <div class="border-t border-muted/10 pt-4 flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Default rejection message</label>
                        <textarea wire:model="loanRejectionReason" rows="3" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                        @error('loanRejectionReason') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Ads (share-to-earn)">
                <div class="flex flex-col gap-4">
                    <x-backoffice.toggle wire:model="enableAds" label="Ads enabled" description="Per-ad reward amounts are set on each ad in the Ads screen." />
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Disabled title</label>
                            <input type="text" wire:model="adsDisabledTitle" maxlength="120" class="{{ $input }}">
                            @error('adsDisabledTitle') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Disabled message</label>
                            <textarea wire:model="adsDisabledMessage" rows="2" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                            @error('adsDisabledMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div x-show="$wire.enableAds" x-cloak class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Sponsored slots</label>
                            <input type="number" step="1" min="1" max="10" wire:model="adsWebsiteSlots" class="{{ $input }}">
                            @error('adsWebsiteSlots') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            <p class="font-sans text-muted text-[11px]">How many ads a user can see and share from My Website.</p>
                        </div>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Rewards (daily target)">
                <div class="flex flex-col gap-4">
                    <x-backoffice.toggle wire:model="enableRewards" label="Rewards enabled" />
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Disabled title</label>
                            <input type="text" wire:model="rewardsDisabledTitle" maxlength="120" class="{{ $input }}">
                            @error('rewardsDisabledTitle') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Disabled message</label>
                            <textarea wire:model="rewardsDisabledMessage" rows="2" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                            @error('rewardsDisabledMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Reward %</label>
                            <input type="number" step="any" wire:model="rewardTargetPercentage" class="{{ $input }}">
                            @error('rewardTargetPercentage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Min target ($)</label>
                            <input type="number" step="any" wire:model="rewardMinTarget" class="{{ $input }}">
                            @error('rewardMinTarget') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Max target (0 = none)</label>
                            <input type="number" step="any" wire:model="rewardMaxTarget" class="{{ $input }}">
                            @error('rewardMaxTarget') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Withdrawals">
                <div class="flex flex-col gap-4">
                    <x-backoffice.toggle wire:model="withdrawalRequireAuthorizationCode" label="Require special coupon code for N-Cloud withdrawals"
                                         description="When on, withdrawing from the N-Cloud Call or N-Cloud SMS wallet requires the authorization code from a special coupon (entered on the Withdraw page). When off, cloud wallets withdraw without a code." />
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="My Website (personalized page)">
                <div class="flex flex-col gap-4">
                    <p class="font-sans text-muted text-[12px] -mt-1">Users are gated on My Website when validity lapses. They renew via online payment through any configured gateway.</p>
                    <div class="grid sm:grid-cols-2 gap-3">
                        {{-- Code renewal (distributor codes) hidden for now — online payment only.
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Renewal method</label>
                            <select wire:model="websiteRenewalMode" class="{{ $input }}">
                                <option value="online">Online payment (gateway)</option>
                                <option value="code">Code (bought from a distributor)</option>
                            </select>
                            @error('websiteRenewalMode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        --}}
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Validity per renewal (days)</label>
                            <input type="number" step="1" min="1" wire:model="websiteDurationDays" class="{{ $input }}">
                            @error('websiteDurationDays') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Online renewal price ($)</label>
                            <input type="number" step="any" min="0" wire:model="websiteRenewalPrice" class="{{ $input }}">
                            @error('websiteRenewalPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        {{-- Website-renewal code pricing hidden for now — code renewal is disabled; online payment only.
                        <div class="sm:col-span-2 rounded-[12px] bg-background/60 border border-muted/10 p-3 grid sm:grid-cols-2 gap-3">
                            <p class="font-sans text-muted text-[11px] sm:col-span-2">Website-renewal code pricing (used in code mode) — what a user pays a distributor, and what a distributor pays to mint. Foreign values apply to non-Nigerian accounts.</p>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Code price ($)</label>
                                <input type="number" step="any" min="0" wire:model="websiteCodePrice" class="{{ $input }}">
                                @error('websiteCodePrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Code vendor price ($)</label>
                                <input type="number" step="any" min="0" wire:model="websiteCodeAgentPrice" class="{{ $input }}">
                                @error('websiteCodeAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Foreign code price ($)</label>
                                <input type="number" step="any" min="0" wire:model="websiteCodeForeignPrice" class="{{ $input }}">
                                @error('websiteCodeForeignPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Foreign code vendor price ($)</label>
                                <input type="number" step="any" min="0" wire:model="websiteCodeForeignAgentPrice" class="{{ $input }}">
                                @error('websiteCodeForeignAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        --}}
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Expired title</label>
                            <input type="text" wire:model="websiteExpiredTitle" maxlength="120" class="{{ $input }}">
                            @error('websiteExpiredTitle') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <label class="font-sans text-muted text-[12px]">Expired message</label>
                            <textarea wire:model="websiteExpiredMessage" rows="2" maxlength="500" class="rounded-[12px] bg-background border border-muted/15 px-3.5 py-2.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                            @error('websiteExpiredMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
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
