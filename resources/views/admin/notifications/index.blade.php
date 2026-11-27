@extends('admin.layout')
@section('content')
<div class="eyebrow">Platform messages</div>
<div class="section-head"><div><h2 class="page-title">Email & notification templates</h2><p class="page-subtitle">Control global delivery and edit the messages used for key account and earning events.</p></div></div>

@php
    $setting = fn($key,$fallback=null) => data_get($settings->get($key)?->value, 'enabled', $settings->get($key)?->value ?? $fallback);
@endphp
<section class="card" style="margin-bottom:16px">
    <div class="section-head"><div><h2>Delivery configuration</h2><p class="muted" style="margin:4px 0 0">Provider credentials are read from the server environment and are never stored in this page.</p></div></div>
    <form method="POST" action="{{ route('admin.notifications.settings') }}">@csrf @method('PATCH')
        <div class="form-grid">
            <div class="field full"><label class="checkline"><input type="checkbox" name="email_enabled" value="1" @checked((bool)$setting('notifications.email.enabled',false))> Enable email notifications globally</label><span class="field-help">Emails are sent only when this switch, the event template and the user’s email preference are enabled. Password reset follows the global and template switches.</span></div>
            <div class="field"><label>Mail provider</label><select class="select" name="mailer" required>@foreach($mailers as $mailer)<option value="{{ $mailer }}" @selected($setting('notifications.email.mailer',config('mail.default','smtp'))===$mailer)>{{ ucfirst($mailer) }} · {{ $mailerStatus[$mailer] }}</option>@endforeach</select><span class="field-help">Add provider keys and SMTP settings to the server .env, then select it here.</span></div>
            <div class="field"><label>From address</label><input class="input" type="email" name="from_address" required value="{{ $setting('notifications.email.from_address',config('mail.from.address')) }}"></div>
            <div class="field"><label>From name</label><input class="input" name="from_name" required maxlength="120" value="{{ $setting('notifications.email.from_name',config('mail.from.name','TLStore')) }}"></div>
            <div class="field"><label class="checkline"><input type="checkbox" name="push_enabled" value="1" @checked((bool)$setting('notifications.push.enabled',true))> Enable push notifications globally</label><span class="field-help">Push also requires a registered device and configured Firebase credentials.</span></div>
            <div class="field full"><label class="checkline"><input type="checkbox" disabled> SMS notifications (reserved for a future integration)</label><span class="field-help">SMS template fields and per-template switches are stored, but no SMS provider or delivery code is active.</span></div>
        </div>
        <div class="row-actions" style="margin-top:16px"><button class="button">Save delivery settings</button></div>
    </form>
    <form method="POST" action="{{ route('admin.notifications.test') }}" style="display:flex;gap:8px;align-items:end;margin-top:18px;padding-top:15px;border-top:1px solid var(--line)">@csrf<div class="field" style="flex:1"><label>Send test email</label><input class="input" name="email" type="email" required placeholder="you@example.com"></div><button class="button secondary">Send test</button></form>
</section>

<section class="card">
    <div class="section-head"><div><h2>Event templates</h2><p class="muted" style="margin:4px 0 0">SMS status is kept for later; it does not trigger SMS delivery.</p></div></div>
    <div class="table-wrap"><table><thead><tr><th>Event</th><th>Key</th><th>Email</th><th>Push</th><th>SMS</th><th></th></tr></thead><tbody>
    @forelse($templates as $template)
        <tr><td><strong>{{ $template->name }}</strong><div class="muted">{{ $template->subject }}</div></td><td class="mono">{{ $template->act }}</td><td><span class="badge {{ $template->email_status?'green':'' }}">{{ $template->email_status?'Active':'Off' }}</span></td><td><span class="badge {{ $template->firebase_status?'green':'' }}">{{ $template->firebase_status?'Active':'Off' }}</span></td><td><span class="badge">{{ $template->sms_status?'Enabled (not sent)':'Off' }}</span></td><td><a class="button secondary small" href="{{ route('admin.notifications.templates.edit',$template) }}">Edit template</a></td></tr>
    @empty<tr><td colspan="6" class="muted" style="text-align:center;padding:30px">Templates are not seeded yet. Run the database seeders.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination"><span>Showing {{ $templates->firstItem()??0 }}–{{ $templates->lastItem()??0 }} of {{ $templates->total() }}</span>{{ $templates->links() }}</div>
</section>
@endsection
