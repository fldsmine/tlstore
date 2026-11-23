@include('livewire.backoffice.payment-manager.verify-payout', [
    'manager' => 'Korapay',
    'routePrefix' => 'backoffice.korapay',
    'identifierLabel' => 'Trace ID',
    'referencePlaceholder' => 'KPO-… or a Korapay reference',
    'referenceHint' => 'Paste any payout reference.',
])
