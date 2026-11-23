<x-mail::message>
# {{ $heading }}

{{ $intro }}

**Ticket:** {{ $ticketReference }}

**Subject:** {{ $ticketSubject }}

---

{{ $body }}

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }} Support
</x-mail::message>
