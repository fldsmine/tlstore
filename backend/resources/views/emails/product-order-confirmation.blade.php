<x-mail::message>
# Payment received

@if ($name)
Hi {{ $name }},
@endif

Thank you — we have received your payment for the **{{ $product }}**. Your eSIM will be sent to **{{ $email }}** shortly, and it may take a few minutes to arrive.

<x-mail::panel>
**Order reference:** {{ $reference }}

**Product:** {{ $product }}

**Amount paid:** {{ $amount }}

**Paid:** {{ $paidAt }}
</x-mail::panel>

**What happens next**

1. Our team prepares your {{ $product }}.
2. It is emailed to {{ $email }} — the same address you gave at checkout.
3. Follow the steps in that email to activate it.

Nothing else is needed from you right now. If the email has not arrived within a few hours, check your spam folder first, then reply to this message.

@if ($support)
You can also reach us at {{ $support }}.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
