@include('livewire.backoffice.payment-manager.send-money', [
    'manager' => 'Paystack',
    'routePrefix' => 'backoffice.paystack',
    'managerConfigured' => $managerConfigured,
    'providerNote' => 'Paystack pays a transfer recipient: the account on this form is turned into one (or matched with the existing record for that account number) and the transfer is funded from your single Paystack balance. If an OTP is required on the account, Paystack holds the transfer at "pending" until it is approved on the Paystack dashboard — a held transfer has not left yet. A payout that gets no definitive answer is stored as unverified: check it with the reference instead of sending it again.',
])
