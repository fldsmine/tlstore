{{-- Account sub-navigation (profile / password). Pass :active="'<key>'" so the highlight
     survives Livewire re-renders, mirroring <x-backoffice.settings-tabs>. --}}
@props(['active' => null])
@php
    $tabs = [
        ['key' => 'profile', 'label' => 'Profile', 'route' => 'backoffice.account.profile', 'icon' => 'ri-user-3-line'],
        ['key' => 'password', 'label' => 'Change Password', 'route' => 'backoffice.account.password', 'icon' => 'ri-lock-2-line'],
    ];
@endphp
<div class="flex items-center gap-1.5 flex-wrap">
    @foreach ($tabs as $tab)
        @php $isActive = $active ? ($active === $tab['key']) : request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}" wire:navigate
           class="inline-flex items-center gap-1.5 px-3.5 h-[36px] rounded-full font-sans text-[12px] transition {{ $isActive ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
            <i class="{{ $tab['icon'] }}"></i> {{ $tab['label'] }}
        </a>
    @endforeach
</div>
