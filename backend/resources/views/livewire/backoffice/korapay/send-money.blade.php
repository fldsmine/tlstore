@include('livewire.backoffice.payment-manager.send-money', [
    'manager' => 'Korapay',
    'routePrefix' => 'backoffice.korapay',
    'managerConfigured' => $managerConfigured,
    'providerNote' => 'Korapay sends the payout from your NGN wallet balance. A payout that gets no definitive answer is stored as unverified — check it with the reference instead of sending it again.',
])
