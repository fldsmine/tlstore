@extends('emails.support.layout')

@section('body')
    <p style="margin:0 0 14px">Hi {{ $guest_name ?: 'there' }},</p>
    <p style="margin:0 0 14px">
        Our support team replied to your message <strong>{{ $ticket_number }}</strong> ({{ $subject }}).
    </p>
    <blockquote style="margin:0 0 18px;padding:12px 16px;border-left:3px solid #cfe4d7;color:#3c4d45;white-space:pre-wrap">{{ $ticket_message }}</blockquote>
    <p style="margin:0;color:#64756d;font-size:13px">
        Reply directly to this email if you need anything else — your message stays attached to the same ticket.
    </p>
@endsection
