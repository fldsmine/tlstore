@include('livewire.backoffice.payment-manager.send-money', [
    'manager' => 'Flutterwave',
    'routePrefix' => 'backoffice.flutterwave',
    'managerConfigured' => $managerConfigured,
    'providerNote' => 'Flutterwave queues the transfer and moves the money on its own schedule — a brand-new transfer starts as NEW and is not final. Pay attention to any approval or 2FA requirement on the Flutterwave dashboard: a transfer waiting for approval has not left the wallet yet.',
])
