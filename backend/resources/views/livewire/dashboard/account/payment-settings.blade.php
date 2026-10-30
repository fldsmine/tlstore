<x-app.settings-shell title="Payment Settings" :back="route('profile')" :card="false">
    <livewire:dashboard.account.bank-account-form :key="'bank-account'" />
</x-app.settings-shell>
