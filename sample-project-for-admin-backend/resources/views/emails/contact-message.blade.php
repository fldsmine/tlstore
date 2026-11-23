<x-mail::message>
# New Contact Enquiry

**From:** {{ $senderName }} ({{ $senderEmail }})

{{ $body }}

<x-mail::button :url="'mailto:'.$senderEmail">
Reply to {{ $senderName }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
