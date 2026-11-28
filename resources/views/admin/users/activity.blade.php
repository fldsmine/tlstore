@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $isStaffEditor = in_array($adminRole, ['super_admin', 'admin'], true);
@endphp
@include('admin.users._header', ['tab' => 'activity'])

<div class="grid detail-grid" style="align-items:start">
    <section class="card">
        <div class="section-head">
            <h2>API sessions</h2>
            @if($isStaffEditor && $tokens->isNotEmpty())
                <form method="POST" action="{{ route('admin.users.action', [$account->id, 'revoke-sessions']) }}" onsubmit="return confirm('Revoke every API session for this account?')">
                    @csrf
                    <button class="button danger small" type="submit">Revoke all</button>
                </form>
            @else
                <span class="badge">{{ $tokens->count() }} active</span>
            @endif
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Abilities</th><th>Last used</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($tokens as $token)
                <tr>
                    <td><strong>{{ $token->name }}</strong></td>
                    <td class="mono muted">{{ implode(', ', (array) ($token->abilities ?? [])) ?: '—' }}</td>
                    <td class="muted">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="muted">{{ $token->created_at?->format('M j, Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No mobile sessions are open for this account.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <div class="section-head"><h2>Devices</h2><a class="link" href="{{ route('admin.users.notifications', $account->id) }}">Manage devices →</a></div>
        @forelse($devices as $device)
            <div class="detail-row">
                <span>{{ $device->platform ?: 'Unknown platform' }} <span class="muted mono">#{{ $device->id }}</span></span>
                <span class="badge {{ $device->revoked_at ? 'red' : 'green' }}">{{ $device->revoked_at ? 'Revoked' : 'Active' }}</span>
            </div>
            <div class="muted" style="font-size:11px;margin:-4px 0 6px">Last seen {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}</div>
        @empty
            <p class="muted">No devices registered.</p>
        @endforelse
        <div class="detail-row"><span>Two-factor</span><strong>{{ $account->two_factor ? 'Enabled' : 'Disabled' }}</strong></div>
        <div class="detail-row"><span>Security PIN</span><strong>{{ $account->security_pin ? 'Set' : 'Not set' }}</strong></div>
        <div class="detail-row"><span>Password</span><strong>{{ $account->password ? 'Set' : 'Not set (social sign-in only)' }}</strong></div>
    </section>
</div>

<div class="grid detail-grid" style="margin-top:18px;align-items:start">
    <section class="card">
        <div class="section-head"><h2>Support tickets</h2><a class="link" href="{{ route('admin.support-tickets.index', ['q' => $account->email]) }}">All tickets →</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Priority</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td><a class="link mono" href="{{ route('admin.support-tickets.show', $ticket->id) }}">{{ $ticket->ticket_number }}</a></td>
                    <td>{{ str($ticket->subject)->limit(60) }}</td>
                    <td><span class="badge">{{ str($ticket->status)->replace('_', ' ')->title() }}</span></td>
                    <td class="muted">{{ str($ticket->priority)->title() }}</td>
                    <td class="muted">{{ ($ticket->last_message_at ?? $ticket->created_at)?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No support tickets from this account.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <div class="section-head"><h2>App reports filed</h2><a class="link" href="{{ route('admin.modules.index', 'app-reports') }}">Report queue →</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Reason</th><th>Status</th><th>Filed</th></tr></thead>
            <tbody>
            @forelse($reports as $report)
                <tr>
                    <td class="mono">{{ $report->id }}</td>
                    <td>{{ str($report->reason_code)->replace('_', ' ')->title() }}<div class="muted">{{ str($report->details)->limit(70) }}</div></td>
                    <td><span class="badge">{{ str($report->status)->replace('_', ' ')->title() }}</span></td>
                    <td class="muted">{{ $report->created_at?->format('M j, Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">This account has not reported any app.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="section-head">
        <div>
            <h2>Audit trail</h2>
            <p class="field-help" style="margin:2px 0 0">Administrator actions on this account plus anything the user did that was audited. Secret-like values are redacted.</p>
        </div>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Changes</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="muted">{{ $log->created_at?->format('M j, Y H:i') }}</td>
                <td><strong>{{ str_replace(['.', '_'], [' · ', ' '], $log->action) }}</strong><div class="muted mono">{{ $log->entity_type }} #{{ $log->entity_id }}</div></td>
                <td>{{ $log->adminActor?->full_name ?? ($log->actor_user_id ? 'The user (#'.$log->actor_user_id.')' : 'System') }}</td>
                <td>
                    @if($log->before_values || $log->after_values)
                        <details>
                            <summary class="link" style="cursor:pointer;font-size:11px">View</summary>
                            <pre style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:10px;margin:6px 0 0">{{ json_encode(['before' => $log->before_values, 'after' => $log->after_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No audit entries for this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }}</span>{{ $logs->links() }}</div>
</section>
@endsection
