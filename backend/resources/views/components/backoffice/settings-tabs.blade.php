{{-- Pass :active="'<key>'" so the highlight survives Livewire re-renders: a save posts to
     /livewire/update where request()->routeIs() no longer matches. Falls back to route detection on full load. --}}
@props(['active' => null])
@php
    $tabs = [
        ['key' => 'general', 'label' => 'General', 'route' => 'backoffice.settings.general', 'icon' => 'ri-settings-3-line'],
        ['key' => 'withdrawal', 'label' => 'Withdrawals', 'route' => 'backoffice.settings.withdrawal', 'icon' => 'ri-bank-card-line'],
        ['key' => 'features', 'label' => 'Features', 'route' => 'backoffice.settings.features', 'icon' => 'ri-toggle-line'],
        ['key' => 'services', 'label' => 'Services', 'route' => 'backoffice.settings.services', 'icon' => 'ri-plug-line'],
        ['key' => 'tokens', 'label' => 'Tokens', 'route' => 'backoffice.settings.tokens', 'icon' => 'ri-coupon-3-line'],
        ['key' => 'vtu', 'label' => 'Bill Payments', 'route' => 'backoffice.settings.vtu', 'icon' => 'ri-shopping-bag-line'],
        ['key' => 'cloud', 'label' => 'Cloud', 'route' => 'backoffice.settings.cloud', 'icon' => 'ri-cloud-line'],
        ['key' => 'chat', 'label' => 'Live Chat', 'route' => 'backoffice.settings.chat', 'icon' => 'ri-chat-smile-2-line'],
        ['key' => 'backup', 'label' => 'Backups', 'route' => 'backoffice.settings.backup', 'icon' => 'ri-database-2-line'],
        ['key' => 'analytics', 'label' => 'Analytics', 'route' => 'backoffice.settings.analytics', 'icon' => 'ri-line-chart-line'],
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
