<div>
    <x-backoffice.page title="Payment requests" subtitle="Review pending and unresolved charges across every payment channel">
        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <x-backoffice.stat label="Pending requests" :value="number_format($pendingCount)" icon="ri-time-line" tone="warning" hint="account and guest payments" />
            <x-backoffice.stat label="Failed for review" :value="number_format($failedReviewCount)" icon="ri-error-warning-line" tone="danger" hint="checkout was created" />
            <x-backoffice.stat label="Manual confirmations today" :value="number_format($manualResolvedToday)" icon="ri-shield-check-line" />
            <x-backoffice.stat label="Backoffice actions today" :value="number_format($resolutionActionsToday)" icon="ri-history-line" />
        </div>

        <x-backoffice.card>
            <div class="flex items-start gap-3 rounded-[14px] border border-primary/15 bg-primary/5 px-4 py-3.5 mb-5">
                <i class="ri-information-line text-primary text-[19px] mt-0.5 shrink-0"></i>
                <div class="font-sans text-[12px] leading-relaxed text-muted">
                    <p class="font-medium text-text">Safe reconciliation, not a direct status edit</p>
                    <p>Use <span class="text-text">Recheck provider</span> to verify against the original channel. A manual confirmation requires a super admin, a provider/bank transaction reference, evidence source and a note. It still uses the existing amount, currency, duplicate-payment and entitlement checks. Settled references cannot be credited again.</p>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Payment request category">
                    @foreach (['transactions' => 'Account payments', 'product_orders' => 'Guest storefront orders'] as $key => $label)
                        <button type="button" wire:click="$set('requestGroup', '{{ $key }}')"
                                class="h-[38px] rounded-full px-4 font-sans text-[12px] transition {{ $requestGroup === $key ? 'bg-primary text-white' : 'bg-background text-muted hover:text-text' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 md:grid-cols-[minmax(220px,1fr)_180px_180px_120px] gap-3">
                    <label class="flex items-center gap-2 h-[42px] rounded-[12px] bg-background border border-muted/15 px-3">
                        <i class="ri-search-line text-muted text-[16px]"></i>
                        <input type="search" wire:model.live.debounce.350ms="search"
                               placeholder="Reference, user, email, phone…"
                               class="min-w-0 flex-1 bg-transparent border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted focus:ring-0">
                    </label>
                    <select wire:model.live="providerFilter" class="h-[42px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        <option value="">All payment channels</option>
                        @foreach ($providers as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter" class="h-[42px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        <option value="unresolved">Needs attention</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        @if ($requestGroup === 'transactions')
                            <option value="completed">Completed</option>
                        @else
                            <option value="paid">Paid</option>
                        @endif
                        <option value="all">All statuses</option>
                    </select>
                    <select wire:model.live="perPage" class="h-[42px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @foreach ([10, 25, 50, 100] as $value)
                            <option value="{{ $value }}">{{ $value }} / page</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-backoffice.card>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($requests->isEmpty())
                <div class="p-10 text-center">
                    <span class="mx-auto mb-3 size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center"><i class="ri-checkbox-circle-line text-[22px]"></i></span>
                    <p class="font-heading font-medium text-text text-[15px]">No payment requests match</p>
                    <p class="mt-1 font-sans text-muted text-[12px]">Try a different status, channel or search term.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Payment / reference</th>
                                <th class="px-5 py-3 font-medium">Payer</th>
                                <th class="px-5 py-3 font-medium">Channel</th>
                                <th class="px-5 py-3 font-medium text-right">Requested</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Created</th>
                                <th class="px-5 py-3 font-medium text-right">Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $record)
                                @php
                                    $isOrder = $record instanceof \App\Models\ProductOrder;
                                    $kind = $isOrder ? 'product_order' : 'transaction';
                                    $typeLabel = $isOrder ? ($record->kindLabel().' — '.$record->product_name) : ($record->type?->getName() ?? 'Payment');
                                    $providerLabel = $providers[$record->provider] ?? \Illuminate\Support\Str::headline((string) $record->provider);
                                    $expected = $isOrder ? (float) $record->amount_ngn : (float) ($record->meta['amount_ngn'] ?? 0);
                                    $currency = strtoupper((string) ($isOrder ? $record->currency : ($record->meta['currency'] ?? '')));
                                    $shownStatus = $isOrder && $record->status === 'paid' ? 'Paid' : ucfirst((string) $record->status);
                                    $statusTone = in_array($record->status, ['completed', 'paid'], true) ? 'success' : ($record->status === 'pending' ? 'warning' : 'danger');
                                    $manualReview = $isOrder ? ($record->meta['manual_review'] ?? $record->meta['reconcile_error'] ?? null) : ($record->meta['manual_review'] ?? $record->meta['reconcile_error'] ?? null);
                                @endphp
                                <tr wire:key="payment-{{ $kind }}-{{ $record->id }}" class="border-b border-muted/5 last:border-0 hover:bg-background/50">
                                    <td class="px-5 py-3.5">
                                        <p class="font-sans text-text text-[13px] font-medium">{{ $typeLabel }}</p>
                                        <p class="font-sans text-muted text-[11px] tabular-nums">{{ $record->reference }}</p>
                                        @if ($record->gateway_reference)
                                            <p class="font-sans text-muted/80 text-[10px]">gateway ref: {{ $record->gateway_reference }}</p>
                                        @endif
                                        @if ($manualReview)
                                            <p class="mt-1 font-sans text-danger text-[10px]">Review flag: {{ \Illuminate\Support\Str::headline((string) ($manualReview['reason'] ?? 'reconciliation mismatch')) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if ($isOrder)
                                            <p class="font-sans text-text text-[12px]">{{ $record->full_name }}</p>
                                            <p class="font-sans text-muted text-[11px]">{{ $record->email }}</p>
                                        @elseif ($record->user)
                                            <a href="{{ route('backoffice.users.show', $record->user) }}" wire:navigate class="font-sans text-text text-[12px] hover:text-primary">
                                                {{ $record->user->username ? '@'.$record->user->username : $record->user->name }}
                                            </a>
                                            <p class="font-sans text-muted text-[11px]">{{ $record->user->email }}</p>
                                        @else
                                            <span class="font-sans text-muted text-[12px]">Account unavailable</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-2.5 py-1 font-sans text-[11px] text-text">
                                            <i class="ri-bank-card-line text-muted"></i>{{ $providerLabel }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        @if ($expected > 0 && $currency !== '')
                                            <span class="font-heading text-text text-[13px] tabular-nums">{{ number_format($expected, 2) }}</span>
                                            <span class="block font-sans text-muted text-[10px]">{{ $currency }}</span>
                                        @else
                                            <span class="font-sans text-danger text-[11px]">Expected amount missing</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <x-backoffice.badge :tone="$statusTone">{{ $shownStatus }}</x-backoffice.badge>
                                        @if ($record->paymentReference)
                                            <span class="mt-1 block font-sans text-muted text-[10px]">payment already ledgered</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-sans text-text text-[12px]">{{ $record->created_at?->format('j M Y, g:i A') }}</p>
                                        <p class="font-sans text-muted text-[10px]">{{ $record->created_at?->diffForHumans() }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button type="button" wire:click="openRequest('{{ $kind }}', '{{ $record->id }}')"
                                                class="h-[34px] rounded-[10px] bg-primary/10 px-3 font-sans text-[12px] font-medium text-primary hover:bg-primary/15">
                                            Review <i class="ri-arrow-right-line ml-1"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($requests->hasPages())
                    <div class="border-t border-muted/10 p-4">{{ $requests->links('vendor.pagination.nextel') }}</div>
                @endif
            @endif
        </x-backoffice.card>

        @if ($selected)
            @php
                $selectedIsOrder = $selected instanceof \App\Models\ProductOrder;
                $selectedStatus = $selectedIsOrder && $selected->status === 'paid' ? 'completed' : (string) $selected->status;
                $selectedTypeLabel = $selectedIsOrder ? ($selected->kindLabel().' — '.$selected->product_name) : ($selected->type?->getName() ?? 'Payment');
                $selectedUserName = $selectedIsOrder ? $selected->full_name : ($selected->user?->name ?? 'Account unavailable');
                $checkoutUrl = $selectedIsOrder ? $selected->checkout_url : ($selected->meta['checkout_url'] ?? null);
                $reviewFlag = $selected->meta['manual_review'] ?? $selected->meta['reconcile_error'] ?? null;
                $checkoutStarted = filled($selected->gateway_reference)
                    || isset($selected->meta['manual_review'])
                    || isset($selected->meta['reconcile_error'])
                    || ($selectedIsOrder ? filled($selected->checkout_url) : filled($selected->meta['checkout_url'] ?? null));
                $canResolveSelected = in_array($selectedStatus, ['pending', 'failed'], true)
                    && ! $selectedHasLedger
                    && ($selectedStatus === 'pending' || $checkoutStarted);
            @endphp

            <x-backoffice.modal :show="$showDetail" title="Payment request review" closeAction="closeDetail" size="xl">
                <div class="flex flex-col gap-5">
                    @error('request')
                        <div role="alert" class="rounded-[12px] border border-danger/20 bg-danger/5 px-3.5 py-3 font-sans text-danger text-[12px]">{{ $message }}</div>
                    @enderror
                    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        <div class="rounded-[14px] bg-background p-3.5">
                            <p class="font-sans text-muted text-[10px] uppercase tracking-wide">Request</p>
                            <p class="mt-1 font-heading font-medium text-text text-[13px]">{{ $selectedTypeLabel }}</p>
                            <p class="mt-1 font-sans text-muted text-[11px] break-all">{{ $selected->reference }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background p-3.5">
                            <p class="font-sans text-muted text-[10px] uppercase tracking-wide">Payer</p>
                            <p class="mt-1 font-sans text-text text-[13px]">{{ $selectedUserName }}</p>
                            <p class="mt-1 font-sans text-muted text-[11px]">{{ $selectedIsOrder ? $selected->email : ($selected->user?->email ?? '—') }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background p-3.5">
                            <p class="font-sans text-muted text-[10px] uppercase tracking-wide">Requested amount</p>
                            <p class="mt-1 font-heading font-semibold text-text text-[17px] tabular-nums">
                                {{ $selectedExpectedCurrency !== '' ? $selectedExpectedCurrency : 'Currency missing' }}
                                {{ $selectedExpectedAmount > 0 ? number_format($selectedExpectedAmount, 2) : '—' }}
                            </p>
                            <p class="mt-1 font-sans text-muted text-[11px]">Channel: {{ $providers[$selected->provider] ?? \Illuminate\Support\Str::headline((string) $selected->provider) }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background p-3.5">
                            <p class="font-sans text-muted text-[10px] uppercase tracking-wide">Status</p>
                            <p class="mt-1"><x-backoffice.badge :tone="$selectedStatus === 'completed' ? 'success' : ($selectedStatus === 'pending' ? 'warning' : 'danger')">{{ ucfirst($selectedStatus) }}</x-backoffice.badge></p>
                            <p class="mt-1 font-sans text-muted text-[11px]">Created {{ $selected->created_at?->format('j M Y, g:i A') }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background p-3.5">
                            <p class="font-sans text-muted text-[10px] uppercase tracking-wide">Provider payment reference</p>
                            <p class="mt-1 font-sans text-text text-[12px] break-all">{{ $selected->gateway_reference ?: 'Not recorded' }}</p>
                        </div>
                        <div class="rounded-[14px] bg-background p-3.5">
                            <p class="font-sans text-muted text-[10px] uppercase tracking-wide">Checkout</p>
                            @if (filled($checkoutUrl) && str_starts_with((string) $checkoutUrl, 'https://'))
                                <a href="{{ $checkoutUrl }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1 font-sans text-primary text-[12px] underline">Open checkout <i class="ri-external-link-line"></i></a>
                            @else
                                <p class="mt-1 font-sans text-muted text-[12px]">No checkout link recorded</p>
                            @endif
                        </div>
                    </div>

                    @if ($reviewFlag)
                        <div class="rounded-[13px] border border-danger/20 bg-danger/5 px-4 py-3">
                            <p class="font-sans font-medium text-danger text-[12px]">Reconciliation flag: {{ \Illuminate\Support\Str::headline((string) ($reviewFlag['reason'] ?? 'amount/reference mismatch')) }}</p>
                            @if (! empty($reviewFlag['error']))
                                <p class="mt-1 font-sans text-muted text-[11px]">{{ $reviewFlag['error'] }}</p>
                            @elseif (isset($reviewFlag['amount'], $reviewFlag['currency']))
                                <p class="mt-1 font-sans text-muted text-[11px]">Provider reported {{ $reviewFlag['currency'] }} {{ number_format((float) $reviewFlag['amount'], 2) }}.</p>
                            @endif
                        </div>
                    @endif

                    @if ($selectedHasLedger)
                        <div class="rounded-[13px] border border-warning/25 bg-warning/10 px-4 py-3 font-sans text-[12px] leading-relaxed text-text">
                            <strong>Already recorded in the single-use payment ledger.</strong> This page will not grant it again. If the entitlement did not apply, use the normal support/refund reconciliation process; do not attempt another payment credit.
                        </div>
                    @endif

                    @if ($canResolveSelected && $canRecheck)
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-[14px] border border-primary/15 bg-primary/5 p-4">
                            <div>
                                <p class="font-sans font-medium text-text text-[13px]">Check the original payment channel</p>
                                <p class="mt-1 font-sans text-muted text-[11px]">Re-query {{ $providers[$selected->provider] ?? $selected->provider }}. A confirmed payment will complete through the existing settlement flow.</p>
                            </div>
                            <button type="button" wire:click="recheckSelected" wire:loading.attr="disabled" wire:target="recheckSelected"
                                    class="h-[40px] shrink-0 rounded-[11px] bg-primary px-4 font-sans font-medium text-white text-[12px] disabled:opacity-50">
                                <span wire:loading.remove wire:target="recheckSelected"><i class="ri-refresh-line mr-1"></i>Recheck provider</span>
                                <span wire:loading wire:target="recheckSelected"><i class="ri-loader-4-line mr-1 animate-spin"></i>Checking…</span>
                            </button>
                        </div>
                    @endif

                    @if ($canResolveSelected && $canManuallyResolve)
                        <details class="rounded-[14px] border border-warning/25 bg-warning/5">
                            <summary class="cursor-pointer list-none px-4 py-3.5 flex items-center justify-between gap-3">
                                <span>
                                    <span class="block font-sans font-medium text-text text-[13px]">Manual confirmation</span>
                                    <span class="mt-0.5 block font-sans text-muted text-[11px]">Super admin only — use only after checking official payment evidence.</span>
                                </span>
                                <i class="ri-arrow-down-s-line text-muted"></i>
                            </summary>
                            <div class="border-t border-warning/20 p-4 flex flex-col gap-3">
                                <div class="rounded-[11px] bg-white/60 px-3 py-2.5 font-sans text-[11px] leading-relaxed text-muted">
                                    This action attests that the payment was received. It will grant the purchase through the regular settlement path, and its provider reference can be used only once.
                                </div>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <label class="flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Verified amount ({{ $selectedExpectedCurrency ?: 'currency unavailable' }})
                                        <input type="number" min="0.01" step="0.01" wire:model="manualAmount" class="h-[40px] rounded-[10px] border border-muted/15 bg-white px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        @error('manualAmount') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Verified currency
                                        <input type="text" maxlength="3" wire:model="manualCurrency" class="h-[40px] rounded-[10px] border border-muted/15 bg-white px-3 font-sans text-[13px] uppercase text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        @error('manualCurrency') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Provider transaction / bank transfer reference
                                        <input type="text" maxlength="120" wire:model="manualGatewayReference" placeholder="Unique ID shown on provider or bank record" class="h-[40px] rounded-[10px] border border-muted/15 bg-white px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        @error('manualGatewayReference') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Evidence source
                                        <select wire:model="verificationSource" class="h-[40px] rounded-[10px] border border-muted/15 bg-white px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                            @foreach ($manualVerificationSources as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('verificationSource') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="sm:col-span-2 flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Evidence locator/reference
                                        <input type="text" maxlength="120" wire:model="evidenceReference" placeholder="Statement line, dashboard record, receipt ID, or secure internal ticket reference" class="h-[40px] rounded-[10px] border border-muted/15 bg-white px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        @error('evidenceReference') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="sm:col-span-2 flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Admin verification note
                                        <textarea rows="3" maxlength="1000" wire:model="manualNote" placeholder="What was checked and why this payment is being confirmed? Do not include card details or secrets." class="rounded-[10px] border border-muted/15 bg-white px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                                        @error('manualNote') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                </div>
                                <label class="flex items-start gap-2 font-sans text-[11px] leading-relaxed text-text">
                                    <input type="checkbox" wire:model="manualAttestation" class="mt-0.5 rounded border-muted/30 text-primary focus:ring-primary/40">
                                    <span>I checked official provider/bank evidence for this exact reference and amount. I understand this confirmation will complete the purchase.</span>
                                </label>
                                @error('manualAttestation') <span class="font-sans text-danger text-[11px]">{{ $message }}</span> @enderror
                                @error('manualConfirmation') <span class="font-sans text-danger text-[11px]">{{ $message }}</span> @enderror
                                <button type="button" wire:click="confirmManually" wire:loading.attr="disabled" wire:target="confirmManually"
                                        class="h-[42px] self-start rounded-[11px] bg-warning px-4 font-sans font-medium text-text text-[12px] disabled:opacity-50">
                                    <span wire:loading.remove wire:target="confirmManually"><i class="ri-shield-check-line mr-1"></i>Confirm payment and complete</span>
                                    <span wire:loading wire:target="confirmManually"><i class="ri-loader-4-line mr-1 animate-spin"></i>Completing…</span>
                                </button>
                            </div>
                        </details>
                    @endif

                    @if ($selectedStatus === 'pending' && $canManuallyResolve && ! $selectedHasLedger)
                        <div class="rounded-[14px] border border-muted/15 p-4">
                            @if (! $showCloseForm)
                                <button type="button" wire:click="toggleCloseForm" class="font-sans text-muted text-[12px] underline decoration-muted/40 underline-offset-2">Close as unpaid…</button>
                            @else
                                <div class="flex flex-col gap-3">
                                    <div class="font-sans text-danger text-[12px] font-medium">This permanently closes the pending request without granting its purchase.</div>
                                    <label class="flex flex-col gap-1 font-sans text-[11px] text-muted">
                                        Reason for closing
                                        <textarea rows="2" maxlength="1000" wire:model="closeNote" placeholder="Record what you checked and why no successful payment was found." class="rounded-[10px] border border-muted/15 bg-background px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                                        @error('closeNote') <span class="text-danger">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="flex items-start gap-2 font-sans text-[11px] leading-relaxed text-text">
                                        <input type="checkbox" wire:model="closeAttestation" class="mt-0.5 rounded border-muted/30 text-primary focus:ring-primary/40">
                                        <span>I checked the relevant channel and found no successful payment for this request.</span>
                                    </label>
                                    @error('closeAttestation') <span class="font-sans text-danger text-[11px]">{{ $message }}</span> @enderror
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="closeAsUnpaid" wire:loading.attr="disabled" class="h-[38px] rounded-[10px] bg-danger px-3 font-sans text-white text-[12px] disabled:opacity-50">Close request</button>
                                        <button type="button" wire:click="toggleCloseForm" class="h-[38px] rounded-[10px] bg-background px-3 font-sans text-muted text-[12px]">Cancel</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-[14px] border border-muted/10 overflow-hidden">
                        <div class="border-b border-muted/10 bg-background px-4 py-3 flex items-center justify-between gap-3">
                            <p class="font-sans font-medium text-text text-[12px]">Resolution history</p>
                            <span class="font-sans text-muted text-[10px]">{{ $selected->paymentResolutionLogs->count() }} recorded action(s)</span>
                        </div>
                        @if ($selected->paymentResolutionLogs->isEmpty())
                            <p class="p-4 font-sans text-muted text-[11px]">No backoffice review actions recorded yet.</p>
                        @else
                            <div class="divide-y divide-muted/10 max-h-[230px] overflow-y-auto">
                                @foreach ($selected->paymentResolutionLogs as $entry)
                                    @php $outcome = $entry->details['outcome'] ?? null; @endphp
                                    <div wire:key="resolution-{{ $entry->id }}" class="px-4 py-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-sans font-medium text-text text-[12px]">{{ \Illuminate\Support\Str::headline($entry->action) }} <span class="font-normal text-muted">{{ $outcome ? '· '.\Illuminate\Support\Str::headline($outcome) : '' }}</span></p>
                                            <p class="font-sans text-muted text-[10px]">{{ $entry->resolved_at?->format('j M Y, g:i A') }}</p>
                                        </div>
                                        <p class="mt-1 font-sans text-muted text-[10px]">{{ $entry->admin?->name ?? 'Admin account unavailable' }} · {{ ucfirst((string) $entry->from_status) }} → {{ ucfirst((string) $entry->to_status) }}</p>
                                        @if ($entry->verification_source)
                                            <p class="mt-1 font-sans text-muted text-[10px]">Source: {{ $verificationSources[$entry->verification_source] ?? \Illuminate\Support\Str::headline($entry->verification_source) }} @if ($entry->evidence_reference) · Evidence: {{ $entry->evidence_reference }} @endif</p>
                                        @endif
                                        @if ($entry->gateway_reference)
                                            <p class="mt-1 font-sans text-muted text-[10px] break-all">Payment ref: {{ $entry->gateway_reference }}</p>
                                        @endif
                                        @if ($entry->amount)
                                            <p class="mt-1 font-sans text-muted text-[10px]">Verified: {{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}</p>
                                        @endif
                                        @if ($entry->note)
                                            <p class="mt-1 font-sans text-text text-[11px] leading-relaxed">{{ $entry->note }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </x-backoffice.modal>
        @endif
    </x-backoffice.page>
</div>
