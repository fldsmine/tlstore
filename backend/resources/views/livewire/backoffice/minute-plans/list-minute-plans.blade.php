<div>
    <x-backoffice.page title="Minute Plans" subtitle="Additional-minute (top-up) plans">
        @php
            $canWrite = $this->canWrite();
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        @if ($canWrite)
            <x-slot:action>
                <button type="button" wire:click="openCreate" aria-label="Add plan"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add plan</span>
                </button>
            </x-slot:action>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($plans->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No plans yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[820px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Plan</th>
                                <th class="px-5 py-3 font-medium text-right">Price</th>
                                <th class="px-5 py-3 font-medium text-right">Agent</th>
                                <th class="px-5 py-3 font-medium text-right">Daily mins</th>
                                <th class="px-5 py-3 font-medium text-right">Duration</th>
                                <th class="px-5 py-3 font-medium text-right">Ref bonus</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plans as $p)
                                <tr wire:key="mp-{{ $p->id }}" wire:click="openDetail('{{ $p->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-sans text-text text-[13px]">{{ $p->name }}</span>
                                            @if ($p->is_featured)<x-backoffice.badge tone="info">Best Value</x-backoffice.badge>@endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] whitespace-nowrap">{{ money($p->price) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ money($p->agent_price) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $p->daily_call_minutes, 2), '0'), '.') }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ $p->duration_days }}d</td>
                                    <td class="px-5 py-3 text-right font-heading text-success text-[13px] whitespace-nowrap">{{ money($p->referral_bonus) }}</td>
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

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit plan' : 'Add plan'">
            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Name</label>
                    <input type="text" wire:model="name" class="{{ $input }}">
                    @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['price','Price ($)'],['agentPrice','Agent price ($)'],['foreignPrice','Foreign price ($)'],['foreignAgentPrice','Foreign agent price ($)'],['dailyCallMinutes','Daily eSIM call minutes'],['shareMinutes','Share minutes added to active term'],['durationDays','eSIM top-up duration (days)'],['paylaterDurationDays','Pay-later days'],['minEarningRate','Min earning rate'],['callIdBonus','Call ID bonus'],['referralBonus','Referral bonus ($)']] as [$field,$label])
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">{{ $label }}</label>
                            <input type="number" step="any" wire:model="{{ $field }}" class="{{ $input }}">
                            @error($field) <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>
                <p class="font-sans text-[11px] text-muted -mt-1">eSIM daily-minute boosts expire after the selected duration. Share-minute additions are attached to the active term and expire at the earlier of the plan duration or term end; they do not carry into a renewal or upgrade. Referral bonus is paid to the buyer's direct upline only (no indirect bonus). <span class="text-text">Foreign</span> prices apply to codes minted by vendors outside Nigeria — leave blank to reuse the Nigerian pair.</p>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Features (one per line)</label>
                    <textarea wire:model="features" rows="3" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-backoffice.toggle wire:model="isActive" label="Active" />
                    <x-backoffice.toggle wire:model="isFeatured" label="Best Value" />
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

        <x-backoffice.modal :show="$showDetail" title="Manage plan" closeAction="$set('showDetail', false)">
            @if ($detailPlan)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-add-circle-line text-[22px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px] truncate">{{ $detailPlan->name }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">{{ money($detailPlan->price) }} · {{ rtrim(rtrim(number_format((float) $detailPlan->daily_call_minutes, 2), '0'), '.') }} daily mins</p>
                            <div class="mt-1.5 flex items-center gap-1.5">
                                @if ($detailPlan->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                @if ($detailPlan->is_featured)<x-backoffice.badge tone="info">Best Value</x-backoffice.badge>@endif
                            </div>
                        </div>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Price', money($detailPlan->price)],
                            ['Agent price', money($detailPlan->agent_price)],
                            ['Foreign price', $detailPlan->foreign_price === null ? 'Same as Nigerian' : money($detailPlan->foreign_price)],
                            ['Foreign agent price', $detailPlan->foreign_agent_price === null ? 'Same as Nigerian' : money($detailPlan->foreign_agent_price)],
                            ['Direct referral bonus', money($detailPlan->referral_bonus)],
                            ['Daily call minutes', rtrim(rtrim(number_format((float) $detailPlan->daily_call_minutes, 2), '0'), '.')],
                            ['Duration', $detailPlan->duration_days.' days'],
                            ['Pay-later window', $detailPlan->paylater_duration_days.' days'],
                            ['Min earning rate', money($detailPlan->min_earning_rate)],
                            ['Call ID bonus', (string) $detailPlan->call_id_bonus],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-2">
                        <button type="button" wire:click="edit('{{ $detailPlan->id }}')"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-edit-2-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Edit details</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleActive', params: ['{{ $detailPlan->id }}'], title: '{{ $detailPlan->is_active ? 'Disable plan' : 'Enable plan' }}', message: '{{ $detailPlan->is_active ? 'Users will no longer be able to buy this plan.' : 'This plan will become available to buy again.' }}', confirmText: '{{ $detailPlan->is_active ? 'Disable' : 'Enable' }}', tone: '{{ $detailPlan->is_active ? 'warning' : 'primary' }}' })"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="{{ $detailPlan->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }} text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">{{ $detailPlan->is_active ? 'Disable plan' : 'Enable plan' }}</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'delete', params: ['{{ $detailPlan->id }}'], title: 'Delete plan', message: 'This plan will be permanently removed.', confirmText: 'Delete', tone: 'danger' })"
                                class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                            <i class="ri-delete-bin-6-line text-danger text-[18px]"></i>
                            <span class="flex-1 font-sans text-danger text-[13px]">Delete plan</span>
                            <i class="ri-arrow-right-s-line text-danger/60"></i>
                        </button>
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
