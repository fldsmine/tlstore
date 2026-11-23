@include('livewire.backoffice.payment-manager.verify-payout', [
    'manager' => 'Bachs',
    'routePrefix' => 'backoffice.bachs',
    'identifierLabel' => 'Payout ID',
    'referencePlaceholder' => 'BCH-… or a Bachs pay_… id',
    'referenceHint' => 'Paste any payout reference or Bachs payout id.',
])
