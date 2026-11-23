<div>
    <x-backoffice.page title="Cloud Storage Plans" subtitle="Storage — extra contacts users buy with a token, on top of their package allowance">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[340px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search plans…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            @if ($canWrite)
                <button type="button" wire:click="openCreate" aria-label="Add plan" class="shrink-0 inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]"><i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add plan</span></button>
            @endif
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($plans->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No storage plans yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[860px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Name</th>
                                <th class="px-5 py-3 font-medium">Token price</th>
                                <th class="px-5 py-3 font-medium">Vendor price</th>
                                <th class="px-5 py-3 font-medium">Contacts</th>
                                <th class="px-5 py-3 font-medium">SMS/day</th>
                                <th class="px-5 py-3 font-medium">Tier</th>
                                <th class="px-5 py-3 font-medium">Foreign price</th>
                                <th class="px-5 py-3 font-medium">Foreign agent</th>
                                <th class="px-5 py-3 font-medium">Validity</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr wire:key="plan-{{ $plan->id }}" @if ($canWrite) wire:click="edit('{{ $plan->id }}')" @endif class="border-b border-muted/5 last:border-0 @if ($canWrite) hover:bg-background/50 cursor-pointer @endif transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-sans text-text text-[13px]">{{ $plan->name }}</span>
                                        </div>
                                        @if ($plan->description)<div class="font-sans text-muted text-[12px] mt-0.5">{{ $plan->description }}</div>@endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] tabular-nums">{{ money($plan->token_price) }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">{{ money($plan->agent_price) }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">+{{ $plan->extra_contacts }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">+{{ $plan->extra_sms }}</td>
                                    <td class="px-5 py-3">
                                        @if ($plan->tier_level !== null)
                                            <x-backoffice.badge tone="purple">{{ $tierOptions[(string) $plan->tier_level] ?? 'Tier '.$plan->tier_level }} · Special</x-backoffice.badge>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] tabular-nums">{{ $plan->foreign_token_price === null ? '—' : money($plan->foreign_token_price) }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">{{ $plan->foreign_agent_price === null ? '—' : money($plan->foreign_agent_price) }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">{{ (int) $plan->duration_days === 0 ? 'No expiry' : $plan->duration_days.' days' }}</td>
                                    <td class="px-5 py-3">
                                        @if ($plan->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($plans->hasPages())<div>{{ $plans->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit plan' : 'Add plan'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Plan name</label>
                    <input type="text" wire:model="name" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Description</label>
                    <input type="text" wire:model="description" placeholder="Optional" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('description') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Token price ($)</label>
                        <input type="number" step="0.01" min="0" wire:model="tokenPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('tokenPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Vendor price ($)</label>
                        <input type="number" step="0.01" min="0" wire:model="agentPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('agentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Storage — extra contacts</label>
                        <input type="number" step="1" min="0" wire:model="extraContacts" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('extraContacts') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Storage — extra SMS / day</label>
                        <input type="number" step="1" min="0" wire:model="extraSms" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('extraSms') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Upline Connect Bonus ($)</label>
                        <input type="number" step="any" min="0" wire:model="referralBonus" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('referralBonus') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Foreign price ($)</label>
                        <input type="number" step="0.01" min="0" wire:model="foreignTokenPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('foreignTokenPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Foreign vendor price ($)</label>
                        <input type="number" step="0.01" min="0" wire:model="foreignAgentPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('foreignAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Validity (days)</label>
                        <input type="number" step="1" min="0" wire:model="durationDays" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('durationDays') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="rounded-[12px] bg-background/60 border border-muted/10 p-3 flex flex-col gap-3">
                    <p class="font-sans text-[12px] text-muted">Renewal pricing — set a renewal price to make this plan renewable (vendors can then mint renewal codes). Leave at 0 for no renewal. Not allowed on lifetime (0-day) plans.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Renewal price ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="renewalTokenPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @error('renewalTokenPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Renewal vendor price ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="renewalAgentPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @error('renewalAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Foreign renewal price ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="foreignRenewalTokenPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @error('foreignRenewalTokenPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Foreign renewal vendor price ($)</label>
                            <input type="number" step="0.01" min="0" wire:model="foreignRenewalAgentPrice" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @error('foreignRenewalAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Sort order</label>
                        <input type="number" step="1" min="0" wire:model="sortOrder" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text tabular-nums focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('sortOrder') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Package tier (special coupon)</label>
                        <select wire:model="tierLevel" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                            <option value="">None — regular purchasable plan</option>
                            @foreach ($tierOptions as $tier => $label)
                                <option value="{{ $tier }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('tierLevel') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <p class="font-sans text-muted text-[11px] -mt-1">A pack grants <span class="text-text">extra Cloud Storage</span> on top of the base package allowance. Vendors buy tokens at the <span class="text-text">vendor price</span>; users redeem them at the <span class="text-text">token price</span>. <span class="text-text">Foreign</span> prices apply to codes minted by vendors outside Nigeria — leave blank to reuse the Nigerian pair. <span class="text-text">Validity</span> 0 = never expires. Assign a <span class="text-text">package tier</span> to make this a special coupon plan — it becomes coupon-only (not directly purchasable) and vendor codes minted for it activate tier storage and authorize N-Cloud withdrawals.</p>
                <div class="flex flex-col gap-2.5 rounded-[14px] border border-muted/12 p-3.5">
                    <x-backoffice.toggle wire:model="isActive" label="Active" />
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
    </x-backoffice.page>
</div>
