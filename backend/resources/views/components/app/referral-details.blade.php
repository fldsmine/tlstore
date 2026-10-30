{{-- Team-member bottom-sheet; rendered inside Invite (exposes closeMember()), shows only when `member` is set. --}}
@props(['member' => null, 'level' => null, 'upline' => null])

@if ($member)
    @php
        $active = (bool) $member->package_id;
        $initials = strtoupper(substr($member->first_name ?: $member->username, 0, 2));
    @endphp
    <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4"
         x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeMember"></div>

        <div class="relative w-full sm:max-w-[400px] bg-surface rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-8 flex flex-col gap-5"
             x-show="shown"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

            <div class="flex items-center justify-between">
                <p class="font-heading font-medium text-text text-[16px]">Team Member</p>
                <button type="button" wire:click="closeMember" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>
            </div>

            <div class="flex flex-col items-center gap-2.5 py-1">
                <span class="size-16 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center">
                    @if ($member->avatar)
                        <img src="{{ $member->avatar }}" alt="" class="size-full object-cover">
                    @else
                        <span class="font-heading font-semibold text-primary text-[20px]">{{ $initials }}</span>
                    @endif
                </span>
                <p class="font-heading font-medium text-text text-[18px] leading-none">{{ $member->full_name }}</p>
                <p class="font-sans text-muted text-[13px]">&#64;{{ $member->username }}</p>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 {{ $active ? 'bg-success/15 text-success' : 'bg-[#d79937]/15 text-[#d79937]' }}">
                    <span class="size-1.5 rounded-full {{ $active ? 'bg-success' : 'bg-[#d79937]' }}"></span>
                    <span class="font-sans text-[12px]">{{ $active ? 'Active' : 'Pending activation' }}</span>
                </span>
            </div>

            <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                @foreach (array_filter([
                    ['Level', $level],
                    $upline ? ['Joined via', '@'.$upline->username] : null,
                    // The upline needs a way to actually reach their downline.
                    ['Email', $member->email],
                    ['Phone', $member->phone],
                    ['Package', $member->package?->name ?? 'No package yet'],
                    ['Joined', $member->created_at?->format('j M Y, g:i A')],
                ]) as [$label, $value])
                    @if ($value)
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                            <span class="font-sans text-text text-[13px] text-right break-all select-all">{{ $value }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

            @unless ($active)
                <p class="font-sans text-[12px] text-muted text-center -mt-1">You earn a Direct Connect Bonus once they activate their eSIM.</p>
            @endunless

            <button type="button" wire:click="closeMember" class="h-[50px] rounded-full bg-primary text-surface font-sans text-[15px] hover:opacity-95 transition">Done</button>
        </div>
    </div>
@endif
