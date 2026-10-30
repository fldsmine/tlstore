<x-mail::message>
# Your {{ $product }}

@if ($name)
Hi {{ $name }},
@endif

Your {{ $product }} is ready. Everything you need is below.

<x-mail::panel>
{!! nl2br(e($details)) !!}
</x-mail::panel>

@if ($note)
{{ $note }}
@endif

**Order reference:** {{ $reference }}

Keep this email — you will need it if you contact support. If anything here does not work, reply to this message and we will sort it out.

@if ($support)
You can also reach us at {{ $support }}.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
