@extends('emails.support.layout')

@section('body')
    <p style="margin:0 0 14px">Hi {{ $guest_name ?: 'there' }},</p>
    <p style="margin:0 0 14px">
        Thanks for getting in touch with {{ $app_name ?? config('app.name', 'TLStore') }}. Your message is with our
        support team and we will reply to this address.
    </p>
    <table role="presentation" style="width:100%;border-collapse:collapse;margin:18px 0;background:#f6faf7;border:1px solid #e3ece6;border-radius:12px">
        <tr>
            <td style="padding:14px 16px">
                <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64756d">Your reference</div>
                <div style="font-weight:800;font-size:17px;color:#0c3528">{{ $ticket_number }}</div>
                <div style="margin-top:10px;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#64756d">Subject</div>
                <div style="font-weight:600">{{ $subject }}</div>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 8px;font-weight:600">What you sent us</p>
    <blockquote style="margin:0;padding:12px 16px;border-left:3px solid #cfe4d7;color:#3c4d45;white-space:pre-wrap">{{ $ticket_message }}</blockquote>
    <p style="margin:18px 0 0;color:#64756d;font-size:13px">
        If you did not submit this message you can ignore this email — no account was created.
    </p>
@endsection
