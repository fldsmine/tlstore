<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">
        <x-app.app-bar title="Loan" />

        @if (! $enabled)
            <x-app.feature-locked class="mt-2" :title="config('settings.loan.disabled_title')" :message="config('settings.loan.disabled_message')" />
        @else
            <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">
                <div class="lg:col-span-2 flex flex-col gap-[18px]">
                    <div class="flex flex-col gap-1">
                        <p class="font-heading font-medium text-text text-[18px]">Apply for a loan</p>
                        <p class="font-sans text-muted text-[13px] leading-relaxed">Select your loan porpose, review your repayment schedule, and pay the required insurance fee. Once completed, your loan application will be forwarded for review. The review process takes between 24 and 48 hours.</p>
                    </div>

                    @if ($submittedLoan)
                        @php
                            $submittedStatus = $submittedLoan->userFacingStatusValue();
                            $submittedTone = ['processing' => 'bg-warning/15 text-warning', 'guarantor_form_required' => 'bg-warning/15 text-warning', 'approved' => 'bg-success/15 text-success', 'paid' => 'bg-success/15 text-success', 'rejected' => 'bg-danger/12 text-danger'][$submittedStatus] ?? 'bg-muted/15 text-muted';
                            $submittedReceipt = \App\Support\DownloadLinks::signed('loan.receipt', ['loan' => $submittedLoan->id, 'download' => 1]);
                            $submittedReceiptPng = \App\Support\DownloadLinks::signed('loan.receipt', ['loan' => $submittedLoan->id, 'download' => 1, 'format' => 'png']);
                            $submittedType = $submittedLoan->loan_type?->label() ?? 'Loan';
                        @endphp
                        <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                            <div class="flex items-start gap-3">
                                <span class="size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-file-list-3-line text-[22px]"></i></span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-heading font-medium text-text text-[18px]">Loan application receipt</p>
                                    <p class="font-sans text-muted text-[12px]">{{ $submittedType }} · {{ $submittedLoan->created_at?->format('j M Y, g:i A') }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 font-sans text-[10px] {{ $submittedTone }}">{{ $submittedLoan->userFacingStatusLabel() }}</span>
                            </div>

                            <div class="bg-background rounded-[15px] divide-y divide-muted/10">
                                <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Loan amount</span><x-money :usd="(float) $submittedLoan->amount" class="text-text font-semibold" /></div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Total repayment</span><x-money :usd="(float) $submittedLoan->total_repayment" class="text-text font-semibold" /></div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Repayment date</span><span class="text-text font-semibold">{{ $submittedLoan->due_date?->format('j M Y') ?? '—' }}</span></div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Insurance fee</span><span class="text-text font-semibold">{{ $submittedLoan->application_fee_currency }} {{ number_format((float) $submittedLoan->application_fee_amount, 2) }}</span></div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Fee payment</span><span class="text-text font-semibold">{{ $submittedLoan->application_fee_coupon_id ? 'Vendor coupon' : 'Payment channel' }}</span></div>
                                <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Disbursement account</span><span class="text-text font-semibold text-right">{{ $submittedLoan->disbursement_account_name }} · {{ $submittedLoan->disbursement_bank_name }} · {{ $submittedLoan->disbursement_account_number }}</span></div>
                            </div>

                            @if ($submittedLoan->status === \App\Enums\LoanStatus::REJECTED)
                                <div class="flex items-start gap-2 rounded-[12px] bg-danger/8 px-3.5 py-2.5">
                                    <i class="ri-information-line text-danger text-[15px] shrink-0 mt-0.5"></i>
                                    <p class="font-sans text-[12px] text-danger/90 leading-relaxed">{{ $submittedLoan->rejection_reason ?: config('settings.loan.rejection_reason') }}</p>
                                </div>
                            @elseif ($submittedLoan->status === \App\Enums\LoanStatus::PROCESSING)
                                <p class="font-sans text-muted text-[12px] leading-relaxed">Your loan application is currently under review. Please check back within 24-48 hours for an update on your application status.</p>
                            @elseif ($submittedLoan->requiresGuarantorForm())
                                <div class="rounded-[13px] bg-warning/10 px-3.5 py-3 font-sans text-[12px] text-text leading-relaxed">
                                    Your loan is ready for the next step. Pay the guarantor-form and submit the guarantor’s details before fund disbursement.
                                </div>
                                <button type="button" wire:click="openGuarantorForm('{{ $submittedLoan->id }}')" class="w-full rounded-[15px] border border-primary/15 bg-background p-3 flex items-center gap-3 text-left hover:border-primary/40 transition">
                                    <span class="size-[62px] rounded-[12px] bg-white border border-muted/15 p-2 flex flex-col justify-between shadow-sm shrink-0">
                                        <span class="flex items-center justify-between"><i class="ri-file-text-line text-primary text-[17px]"></i><span class="text-[7px] font-bold tracking-wider text-primary">NEXTEL</span></span>
                                        <span class="space-y-1"><span class="block h-1 w-9 rounded bg-muted/20"></span><span class="block h-1 w-7 rounded bg-muted/20"></span><span class="block h-1 w-10 rounded bg-muted/20"></span></span>
                                        <span class="text-[7px] font-semibold uppercase tracking-wider text-muted">Guarantor form</span>
                                    </span>
                                    <span class="min-w-0 flex-1"><span class="block font-heading font-medium text-text text-[13px]">Open guarantor form</span><span class="block font-sans text-muted text-[11px] mt-0.5">Tap to pay {{ '₦'.number_format((float) $guarantorFee['fee_ngn'], 2) }} NGN and complete the required form.</span></span>
                                    <i class="ri-arrow-right-s-line text-primary text-[20px]"></i>
                                </button>
                            @elseif ($submittedLoan->status === \App\Enums\LoanStatus::PAID)
                                <p class="font-sans text-success text-[12px] leading-relaxed">Your loan is recorded as paid/disbursed to the bank account on your application{{ $submittedLoan->paid_at ? ' on '.$submittedLoan->paid_at->format('j M Y') : '' }}.</p>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <x-app.download-link :href="$submittedReceipt" :filename="'Nextel-Loan-Receipt-'.$submittedLoan->id.'.pdf'" mime="application/pdf"
                                                     class="h-[46px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2">
                                    <i class="ri-download-2-line text-[17px]"></i> Download receipt PDF
                                </x-app.download-link>
                                <a href="{{ $submittedReceiptPng }}" target="_blank" rel="noopener"
                                   class="h-[46px] rounded-[30px] bg-background text-primary font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:bg-primary/5">
                                    <i class="ri-image-line text-[17px]"></i> Prepare receipt PNG
                                </a>
                            </div>

                            @if (! $hasUnresolved)
                                <button type="button" wire:click="newApplication" class="h-[44px] rounded-[30px] bg-background text-primary font-sans font-semibold text-[13px] hover:bg-primary/5 transition">Start another application</button>
                            @endif
                        </div>
                    @endif

                    @if ($hasUnresolved)
						@if ($pendingPayment)
                        <div class="flex items-start gap-3 rounded-[16px] bg-warning/12 px-4 py-4">
                            <i class="ri-time-line text-warning text-[20px] shrink-0 mt-0.5"></i>
                            <div class="flex-1 min-w-0">
								<p class="font-heading font-medium text-text text-[14px]">Insurance fee payment awaiting verification</p>
								<p class="font-sans text-muted text-[12px] leading-relaxed mt-1">Your application has not been submitted yet. We’ll create it only after the payment is verified.</p>
								<p class="font-sans text-muted text-[11px] mt-2">Payment reference: <span class="text-text font-medium">{{ $pendingPayment->reference }}</span></p>
								<div class="mt-3 flex flex-wrap items-center gap-2">
									@if ($pendingCheckout)
										<a href="{{ $pendingCheckout['url'] }}" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-primary px-4 font-sans text-[12px] font-semibold text-white" target="_blank" rel="noopener">
											Continue payment <i class="ri-arrow-right-up-line"></i>
										</a>
									@endif
									<button type="button"
											x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'cancelPendingPayment', params: ['{{ $pendingPayment->reference }}'], title: 'Cancel payment request?', message: 'This removes the pending payment request and any linked loan awaiting review. If you already paid, close this dialog and wait for verification instead.', confirmText: 'Delete request', tone: 'danger' })"
											wire:loading.attr="disabled"
											wire:target="cancelPendingPayment"
											class="inline-flex h-9 items-center gap-1.5 rounded-full border border-danger/25 bg-danger/5 px-4 font-sans text-[12px] font-semibold text-danger hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-60">
										<i class="ri-close-circle-line"></i> Cancel payment
									</button>
								</div>
							</div>
						</div>
						@else
                        <div class="flex items-start">
                            <div class="lg:col-span-2 flex flex-col gap-[18px]">
							@if(! $submittedLoan)
							@if (! $showSummary)
								<div class="flex items-center justify-between gap-3 rounded-[15px] bg-success/10 px-4 py-3">
									<div class="flex items-center gap-2.5">
										<i class="ri-shield-check-fill text-success text-[19px]"></i>
										<div>
											<p class="font-heading font-medium text-success text-[13px]">Application available</p>
											<p class="font-sans text-muted text-[11px]">A staff member will review your request.</p>
										</div>
									</div>
									<div class="text-right">
										<p class="font-sans text-muted text-[10px]">Maximum loan</p>
										<x-money :usd="$maxAmount" class="font-heading font-semibold text-text text-[14px]" />
									</div>
								</div>

								<div class="bg-surface rounded-[20px] p-5 flex flex-col gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]"
									 x-data="{ amt: @entangle('amount'), feePct: {{ $serviceFeePercent }}, symbol: @js($entrySymbol), money(v) { return this.symbol + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }, get fee() { return Number(this.amt || 0) * this.feePct / 100; }, get total() { return Number(this.amt || 0) + this.fee; } }">
									<div class="flex flex-col gap-1.5">
										<label class="font-sans text-[13px] text-text font-medium">Purpose of loan</label>
										<div class="relative">
											<select wire:model="loanType" class="w-full h-[48px] appearance-none rounded-[14px] bg-background border border-muted/20 px-4 pr-10 outline-none font-sans text-[14px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
												@foreach ($loanTypes as $value => $label)
													<option value="{{ $value }}">{{ $label }}</option>
												@endforeach
											</select>
											<i class="ri-arrow-down-s-line text-[20px] text-muted absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
										</div>
										@error('loanType')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
									</div>

									<div class="flex flex-col gap-2.5">
										<label class="font-sans text-[13px] text-text font-medium">Select loan amount <span class="text-muted font-normal">({{ $entryCode }})</span></label>
										<div class="grid grid-cols-3 gap-2.5">
											@foreach ($presets as $preset)
												<button type="button" @click="amt = {{ $preset }}"
														class="h-[44px] rounded-[12px] border font-sans text-[13px] transition"
														:class="Number(amt) === {{ $preset }} ? 'border-primary bg-primary text-white' : 'border-muted/25 bg-background text-text hover:border-muted/40'">
													{{ $entrySymbol }}{{ rtrim(rtrim(number_format($preset, 2), '0'), '.') }}
												</button>
											@endforeach
										</div>
									</div>

									<div class="flex flex-col gap-1.5">
										<div class="flex items-center justify-between gap-2">
											<label class="font-sans text-[13px] text-text font-medium">Enter amount <span class="text-muted font-normal">({{ $entryCode }})</span></label>
											<span class="font-sans text-[11px] text-text">UP TO {{ $maxEntry }}</span>
										</div>
										<input type="number" x-model.number="amt" min="{{ $minEntry }}" max="{{ $maxEntry }}" step="0.01" placeholder="{{ $entrySymbol }}0.00"
											   class="w-full h-[50px] rounded-[14px] bg-background border border-muted/20 px-4 outline-none font-heading text-[16px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
										@error('amount')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
									</div>

									<div class="rounded-[15px] bg-background p-4 flex flex-col gap-2.5">
										<p class="font-heading font-medium text-text text-[14px]">Repayment estimate</p>
										<div class="flex items-center justify-between font-sans text-[13px]"><span class="text-muted">Loan amount</span><span class="text-text" x-text="money(amt)"></span></div>
										<div class="flex items-center justify-between font-sans text-[13px]"><span class="text-muted">Interest rate<span x-show="feePct > 0" x-text="' (' + feePct + '%)'"></span></span><span :class="fee > 0 ? 'text-text' : 'text-success'" x-text="money(fee)"></span></div>
										<div class="h-px bg-muted/15"></div>
										<div class="flex items-center justify-between font-sans text-[14px]"><span class="text-text font-medium">Total repayment</span><span class="text-text font-semibold" x-text="money(total)"></span></div>
									</div>

									<div class="flex flex-col gap-1.5">
										<div class="flex items-center justify-between gap-2">
											<label class="font-sans text-[13px] text-text font-medium">Repayment date</label>
											<span class="font-sans text-[11px] text-muted">Within {{ $repaymentDays }} days</span>
										</div>
										<input type="date" wire:model="dueDate" min="{{ $minDueDate }}" max="{{ $maxDueDate }}"
											   class="w-full h-[48px] rounded-[14px] bg-background border border-muted/20 px-4 outline-none font-sans text-[14px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
										@error('dueDate')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
									</div>

									<div class="rounded-[15px] bg-background p-4 flex flex-col gap-3.5">
										<div>
											<p class="font-sans text-text text-[13px] font-medium">Bank account for loan disbursement</p>
											<p class="font-sans text-muted text-[11px] mt-1">Once your loan is approved, the bank account details below will be used to automatically disburse the approved loan amount to you.</p>
										</div>
										<div class="flex flex-col gap-1.5">
											<label class="font-sans text-[12px] text-muted">Account name</label>
											<input type="text" wire:model="accountName" maxlength="120" autocomplete="name" placeholder="Name on the bank account"
												   class="w-full h-[46px] rounded-[12px] bg-surface border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
											@error('accountName')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
										</div>
										<div class="flex flex-col gap-1.5">
											<label class="font-sans text-[12px] text-muted">Account number</label>
											<input type="text" wire:model="accountNumber" maxlength="34" inputmode="text" autocomplete="off" placeholder="Account number"
												   class="w-full h-[46px] rounded-[12px] bg-surface border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
											@error('accountNumber')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
										</div>
										<div class="flex flex-col gap-1.5">
											<label class="font-sans text-[12px] text-muted">Bank name</label>
											<input type="text" wire:model="bankName" maxlength="120" placeholder="Bank name"
												   class="w-full h-[46px] rounded-[12px] bg-surface border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
											@error('bankName')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
										</div>
									</div>

									<label class="flex items-start gap-3 rounded-[14px] bg-background px-4 py-3 cursor-pointer">
										<input type="checkbox" wire:model="agreementAccepted" value="1" class="mt-0.5 rounded border-muted/30 text-primary focus:ring-primary">
										<span class="font-sans text-[12px] text-text leading-relaxed">I accept the loan agreement, confirm the repayment date and bank details above, and understand the Insurance fee of ({{ $applicationFeeDisplay }}). If approved, staff will manually disburse funds to the account provided.</span>
									</label>
									@error('agreementAccepted')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror

									<button type="button" wire:click="proceedToSummary" wire:loading.attr="disabled" wire:target="proceedToSummary"
											class="h-[52px] rounded-[30px] bg-primary text-white font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-50">
										<span wire:loading.remove wire:target="proceedToSummary"><i class="ri-arrow-right-line"></i> Review application</span>
										<span wire:loading wire:target="proceedToSummary" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Preparing summary…</span>
									</button>
								</div>
							@else
								<div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
									<div>
										<p class="font-heading font-medium text-text text-[18px]">Review &amp; pay</p>
										<p class="font-sans text-muted text-[12px] mt-1">Your application is created only after the fee is validated.</p>
									</div>

									<div class="rounded-[15px] bg-background divide-y divide-muted/10">
										<div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Purpose of loan</span><span class="text-text font-semibold">{{ $loanTypeLabel }}</span></div>
										<div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Loan amount</span><x-money :usd="$summaryAmount" class="text-text font-semibold" /></div>
										<div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Interest rate</span><x-money :usd="$summaryServiceFee" class="text-text font-semibold" /></div>
										<div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-text font-medium">Total repayment</span><x-money :usd="$summaryTotal" class="text-text font-semibold" /></div>
										<div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Repayment date</span><span class="text-text font-semibold">{{ \Illuminate\Support\Carbon::parse($dueDate)->format('j M Y') }}</span></div>
										<div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Disbursement account</span><span class="text-text font-semibold text-right">{{ $accountName }} · {{ $bankName }} · {{ $accountNumber }}</span></div>
										<div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Insurance fee</span><span class="text-text font-semibold">{{ $applicationFeeDisplay }}</span></div>
									</div>

									<x-app.insurance-info label="Why pay insurance fee?" title="What Loan Insurance Covers" />

									@if ($canUseFeeCoupon)
										<form wire:submit="submitWithCoupon" class="flex flex-col gap-2.5">
											<label class="font-sans text-[13px] text-text font-medium">Use a loan insurance coupon</label>
											<div class="flex items-stretch gap-2">
												<input type="text" wire:model="feeCouponCode" maxlength="32" placeholder="Enter coupon code"
													   class="min-w-0 flex-1 h-[46px] uppercase rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
												<button type="submit" wire:loading.attr="disabled" wire:target="submitWithCoupon"
														class="shrink-0 h-[46px] px-4 rounded-[12px] bg-primary text-white font-sans font-semibold text-[12px] disabled:opacity-50">
													<span wire:loading.remove wire:target="submitWithCoupon">Apply coupon</span>
													<span wire:loading wire:target="submitWithCoupon"><i class="ri-loader-4-line animate-spin"></i></span>
												</button>
											</div>
											@error('feeCouponCode')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
										</form>
									@endif

									@if ($payment['available'] ?? false)
										<div class="border-t border-muted/10 pt-4 flex flex-col gap-2.5">
											<div class="flex items-center justify-between gap-2">
												<span class="font-sans text-muted text-[12px]">Pay through secure payment gateway channel</span>
												<span class="font-sans text-text text-[12px] font-semibold">{{ $paymentFeeDisplay }}</span>
											</div>
											<button type="button" wire:click="payApplicationFee" wire:loading.attr="disabled" wire:target="payApplicationFee"
													class="h-[48px] rounded-[30px] bg-secondary/30 text-primary font-sans font-semibold text-[13px] flex items-center justify-center gap-2 hover:bg-secondary/40 transition disabled:opacity-50">
												<span wire:loading.remove wire:target="payApplicationFee"><i class="ri-bank-card-line"></i> Pay fee and submit</span>
												<span wire:loading wire:target="payApplicationFee" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Opening payment…</span>
											</button>
										</div>
									@else
										<p class="font-sans text-muted text-[11px]">{{ $payment['reason'] ?? 'Online payment is unavailable.' }} @if ($canUseFeeCoupon) You can continue with a valid vendor coupon. @else Percentage fees require an active payment channel; vendor coupons are unavailable in this mode. @endif</p>
									@endif

									<button type="button" wire:click="backToApplication" class="h-[42px] rounded-[30px] bg-background text-muted font-sans font-medium text-[13px] hover:text-text transition">Back to application</button>
								</div>
							@endif
							@endif
                            </div>
                        </div>
						@endif
                    @elseif (! $submittedLoan)
                        @if (! $showSummary)
                            <div class="flex items-center justify-between gap-3 rounded-[15px] bg-success/10 px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <i class="ri-shield-check-fill text-success text-[19px]"></i>
                                    <div>
                                        <p class="font-heading font-medium text-success text-[13px]">Application available</p>
                                        <p class="font-sans text-muted text-[11px]">A staff member will review your request.</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-sans text-muted text-[10px]">Maximum loan</p>
                                    <x-money :usd="$maxAmount" class="font-heading font-semibold text-text text-[14px]" />
                                </div>
                            </div>

                            <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]"
                                 x-data="{ amt: @entangle('amount'), feePct: {{ $serviceFeePercent }}, symbol: @js($entrySymbol), money(v) { return this.symbol + Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }, get fee() { return Number(this.amt || 0) * this.feePct / 100; }, get total() { return Number(this.amt || 0) + this.fee; } }">
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-[13px] text-text font-medium">Purpose of loan</label>
                                    <div class="relative">
                                        <select wire:model="loanType" class="w-full h-[48px] appearance-none rounded-[14px] bg-background border border-muted/20 px-4 pr-10 outline-none font-sans text-[14px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                            @foreach ($loanTypes as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <i class="ri-arrow-down-s-line text-[20px] text-muted absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                    </div>
                                    @error('loanType')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                </div>

                                <div class="flex flex-col gap-2.5">
                                    <label class="font-sans text-[13px] text-text font-medium">Select loan amount <span class="text-muted font-normal">({{ $entryCode }})</span></label>
                                    <div class="grid grid-cols-3 gap-2.5">
                                        @foreach ($presets as $preset)
                                            <button type="button" @click="amt = {{ $preset }}"
                                                    class="h-[44px] rounded-[12px] border font-sans text-[13px] transition"
                                                    :class="Number(amt) === {{ $preset }} ? 'border-primary bg-primary text-white' : 'border-muted/25 bg-background text-text hover:border-muted/40'">
                                                {{ $entrySymbol }}{{ rtrim(rtrim(number_format($preset, 2), '0'), '.') }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center justify-between gap-2">
										<label class="font-sans text-[13px] text-text font-medium">Enter amount <span class="text-muted font-normal">({{ $entryCode }})</span></label>
                                        <span class="font-sans text-[11px] text-text">UP TO {{ $maxEntry }}</span>
                                    </div>
                                    <input type="number" x-model.number="amt" min="{{ $minEntry }}" max="{{ $maxEntry }}" step="0.01" placeholder="{{ $entrySymbol }}0.00"
                                           class="w-full h-[50px] rounded-[14px] bg-background border border-muted/20 px-4 outline-none font-heading text-[16px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                    @error('amount')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                </div>

                                <div class="rounded-[15px] bg-background p-4 flex flex-col gap-2.5">
                                    <p class="font-heading font-medium text-text text-[14px]">Repayment estimate</p>
                                    <div class="flex items-center justify-between font-sans text-[13px]"><span class="text-muted">Loan amount</span><span class="text-text" x-text="money(amt)"></span></div>
                                    <div class="flex items-center justify-between font-sans text-[13px]"><span class="text-muted">Interest rate<span x-show="feePct > 0" x-text="' (' + feePct + '%)'"></span></span><span :class="fee > 0 ? 'text-text' : 'text-success'" x-text="money(fee)"></span></div>
                                    <div class="h-px bg-muted/15"></div>
                                    <div class="flex items-center justify-between font-sans text-[14px]"><span class="text-text font-medium">Total repayment</span><span class="text-text font-semibold" x-text="money(total)"></span></div>
                                </div>

                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <label class="font-sans text-[13px] text-text font-medium">Repayment date</label>
                                        <span class="font-sans text-[11px] text-muted">Within {{ $repaymentDays }} days</span>
                                    </div>
                                    <input type="date" wire:model="dueDate" min="{{ $minDueDate }}" max="{{ $maxDueDate }}"
                                           class="w-full h-[48px] rounded-[14px] bg-background border border-muted/20 px-4 outline-none font-sans text-[14px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                    @error('dueDate')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                </div>

                                <div class="rounded-[15px] bg-background p-4 flex flex-col gap-3.5">
                                    <div>
                                        <p class="font-sans text-text text-[13px] font-medium">Bank account for loan disbursement</p>
                                        <p class="font-sans text-muted text-[11px] mt-1">Once your loan is approved, the bank account details below will be used to automatically disburse the approved loan amount to you.</p>
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="font-sans text-[12px] text-muted">Account name</label>
                                        <input type="text" wire:model="accountName" maxlength="120" autocomplete="name" placeholder="Name on the bank account"
                                               class="w-full h-[46px] rounded-[12px] bg-surface border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                        @error('accountName')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="font-sans text-[12px] text-muted">Account number</label>
                                        <input type="text" wire:model="accountNumber" maxlength="34" inputmode="text" autocomplete="off" placeholder="Account number"
                                               class="w-full h-[46px] rounded-[12px] bg-surface border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                        @error('accountNumber')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <label class="font-sans text-[12px] text-muted">Bank name</label>
                                        <input type="text" wire:model="bankName" maxlength="120" placeholder="Bank name"
                                               class="w-full h-[46px] rounded-[12px] bg-surface border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                        @error('bankName')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <label class="flex items-start gap-3 rounded-[14px] bg-background px-4 py-3 cursor-pointer">
                                    <input type="checkbox" wire:model="agreementAccepted" value="1" class="mt-0.5 rounded border-muted/30 text-primary focus:ring-primary">
                                    <span class="font-sans text-[12px] text-text leading-relaxed">I accept the loan agreement, confirm the repayment date and bank details above, and understand the Insurance fee of ({{ $applicationFeeDisplay }}). If approved, staff will manually disburse funds to the account provided.</span>
                                </label>
                                @error('agreementAccepted')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror

                                <button type="button" wire:click="proceedToSummary" wire:loading.attr="disabled" wire:target="proceedToSummary"
                                        class="h-[52px] rounded-[30px] bg-primary text-white font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-50">
                                    <span wire:loading.remove wire:target="proceedToSummary"><i class="ri-arrow-right-line"></i> Review application</span>
                                    <span wire:loading wire:target="proceedToSummary" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Preparing summary…</span>
                                </button>
                            </div>
                        @else
                            <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                                <div>
                                    <p class="font-heading font-medium text-text text-[18px]">Review &amp; pay</p>
                                    <p class="font-sans text-muted text-[12px] mt-1">Your application is created only after the fee is validated.</p>
                                </div>

                                <div class="rounded-[15px] bg-background divide-y divide-muted/10">
                                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Purpose of loan</span><span class="text-text font-semibold">{{ $loanTypeLabel }}</span></div>
                                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Loan amount</span><x-money :usd="$summaryAmount" class="text-text font-semibold" /></div>
                                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Interest rate</span><x-money :usd="$summaryServiceFee" class="text-text font-semibold" /></div>
                                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-text font-medium">Total repayment</span><x-money :usd="$summaryTotal" class="text-text font-semibold" /></div>
                                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Repayment date</span><span class="text-text font-semibold">{{ \Illuminate\Support\Carbon::parse($dueDate)->format('j M Y') }}</span></div>
                                    <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Disbursement account</span><span class="text-text font-semibold text-right">{{ $accountName }} · {{ $bankName }} · {{ $accountNumber }}</span></div>
                                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Insurance fee</span><span class="text-text font-semibold">{{ $applicationFeeDisplay }}</span></div>
                                </div>

								<x-app.insurance-info label="Why pay insurance fee?" title="What Loan Insurance Covers" />

                                @if ($canUseFeeCoupon)
                                    <form wire:submit="submitWithCoupon" class="flex flex-col gap-2.5">
                                        <label class="font-sans text-[13px] text-text font-medium">Use a loan insurance coupon</label>
                                        <div class="flex items-stretch gap-2">
                                            <input type="text" wire:model="feeCouponCode" maxlength="32" placeholder="Enter coupon code"
                                                   class="min-w-0 flex-1 h-[46px] uppercase rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                            <button type="submit" wire:loading.attr="disabled" wire:target="submitWithCoupon"
                                                    class="shrink-0 h-[46px] px-4 rounded-[12px] bg-primary text-white font-sans font-semibold text-[12px] disabled:opacity-50">
                                                <span wire:loading.remove wire:target="submitWithCoupon">Apply coupon</span>
                                                <span wire:loading wire:target="submitWithCoupon"><i class="ri-loader-4-line animate-spin"></i></span>
                                            </button>
                                        </div>
                                        @error('feeCouponCode')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                    </form>
                                @endif

                                @if ($payment['available'] ?? false)
                                    <div class="border-t border-muted/10 pt-4 flex flex-col gap-2.5">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-sans text-muted text-[12px]">Pay through secure payment gateway channel</span>
                                            <span class="font-sans text-text text-[12px] font-semibold">{{ $paymentFeeDisplay }}</span>
                                        </div>
                                        <button type="button" wire:click="payApplicationFee" wire:loading.attr="disabled" wire:target="payApplicationFee"
                                                class="h-[48px] rounded-[30px] bg-secondary/30 text-primary font-sans font-semibold text-[13px] flex items-center justify-center gap-2 hover:bg-secondary/40 transition disabled:opacity-50">
                                            <span wire:loading.remove wire:target="payApplicationFee"><i class="ri-bank-card-line"></i> Pay fee and submit</span>
                                            <span wire:loading wire:target="payApplicationFee" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Opening payment…</span>
                                        </button>
                                    </div>
                                @else
                                    <p class="font-sans text-muted text-[11px]">{{ $payment['reason'] ?? 'Online payment is unavailable.' }} @if ($canUseFeeCoupon) You can continue with a valid vendor coupon. @else Percentage fees require an active payment channel; vendor coupons are unavailable in this mode. @endif</p>
                                @endif

                                <button type="button" wire:click="backToApplication" class="h-[42px] rounded-[30px] bg-background text-muted font-sans font-medium text-[13px] hover:text-text transition">Back to application</button>
                            </div>
                        @endif
                    @endif
                </div>

                <div class="lg:col-span-1 flex flex-col gap-2.5">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[15px]">Application history</p>
                        <span class="font-sans text-muted text-[11px]">{{ $loans->count() }} recent</span>
                    </div>
                    @forelse ($loans as $loan)
                        @php
                            $loanStatus = $loan->userFacingStatusValue();
                            $badge = ['processing' => 'bg-warning/15 text-warning', 'guarantor_form_required' => 'bg-warning/15 text-warning', 'approved' => 'bg-success/15 text-success', 'paid' => 'bg-success/15 text-success', 'rejected' => 'bg-danger/12 text-danger'][$loanStatus] ?? 'bg-muted/15 text-muted';
                            $pendingGuarantorPayment = $pendingGuarantorPayments->get((string) $loan->id);
                        @endphp
                        <div class="flex flex-col gap-2" wire:key="loan-{{ $loan->id }}">
                            <button type="button" wire:click="showDetails('{{ $loan->id }}')"
                                    class="w-full text-left bg-surface rounded-[16px] p-4 flex flex-col gap-2 shadow-sm hover:shadow-md transition">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-sans text-muted text-[12px]">{{ $loan->loan_type?->label() ?? 'Loan' }}</span>
                                    <span class="rounded-full px-2.5 py-1 font-sans text-[10px] {{ $badge }}">{{ $loan->userFacingStatusLabel() }}</span>
                                </div>
                                <x-money :usd="$loan->amount" class="font-heading font-medium text-text text-[16px]" />
                                <div class="flex items-center justify-between font-sans text-[11px] text-muted">
                                    <span>Applied {{ $loan->created_at?->format('j M Y') }}</span>
                                    <span>Due {{ $loan->due_date?->format('j M Y') }}</span>
                                </div>
                                @if ($loan->status === \App\Enums\LoanStatus::REJECTED)
                                    <span class="font-sans text-[11px] text-danger line-clamp-2">{{ $loan->rejection_reason ?: config('settings.loan.rejection_reason') }}</span>
                                @endif
                            </button>
							@if( $isGuarantor )
                             @if ($loan->requiresGuarantorForm())
                                <button type="button" wire:click="openGuarantorForm('{{ $loan->id }}')"
                                        class="w-full rounded-[14px] border border-primary/15 bg-surface p-3 flex items-center gap-3 text-left hover:border-primary/40 transition">
                                    <span class="size-[54px] rounded-[10px] bg-white border border-muted/15 p-1.5 flex flex-col justify-between shadow-sm shrink-0">
                                        <span class="flex items-center justify-between"><i class="ri-file-text-line text-primary text-[15px]"></i><span class="text-[6px] font-bold tracking-wider text-primary">NEXTEL</span></span>
                                        <span class="space-y-1"><span class="block h-1 w-8 rounded bg-muted/20"></span><span class="block h-1 w-6 rounded bg-muted/20"></span></span>
                                        <span class="text-[6px] font-semibold uppercase tracking-wider text-muted">Form</span>
                                    </span>
                                    <span class="min-w-0 flex-1"><span class="block font-heading font-medium text-text text-[12px]">Guarantor form</span><span class="block font-sans text-muted text-[10px] mt-0.5">{{ $pendingGuarantorPayment ? 'Payment pending · tap to continue' : ($loan->guarantor_form_fee_paid_at ? 'Fee verified · complete the form' : 'Required before disbursement') }}</span></span>
                                    <i class="ri-arrow-right-s-line text-primary text-[18px]"></i>
                                </button>
                            @elseif ($loan->hasCompletedGuarantorForm())
                                <div class="rounded-[12px] bg-success/8 px-3 py-2 font-sans text-[11px] text-success flex items-center justify-between gap-2">
                                    <span><i class="ri-checkbox-circle-line mr-1"></i> Guarantor form submitted</span>
                                    <a href="{{ \App\Support\DownloadLinks::signed('loan.guarantor.form.view', ['loan' => $loan->id]) }}" class="font-semibold underline">View / download PDF</a>
                                </div>
                            @endif
                           @endif
                        </div>
                    @empty
                        <div class="bg-surface rounded-[16px] px-4 py-8 flex flex-col items-center gap-1.5 text-center shadow-sm">
                            <i class="ri-bank-line text-muted text-[24px]"></i>
                            <p class="font-sans text-muted text-[13px]">No loan applications yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>

    @if ($detailLoan)
        @php
            $detailStatus = $detailLoan->userFacingStatusValue();
            $detailTone = ['processing' => 'bg-warning/15 text-warning', 'guarantor_form_required' => 'bg-warning/15 text-warning', 'approved' => 'bg-success/15 text-success', 'paid' => 'bg-success/15 text-success', 'rejected' => 'bg-danger/12 text-danger'][$detailStatus] ?? 'bg-muted/15 text-muted';
            $detailReceipt = \App\Support\DownloadLinks::signed('loan.receipt', ['loan' => $detailLoan->id, 'download' => 1]);
            $detailReceiptPng = \App\Support\DownloadLinks::signed('loan.receipt', ['loan' => $detailLoan->id, 'download' => 1, 'format' => 'png']);
        @endphp
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
            <div class="absolute inset-0 bg-black/40" wire:click="$set('detailId', null)"></div>
            <div class="relative w-full sm:max-w-[440px] max-h-[88vh] overflow-y-auto bg-surface rounded-t-[24px] sm:rounded-[24px] p-6 flex flex-col gap-4 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                <div class="flex items-center justify-between">
                    <p class="font-heading font-medium text-text text-[18px]">Loan receipt</p>
                    <button type="button" wire:click="$set('detailId', null)" class="size-8 rounded-full bg-background flex items-center justify-center text-muted hover:text-text" aria-label="Close"><i class="ri-close-line text-[18px]"></i></button>
                </div>
                <div class="flex flex-col items-center gap-2 py-1">
                    <span class="size-12 rounded-full bg-primary/10 flex items-center justify-center text-primary"><i class="ri-bank-line text-[22px]"></i></span>
                    <x-money :usd="(float) $detailLoan->amount" class="font-heading font-semibold text-text text-[26px]" />
                    <span class="rounded-full px-3 py-1 font-sans text-[11px] {{ $detailTone }}">{{ $detailLoan->userFacingStatusLabel() }}</span>
                </div>
                <div class="bg-background rounded-[15px] divide-y divide-muted/10">
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Purpose of loan</span><span class="text-text font-medium">{{ $detailLoan->loan_type?->label() ?? 'Loan' }}</span></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Loan amount</span><x-money :usd="(float) $detailLoan->amount" class="text-text font-medium" /></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Interest rate</span><x-money :usd="max(0, (float) $detailLoan->total_repayment - (float) $detailLoan->amount)" class="text-text font-medium" /></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Total repayment</span><x-money :usd="(float) $detailLoan->total_repayment" class="text-text font-semibold" /></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Repayment date</span><span class="text-text">{{ $detailLoan->due_date?->format('j M Y') }}</span></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Applied</span><span class="text-text">{{ $detailLoan->created_at?->format('j M Y, g:i A') }}</span></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Insurance fee</span><span class="text-text font-medium">{{ $detailLoan->application_fee_currency }} {{ number_format((float) $detailLoan->application_fee_amount, 2) }} · insurance fee</span></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Fee payment</span><span class="text-text">{{ $detailLoan->application_fee_coupon_id ? 'Vendor coupon' : 'Payment channel' }}</span></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Guarantor-form fee</span><span class="text-text">{{ $detailLoan->guarantor_form_fee_paid_at ? 'NGN '.number_format((float) $detailLoan->guarantor_form_fee_amount, 2).' · verified' : (($pendingGuarantorPayments->get((string) $detailLoan->id)) ? 'Payment pending' : 'Not paid') }}</span></div>
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Guarantor form</span><span class="text-text">{{ $detailLoan->hasCompletedGuarantorForm() ? 'Submitted '.$detailLoan->guarantor_form_submitted_at?->format('j M Y, g:i A') : 'Not submitted' }}</span></div>
                    <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Disbursement account</span><span class="text-text text-right break-words">{{ $detailLoan->disbursement_account_name }} · {{ $detailLoan->disbursement_bank_name }} · {{ $detailLoan->disbursement_account_number }}</span></div>
                    @if ($detailLoan->application_fee_reference)
                        <div class="flex items-center justify-between gap-3 px-4 py-3 font-sans text-[13px]"><span class="text-muted">Payment reference</span><span class="text-text text-right break-all">{{ $detailLoan->application_fee_reference }}</span></div>
                    @endif
                    <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Agreement accepted</span><span class="text-text">{{ $detailLoan->agreement_accepted_at?->format('j M Y, g:i A') ?? '—' }}</span></div>
                    @if ($detailLoan->paid_at)
                        <div class="flex items-center justify-between px-4 py-3 font-sans text-[13px]"><span class="text-muted">Marked paid</span><span class="text-text">{{ $detailLoan->paid_at->format('j M Y, g:i A') }}</span></div>
                    @endif
                </div>
                @if ($detailLoan->status === \App\Enums\LoanStatus::REJECTED)
                    <div class="flex items-start gap-2 rounded-[12px] bg-danger/8 px-3.5 py-2.5">
                        <i class="ri-information-line text-danger text-[15px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-[12px] text-danger/90 leading-relaxed">{{ $detailLoan->rejection_reason ?: config('settings.loan.rejection_reason') }}</p>
                    </div>
                @elseif ($detailLoan->requiresGuarantorForm())
					@if( $isGuarantor )
                    <button type="button" wire:click="openGuarantorForm('{{ $detailLoan->id }}')" class="w-full rounded-[14px] border border-primary/15 bg-background p-3 flex items-center gap-3 text-left">
                        <span class="size-[54px] rounded-[10px] bg-white border border-muted/15 p-1.5 flex flex-col justify-between shadow-sm shrink-0"><i class="ri-file-text-line text-primary text-[16px]"></i><span class="block h-1 w-8 rounded bg-muted/20"></span><span class="text-[6px] font-semibold uppercase tracking-wider text-muted">Guarantor form</span></span>
                        <span class="min-w-0 flex-1"><span class="block font-heading font-medium text-text text-[12px]">Open required guarantor form</span><span class="block font-sans text-muted text-[10px] mt-0.5">{{ $detailLoan->guarantor_form_fee_paid_at ? 'Payment verified · form ready' : 'Pay ₦'.number_format((float) $guarantorFee['fee_ngn'], 2).' NGN to continue' }}</span></span>
                        <i class="ri-arrow-right-s-line text-primary"></i>
                    </button>
					@endif
                @elseif ($detailLoan->hasCompletedGuarantorForm())
					@if( $isGuarantor )
                    <a href="{{ \App\Support\DownloadLinks::signed('loan.guarantor.form.view', ['loan' => $detailLoan->id]) }}" class="h-[44px] rounded-[30px] bg-success/10 text-success font-sans font-semibold text-[13px] inline-flex items-center justify-center gap-2"><i class="ri-download-2-line"></i> View / download guarantor form</a>
					@endif
                @endif
                <div class="grid grid-cols-1 gap-2.5">
                    <x-app.download-link :href="$detailReceipt" :filename="'Nextel-Loan-Receipt-'.$detailLoan->id.'.pdf'" mime="application/pdf"
                                         class="h-[46px] rounded-[30px] bg-primary text-white font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2">
                        <i class="ri-download-2-line text-[17px]"></i> Download receipt PDF
                    </x-app.download-link>
                    <a href="{{ $detailReceiptPng }}" target="_blank" rel="noopener"
                       class="h-[46px] rounded-[30px] bg-background text-primary font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:bg-primary/5">
                        <i class="ri-image-line text-[17px]"></i> Prepare receipt PNG
                    </a>
                </div>
                <button type="button" wire:click="$set('detailId', null)" class="h-[42px] rounded-[30px] bg-background text-muted font-sans font-medium text-[13px]">Close</button>
            </div>
        </div>
    @endif

    @if ($guarantorFormLoan)
        @php
            $pendingGuarantorPayment = $pendingGuarantorPayments->get((string) $guarantorFormLoan->id);
            $pendingGuarantorCheckout = $pendingGuarantorPayment?->meta['checkout_url'] ?? null;
            $guarantorFormView = \App\Support\DownloadLinks::signed('loan.guarantor.form.view', ['loan' => $guarantorFormLoan->id]);
        @endphp
        <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center">
            <div class="absolute inset-0 bg-black/45 backdrop-blur-sm" wire:click="$set('guarantorLoanId', null)"></div>
            <div class="relative w-full sm:max-w-[520px] max-h-[92vh] overflow-y-auto bg-surface rounded-t-[24px] sm:rounded-[24px] p-5 sm:p-6 flex flex-col gap-4 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-heading font-medium text-text text-[18px]">Guarantor form</p>
                        <p class="font-sans text-muted text-[12px] mt-1">Loan {{ $guarantorFormLoan->loan_type?->label() ?? 'application' }} · {{ $guarantorFormLoan->created_at?->format('j M Y') }}</p>
                    </div>
                    <button type="button" wire:click="$set('guarantorLoanId', null)" class="size-8 rounded-full bg-background flex items-center justify-center text-muted" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>

                @if ($guarantorFormLoan->hasCompletedGuarantorForm())
                    <div class="rounded-[15px] bg-success/10 px-4 py-3 flex items-start gap-2.5">
                        <i class="ri-checkbox-circle-line text-success text-[20px] mt-0.5"></i>
                        <div><p class="font-sans font-semibold text-success text-[13px]">Form submitted</p><p class="font-sans text-muted text-[11px] mt-1">Submitted {{ $guarantorFormLoan->guarantor_form_submitted_at?->format('j M Y, g:i A') }}. It must be submitted before staff can record a disbursement.</p></div>
                    </div>
                    <a href="{{ $guarantorFormView }}" class="h-[48px] rounded-[30px] bg-primary text-white font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2">
                        <i class="ri-file-text-line"></i> View / download guarantor form
                    </a>
                @elseif ($guarantorFormLoan->guarantor_form_fee_paid_at)
                    <div class="rounded-[15px] bg-success/10 px-4 py-3 flex items-start gap-2.5">
                        <i class="ri-shield-check-line text-success text-[20px] mt-0.5"></i>
                        <div><p class="font-sans font-semibold text-success text-[13px]">Payment verified · form unlocked</p><p class="font-sans text-muted text-[11px] mt-1">Verified fee: ₦{{ number_format((float) $guarantorFormLoan->guarantor_form_fee_amount, 2) }} NGN. Enter the guarantor’s details and confirm their consent.</p></div>
                    </div>

                    <form wire:submit="submitGuarantorForm" class="flex flex-col gap-3.5">
                        @error('guarantorForm')<p class="rounded-[12px] bg-danger/10 px-3 py-2 font-sans text-[12px] text-danger">{{ $message }}</p>@enderror
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <label for="guarantor-full-name" class="font-sans text-[12px] text-muted">Guarantor’s full name</label>
                                <input id="guarantor-full-name" type="text" maxlength="120" wire:model="guarantorForm.full_name" autocomplete="name" class="w-full h-[44px] rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                @error('guarantorForm.full_name')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="guarantor-relationship" class="font-sans text-[12px] text-muted">Relationship to applicant</label>
                                <input id="guarantor-relationship" type="text" maxlength="80" wire:model="guarantorForm.relationship" class="w-full h-[44px] rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                @error('guarantorForm.relationship')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="guarantor-phone" class="font-sans text-[12px] text-muted">Phone number</label>
                                <input id="guarantor-phone" type="tel" maxlength="32" wire:model="guarantorForm.phone" autocomplete="tel" class="w-full h-[44px] rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                @error('guarantorForm.phone')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="guarantor-email" class="font-sans text-[12px] text-muted">Email (optional)</label>
                                <input id="guarantor-email" type="email" maxlength="255" wire:model="guarantorForm.email" autocomplete="email" class="w-full h-[44px] rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                @error('guarantorForm.email')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <label for="guarantor-address" class="font-sans text-[12px] text-muted">Residential address</label>
                                <textarea id="guarantor-address" rows="2" maxlength="255" wire:model="guarantorForm.residential_address" class="w-full rounded-[12px] bg-background border border-muted/20 px-3.5 py-3 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                                @error('guarantorForm.residential_address')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5 sm:col-span-2">
                                <label for="guarantor-signature" class="font-sans text-[12px] text-muted">Electronic signature · type the full name above</label>
                                <input id="guarantor-signature" type="text" maxlength="120" wire:model="guarantorForm.signature" autocomplete="off" class="w-full h-[44px] rounded-[12px] bg-background border border-muted/20 px-3.5 outline-none font-sans text-[13px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                                @error('guarantorForm.signature')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <label class="flex items-start gap-2.5 rounded-[12px] bg-background px-3.5 py-3 cursor-pointer">
                            <input type="checkbox" wire:model="guarantorForm.consent" class="mt-0.5 rounded border-muted/30 text-primary focus:ring-primary">
                            <span class="font-sans text-[11px] text-text leading-relaxed">I confirm that the guarantor has consented to provide these details and to be contacted about this loan. I am authorized to submit this information.</span>
                        </label>
                        @error('guarantorForm.consent')<span class="font-sans text-[11px] text-danger">{{ $message }}</span>@enderror
                        <button type="submit" wire:loading.attr="disabled" wire:target="submitGuarantorForm" class="h-[48px] rounded-[30px] bg-primary text-white font-sans font-semibold text-[14px] flex items-center justify-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="submitGuarantorForm"><i class="ri-check-line"></i> Submit guarantor form</span>
                            <span wire:loading wire:target="submitGuarantorForm"><i class="ri-loader-4-line animate-spin"></i> Submitting…</span>
                        </button>
                    </form>
                @else
                    <div class="rounded-[15px] bg-warning/10 px-4 py-3">
                        <p class="font-sans font-semibold text-text text-[13px]">Required before manual disbursement</p>
                        <p class="font-sans text-muted text-[11px] mt-1 leading-relaxed">A fixed fee of ₦{{ number_format((float) $guarantorFee['fee_ngn'], 2) }} NGN unlocks the guarantor form. Payment is verified before any guarantor details are collected.</p>
                    </div>
                    @if ($pendingGuarantorPayment)
                        <div class="rounded-[14px] border border-muted/10 bg-background p-3.5 flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-3"><span class="font-sans text-muted text-[12px]">Payment status</span><span class="font-sans text-warning text-[12px] font-semibold">Awaiting verification</span></div>
                            <div class="font-sans text-muted text-[11px] break-all">Reference: <span class="text-text">{{ $pendingGuarantorPayment->reference }}</span></div>
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                @if ($pendingGuarantorCheckout)
                                    <a href="{{ $pendingGuarantorCheckout }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-primary px-4 font-sans text-[12px] font-semibold text-white">Continue payment <i class="ri-arrow-right-up-line"></i></a>
                                @endif
                                <button type="button" wire:click="refreshGuarantorFormPayment('{{ $pendingGuarantorPayment->reference }}')" wire:loading.attr="disabled" wire:target="refreshGuarantorFormPayment" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-primary/8 px-4 font-sans text-[12px] font-semibold text-primary disabled:opacity-60"><i class="ri-refresh-line"></i> Check payment</button>
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'cancelGuarantorFormPayment', params: ['{{ $pendingGuarantorPayment->reference }}'], title: 'Cancel guarantor-form payment?', message: 'We will check the payment status with the provider first. The request can be removed only after the provider confirms it failed or expired. A pending, unverifiable, or successful payment will be kept.', confirmText: 'Cancel request', tone: 'danger' })" wire:loading.attr="disabled" wire:target="cancelGuarantorFormPayment" class="inline-flex h-9 items-center gap-1.5 rounded-full border border-danger/25 bg-danger/5 px-4 font-sans text-[12px] font-semibold text-danger hover:bg-danger/10 disabled:opacity-60"><i class="ri-close-circle-line"></i> Cancel request</button>
                            </div>
                        </div>
                    @elseif ($guarantorFee['available'] ?? false)
                        <div class="rounded-[14px] border border-muted/10 bg-background p-3.5">
                            <div class="flex items-center justify-between gap-3"><span class="font-sans text-muted text-[12px]">Active channel charge</span><span class="font-sans text-text text-[12px] font-semibold">{{ number_format((float) $guarantorFee['charge_amount'], 2) }} {{ $guarantorFee['currency'] }}</span></div>
                            <p class="font-sans text-muted text-[10px] mt-1">Fixed fee equivalent: ₦{{ number_format((float) $guarantorFee['fee_ngn'], 2) }} NGN</p>
                        </div>
                        <button type="button" wire:click="startGuarantorFormPayment" wire:loading.attr="disabled" wire:target="startGuarantorFormPayment" class="h-[48px] rounded-[30px] bg-primary text-white font-sans font-semibold text-[14px] flex items-center justify-center gap-2 disabled:opacity-60">
                            <span wire:loading.remove wire:target="startGuarantorFormPayment"><i class="ri-bank-card-line"></i> Pay and access form</span>
                            <span wire:loading wire:target="startGuarantorFormPayment"><i class="ri-loader-4-line animate-spin"></i> Opening payment…</span>
                        </button>
                    @else
                        <p class="rounded-[12px] bg-danger/8 px-3.5 py-3 font-sans text-[12px] text-danger">{{ $guarantorFee['reason'] ?? 'The guarantor-form fee is unavailable right now.' }}</p>
                    @endif
                @endif

                <button type="button" wire:click="$set('guarantorLoanId', null)" class="h-[42px] rounded-[30px] bg-background text-muted font-sans font-medium text-[13px]">Close</button>
            </div>
        </div>
    @endif

    <x-app.bottom-nav />
</div>
