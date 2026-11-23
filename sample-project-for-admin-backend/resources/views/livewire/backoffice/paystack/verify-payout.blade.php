@include('livewire.backoffice.payment-manager.verify-payout', [
    'manager' => 'Paystack',
    'routePrefix' => 'backoffice.paystack',
    'identifierLabel' => 'Transfer code',
    'referencePlaceholder' => 'PSK-… or a Paystack TRF_… code',
    'referenceHint' => 'Paste any payout reference or Paystack transfer code.',
])
