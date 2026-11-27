@extends('emails.support.layout')

@section('body')
    <p style="margin:0 0 14px">A visitor submitted the public contact form.</p>
    <table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 18px">
        <tr><td style="padding:6px 0;color:#64756d;width:120px">Ticket</td><td style="padding:6px 0;font-weight:700">{{ $ticket_number }}</td></tr>
        <tr><td style="padding:6px 0;color:#64756d">Name</td><td style="padding:6px 0">{{ $guest_name ?: '—' }}</td></tr>
        <tr><td style="padding:6px 0;color:#64756d">Email</td><td style="padding:6px 0">{{ $guest_email }}</td></tr>
        <tr><td style="padding:6px 0;color:#64756d">Subject</td><td style="padding:6px 0">{{ $subject }}</td></tr>
        @if(! empty($account_name))
            <tr><td style="padding:6px 0;color:#64756d">Known account</td><td style="padding:6px 0">{{ $account_name }} ({{ $account_email }})</td></tr>
        @endif
        @if(! empty($ip_address))
            <tr><td style="padding:6px 0;color:#64756d">Source IP</td><td style="padding:6px 0">{{ $ip_address }}</td></tr>
        @endif
    </table>
    <p style="margin:0 0 8px;font-weight:600">Message</p>
    <blockquote style="margin:0;padding:12px 16px;border-left:3px solid #cfe4d7;color:#3c4d45;white-space:pre-wrap">{{ $ticket_message }}</blockquote>
    @isset($admin_url)
        <p style="margin:18px 0 0"><a href="{{ $admin_url }}" style="display:inline-block;background:#0c3528;color:#fff;text-decoration:none;padding:10px 16px;border-radius:10px;font-weight:700">Open in the support inbox</a></p>
    @endisset
@endsection
