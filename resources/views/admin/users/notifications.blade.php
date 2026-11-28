@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $isModerator = in_array($adminRole, ['super_admin', 'admin', 'moderator'], true);
@endphp
@include('admin.users._header', ['tab' => 'notifications'])

<div class="grid detail-grid" style="align-items:start">
    @if($isModerator && ! $account->trashed())
        <section class="card">
            <div class="section-head"><h2>Send an in-app notification</h2><span class="badge">{{ $pushAvailable ? 'Push configured' : 'Push off' }}</span></div>
            <form method="POST" action="{{ route('admin.users.action', [$account->id, 'notify']) }}">
                @csrf
                <div class="field"><label for="title">Title</label><input class="input" id="title" name="title" maxlength="120" required placeholder="Short headline the user sees"></div>
                <div class="field" style="margin-top:10px"><label for="body">Message</label><textarea class="textarea" id="body" name="body" maxlength="1000" required style="min-height:110px" placeholder="Plain text. Do not include passwords or secrets."></textarea></div>
                <p class="field-help" style="margin:8px 0">
                    The message is stored in the account notification feed immediately. A push attempt is made only when Firebase is configured and the user has a registered device.
                </p>
                <button class="button" type="submit">Send notification</button>
            </form>
        </section>
    @endif

    <section class="card">
        <div class="section-head"><h2>Notification preferences</h2><span class="badge">User controlled</span></div>
        @forelse($preferences as $preference)
            <div class="detail-row">
                <span>{{ str($preference->category)->replace('_', ' ')->title() }}</span>
                <span class="pill-list">
                    <span class="badge {{ $preference->push_enabled ? 'green' : '' }}">Push {{ $preference->push_enabled ? 'on' : 'off' }}</span>
                    <span class="badge {{ $preference->email_enabled ? 'green' : '' }}">Email {{ $preference->email_enabled ? 'on' : 'off' }}</span>
                </span>
            </div>
        @empty
            <p class="muted">No explicit preference rows: the app defaults apply.</p>
        @endforelse
        <p class="field-help" style="margin-top:10px">Preferences are changed by the user in the app so consent stays with them; this view is read only.</p>
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="section-head"><h2>Notification history</h2><span class="badge">{{ number_format($notifications->total()) }} total</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Notification</th><th>Type</th><th>Read</th><th>Deliveries</th></tr></thead>
        <tbody>
        @forelse($notifications as $notification)
            <tr>
                <td class="muted">{{ $notification->created_at?->format('M j, Y H:i') }}</td>
                <td>
                    <strong>{{ $notification->title }}</strong>
                    <div class="muted">{{ str($notification->body)->limit(140) }}</div>
                    <div class="muted mono">{{ $notification->notification_key }}</div>
                </td>
                <td class="mono">{{ str($notification->type)->replace('_', ' ') }}</td>
                <td><span class="badge {{ $notification->read_at ? '' : 'green' }}">{{ $notification->read_at ? 'Read' : 'Unread' }}</span></td>
                <td class="muted">{{ number_format($notification->deliveries_count) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Nothing has been sent to this account yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>Showing {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }}</span>{{ $notifications->links() }}</div>
</section>

<section class="card" style="margin-top:18px">
    <div class="section-head"><h2>Registered devices</h2><a class="link" href="{{ route('admin.users.activity', $account->id) }}">Sessions →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Device</th><th>Platform</th><th>Token</th><th>Last seen</th><th>State</th><th></th></tr></thead>
        <tbody>
        @forelse($devices as $device)
            <tr>
                <td class="mono">#{{ $device->id }}</td>
                <td>{{ $device->platform ?: '—' }}</td>
                <td class="mono muted">{{ $device->push_token ? str($device->push_token)->limit(22).'…' : '—' }}</td>
                <td class="muted">{{ $device->last_seen_at?->format('M j, Y H:i') ?? '—' }}</td>
                <td><span class="badge {{ $device->revoked_at ? 'red' : 'green' }}">{{ $device->revoked_at ? 'Revoked' : 'Active' }}</span></td>
                <td>
                    @if(! $device->revoked_at && $isModerator)
                        <form method="POST" action="{{ route('admin.users.action', [$account->id, 'revoke-device']) }}" onsubmit="return confirm('Revoke this device token?')">
                            @csrf
                            <input type="hidden" name="device_id" value="{{ $device->id }}">
                            <button class="button danger small" type="submit">Revoke</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No devices have registered for push notifications.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
