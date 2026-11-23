@include('livewire.backoffice.payment-manager.send-money', [
    'manager' => 'Bachs',
    'routePrefix' => 'backoffice.bachs',
    'managerConfigured' => $managerConfigured,
    'providerNote' => 'Bachs pays a registered payout destination: the account on this form is matched against the saved destinations (or registered as a new one) and the payout is funded from that currency\'s own Bachs balance. A destination that is still under review is refused — nothing is sent until it is usable. A payout that gets no definitive answer is stored as unverified: check it with the reference instead of sending it again.',
])
