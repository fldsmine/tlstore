@include('livewire.backoffice.payment-manager.verify-payout', [
    'manager' => 'Flutterwave',
    'routePrefix' => 'backoffice.flutterwave',
    'identifierLabel' => 'Transfer ID',
    'referencePlaceholder' => 'FLW-… or a Flutterwave transfer id',
    'referenceHint' => 'Paste any payout reference or transfer id.',
])
