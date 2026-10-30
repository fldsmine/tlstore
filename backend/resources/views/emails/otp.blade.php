<x-mail::message>
# {{ $heading }}

@if ($name)
Hi {{ $name }},
@endif

{{ $intro }}

<x-mail::panel>
<p style="text-align:center; margin:0; font-size:32px; font-weight:700; letter-spacing:10px; color:#DCEB6A;">{{ $code }}</p>
</x-mail::panel>

This code expires in **10 minutes**. For your security, never share it with anyone.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
