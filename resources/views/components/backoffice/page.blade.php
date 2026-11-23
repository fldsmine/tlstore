@props(['title' => null, 'subtitle' => null, 'back' => null])

<div class="min-h-screen bg-background">
    <div class="w-full px-4 md:px-8 lg:px-10 pb-24 lg:pb-10 flex flex-col gap-6">
        <x-backoffice.app-bar :title="$title" :subtitle="$subtitle" :back="$back">
            <x-slot:action>{{ $action ?? '' }}</x-slot:action>
        </x-backoffice.app-bar>

        {{ $slot }}
    </div>
</div>
