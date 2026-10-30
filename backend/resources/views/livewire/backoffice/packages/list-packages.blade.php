<div>
    <x-backoffice.page title="Packages" subtitle="Membership tiers & the call types available under each">
        @php
            $canWrite = $this->canWrite();
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        @if ($canWrite)
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="Add package"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add package</span>
                </button>
            </x-slot:action>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($packages->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No packages yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[920px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Package</th>
                                <th class="px-5 py-3 font-medium text-right">Tier</th>
                                <th class="px-5 py-3 font-medium text-right">Price</th>
                                <th class="px-5 py-3 font-medium text-right">Agent</th>
                                <th class="px-5 py-3 font-medium text-right">Daily mins</th>
                                <th class="px-5 py-3 font-medium text-right">Call IDs</th>
                                <th class="px-5 py-3 font-medium text-right">Call types</th>
                                <th class="px-5 py-3 font-medium text-right">Users</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($packages as $p)
                                <tr wire:key="pkg-{{ $p->id }}" wire:click="openDetail('{{ $p->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-sans text-text text-[13px]">{{ $p->name }}</span>
                                            @if ($p->is_featured)<x-backoffice.badge tone="info">Popular</x-backoffice.badge>@endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ $p->tier_level }}</td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] whitespace-nowrap">{{ money($p->price) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ money($p->agent_price) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $p->daily_call_minutes, 2), '0'), '.') }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ $p->call_id_limit }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ number_format($p->call_types_count) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ number_format($p->users_count) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($p->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit package' : 'Add package'" size="lg">
            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Name</label>
                    <input type="text" wire:model="name" class="{{ $input }}">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ([['price','Price ($)'],['agentPrice','Agent price ($)'],['foreignPrice','Foreign price ($)'],['foreignAgentPrice','Foreign agent price ($)'],['directReferralBonus','Direct ref bonus ($)'],['indirectReferralBonus','Indirect ref bonus ($)'],['upgradePrice','Upgrade price ($)'],['upgradeAgentPrice','Upgrade agent price ($)'],['foreignUpgradePrice','Foreign upgrade price ($)'],['foreignUpgradeAgentPrice','Foreign upgrade agent price ($)'],['upgradeDirectReferralBonus','Upgrade direct ref bonus ($)'],['upgradeIndirectReferralBonus','Upgrade indirect ref bonus ($)'],['dailyCallMinutes','Daily eSIM call minutes'],['monthlyShareMinutes','Monthly share minutes'],['shareTermDays','Share term (days)'],['maxDailyShareholderEarning','Daily share earning cap ($) — 0 disables claims'],['callIdLimit','Call ID limit'],['tierLevel','Tier level']] as [$field,$label])
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">{{ $label }}</label>
                            <input type="number" step="any" wire:model="{{ $field }}" class="{{ $input }}">
                            @error($field) <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>
                <p class="font-sans text-[11px] text-muted -mt-1">Monthly share minutes are prorated over the configured term (monthly minutes × term days ÷ 30); the daily eSIM call allowance above is separate. When the daily share cap is positive, saving raises the quota if needed to make that cap reachable through an active included call type. Set the cap to zero to disable share claims. New terms start when a package is activated, upgraded, or renewed.</p>

                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <label class="font-sans text-muted text-[12px]">Available call types</label>
                        <span class="font-sans text-[11px] text-muted">Blank uses the call type's base; share-pool payouts use that base rate</span>
                    </div>
                    <div class="rounded-[14px] bg-background divide-y divide-muted/10">
                        @foreach ($allCallTypes as $ct)
                            <div wire:key="cfg-{{ $ct->id }}" class="flex items-center gap-3 px-3.5 py-2.5">
                                <span class="size-8 rounded-full flex items-center justify-center text-white shrink-0" style="background: {{ $ct->color ?: '#18443E' }}"><i class="ri-phone-line text-[13px]"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-sans text-text text-[13px] truncate">{{ $ct->name }}</p>
                                    <p class="font-sans text-muted text-[11px] truncate">Base {{ money($ct->rate_per_minute) }}/min · {{ rtrim(rtrim(number_format((float) $ct->claimable_percentage, 2), '0'), '.') }}%</p>
                                </div>
                                <input type="number" step="any" wire:model="callTypeConfig.{{ $ct->id }}.rate" placeholder="Rate"
                                       class="h-[38px] w-[78px] rounded-[10px] bg-card border border-muted/15 px-2 font-sans text-[12px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                <input type="number" step="any" wire:model="callTypeConfig.{{ $ct->id }}.claim" placeholder="%"
                                       class="h-[38px] w-[64px] rounded-[10px] bg-card border border-muted/15 px-2 font-sans text-[12px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                <x-backoffice.toggle wire:model="callTypeConfig.{{ $ct->id }}.enabled" aria-label="Enable {{ $ct->name }}" />
                            </div>
                        @endforeach
                    </div>
                    @error('callTypeConfig.*.rate') <span class="font-sans text-[12px] text-danger">Override rates must be valid amounts.</span> @enderror
                    @error('callTypeConfig.*.claim') <span class="font-sans text-[12px] text-danger">Override percentages must be between 0 and 100.</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Features (one per line)</label>
                    <textarea wire:model="features" rows="3" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Activation message — shown after this package is redeemed (blank uses the generic copy)</label>
                    <textarea wire:model="successMessage" rows="2" placeholder="Congratulations! Your Royal eSIM has been successfully activated!…" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                    @error('successMessage') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>

                {{-- Cloud gate, then the capabilities it unlocks. Diamond = SMS only; Royal = the lot. --}}
                <div class="rounded-[14px] bg-background p-3.5 flex flex-col gap-3">
                    <x-backoffice.toggle wire:model="enableCloud" label="Enable Nextel Cloud" description="Users on this package can open Nextel Cloud at all." />
                    <div x-show="$wire.enableCloud" x-cloak class="border-l-2 border-muted/15 ml-1 pl-3 flex flex-col gap-3">
                        <x-backoffice.toggle wire:model="cloudSms" label="Read Cloud messages" description="Users can read sponsored messages in the Cloud inbox and claim their rewards." />
                        <x-backoffice.toggle wire:model="cloudCalls" label="Cloud calling & contacts" description="Users can save sponsor contacts and earn from sponsored calls." />

                        <div x-show="$wire.cloudSms" x-cloak class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">SMS / day — 0 = no cap</label>
                                <input type="number" step="1" min="0" wire:model="cloudSmsLimit" placeholder="10" class="{{ $input }}">
                                @error('cloudSmsLimit') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div x-show="$wire.cloudCalls" x-cloak class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Cloud Storage</label>
                                <input type="number" step="1" min="0" wire:model="cloudContactLimit" placeholder="10" class="{{ $input }}">
                                @error('cloudContactLimit') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Max earning / day ($) — 0 = no cap</label>
                                <input type="number" step="0.01" min="0" wire:model="cloudDailyEarningCap" placeholder="5" class="{{ $input }}">
                                @error('cloudDailyEarningCap') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Max earning / hour ($) — 0 = no cap</label>
                                <input type="number" step="0.01" min="0" wire:model="cloudHourlyEarningCap" placeholder="1" class="{{ $input }}">
                                @error('cloudHourlyEarningCap') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <p class="font-sans text-muted text-[11px]">Users buy extra Cloud Storage as packs (Backoffice → Storage Plans).</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-backoffice.toggle wire:model="isActive" label="Active" />
                    <x-backoffice.toggle wire:model="isFeatured" label="Popular" />
                </div>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showDetail" title="Manage package" closeAction="$set('showDetail', false)">
            @if ($detailPackage)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-vip-crown-2-line text-[22px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px] truncate">{{ $detailPackage->name }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">Tier {{ $detailPackage->tier_level }} · {{ money($detailPackage->price) }} · {{ number_format($detailPackage->users_count) }} user{{ $detailPackage->users_count === 1 ? '' : 's' }}</p>
                            <div class="mt-1.5 flex items-center gap-1.5">
                                @if ($detailPackage->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                @if ($detailPackage->is_featured)<x-backoffice.badge tone="info">Popular</x-backoffice.badge>@endif
                            </div>
                        </div>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Price', money($detailPackage->price)],
                            ['Agent price', money($detailPackage->agent_price)],
                            ['Direct referral bonus', money($detailPackage->direct_referral_bonus)],
                            ['Indirect referral bonus', money($detailPackage->indirect_referral_bonus)],
                            ['Nextel Cloud', $detailPackage->enable_cloud
                                ? collect([$detailPackage->cloud_calls ? 'calling + contacts' : null, $detailPackage->cloud_sms ? 'messages' : null])->filter()->implode(', ') ?: 'enabled (no capabilities)'
                                : 'No'],
                            ['Cloud Storage', $detailPackage->cloud_calls ? $detailPackage->cloud_contact_limit : '—'],
                            ['SMS / day', $detailPackage->cloud_sms ? ($detailPackage->cloud_sms_limit > 0 ? $detailPackage->cloud_sms_limit : 'No cap') : '—'],
                            ['Foreign price', $detailPackage->foreign_price === null ? 'Same as Nigerian' : money($detailPackage->foreign_price)],
                            ['Foreign agent price', $detailPackage->foreign_agent_price === null ? 'Same as Nigerian' : money($detailPackage->foreign_agent_price)],
                            ['Daily eSIM call minutes', rtrim(rtrim(number_format((float) $detailPackage->daily_call_minutes, 2), '0'), '.')],
                            ['Monthly share minutes', rtrim(rtrim(number_format((float) $detailPackage->monthly_share_minutes, 2), '0'), '.')],
                            ['Share term', $detailPackage->share_term_days.' days'],
                            ['Daily share earning cap', (float) $detailPackage->max_daily_shareholder_earning > 0 ? money($detailPackage->max_daily_shareholder_earning) : 'Claims disabled (not configured)'],
                            ['Call ID limit', (string) $detailPackage->call_id_limit],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-2">
                        <p class="font-sans text-muted text-[12px]">Call types available under it</p>
                        @if ($detailPackage->callTypes->isEmpty())
                            <p class="font-sans text-muted text-[13px] rounded-[14px] bg-background px-4 py-3">No call types available under this package.</p>
                        @else
                            <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                                @foreach ($detailPackage->callTypes->where('pivot.is_available', true) as $ct)
                                    @php
                                        $rate = $ct->pivot->rate_per_minute ?? $ct->rate_per_minute;
                                        $claim = $ct->pivot->claimable_percentage ?? $ct->claimable_percentage;
                                        $overridden = $ct->pivot->rate_per_minute !== null || $ct->pivot->claimable_percentage !== null;
                                    @endphp
                                    <div class="flex items-center gap-3 px-4 py-3">
                                        <span class="size-8 rounded-full flex items-center justify-center text-white shrink-0" style="background: {{ $ct->color ?: '#18443E' }}"><i class="ri-phone-line text-[13px]"></i></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-sans text-text text-[13px] truncate">{{ $ct->name }}</p>
                                            <p class="font-sans text-muted text-[11px] truncate">{{ money($rate) }}/min · {{ rtrim(rtrim(number_format((float) $claim, 2), '0'), '.') }}%</p>
                                        </div>
                                        @if ($overridden)<x-backoffice.badge tone="info">Override</x-backoffice.badge>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($canWrite)
                        <div class="flex flex-col gap-2">
                            <button type="button" wire:click="edit('{{ $detailPackage->id }}')"
                                    class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                                <i class="ri-edit-2-line text-primary text-[18px]"></i>
                                <span class="flex-1 font-sans text-text text-[13px]">Edit details & call types</span>
                                <i class="ri-arrow-right-s-line text-muted"></i>
                            </button>
                            <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleActive', params: ['{{ $detailPackage->id }}'], title: '{{ $detailPackage->is_active ? 'Disable package' : 'Enable package' }}', message: '{{ $detailPackage->is_active ? 'Users will no longer be able to buy this package.' : 'This package will become available for users to buy.' }}', confirmText: '{{ $detailPackage->is_active ? 'Disable' : 'Enable' }}', tone: '{{ $detailPackage->is_active ? 'warning' : 'primary' }}' })"
                                    class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                                <i class="{{ $detailPackage->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }} text-primary text-[18px]"></i>
                                <span class="flex-1 font-sans text-text text-[13px]">{{ $detailPackage->is_active ? 'Disable package' : 'Enable package' }}</span>
                                <i class="ri-arrow-right-s-line text-muted"></i>
                            </button>
                            @if ($detailPackage->users_count === 0)
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $detailPackage->id }}'], title: 'Delete package', message: 'This package will be permanently removed.', confirmText: 'Delete', tone: 'danger' })"
                                        class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                                    <i class="ri-delete-bin-6-line text-danger text-[18px]"></i>
                                    <span class="flex-1 font-sans text-danger text-[13px]">Delete package</span>
                                    <i class="ri-arrow-right-s-line text-danger/60"></i>
                                </button>
                            @else
                                <p class="font-sans text-[12px] text-muted px-1">Can't delete — users hold this package. Disable it instead.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
