<x-mail::message>
# Paid eSIM order — send the product

A guest has paid for an eSIM on the product page. Nothing has been delivered yet: the buyer is waiting on us.

<x-mail::panel>
**Reference:** {{ $reference }}

**Product:** {{ $product }}

**Amount:** {{ $amount }} — paid {{ $paidAt }} via {{ ucfirst((string) $provider) }}
</x-mail::panel>

**Buyer**

- Name: **{{ $buyerName }}**
- Phone: **{{ $buyerPhone }}**
- Email: **{{ $buyerEmail }}**

Send the {{ $product }} to {{ $buyerEmail }}, then mark the order fulfilled so it leaves the queue.

<x-mail::button :url="$orderUrl">
Open the order
</x-mail::button>
</x-mail::message>
