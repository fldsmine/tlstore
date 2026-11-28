@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $money = fn ($minor, $currency) => number_format(\App\Application\Money\CurrencyMinorUnits::fromMinor((int) $minor, (string) $currency), \App\Application\Money\CurrencyMinorUnits::precision((string) $currency));
    $referralCurrency = $account->wallet_currency ?: $account->preferred_currency;
@endphp
@include('admin.users._header', ['tab' => 'referrals'])

<div class="grid metric-grid">
    <div class="card metric-card"><span class="metric-icon">♙</span><div><div class="metric-label">Referred users</div><div class="metric-value">{{ number_format($referredCount) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">✓</span><div><div class="metric-label">Qualified referrals</div><div class="metric-value">{{ number_format($qualifiedCount) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">₦</span><div><div class="metric-label">Referral earnings</div><div class="metric-value">{{ $money($earningsTotal, (string) $referralCurrency) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">◈</span><div><div class="metric-label">Referral code</div><div class="metric-value" style="font-size:18px">{{ $account->referral_code ?: '—' }}</div></div></div>
</div>

<div class="grid detail-grid" style="align-items:start">
    <section class="card">
        <div class="section-head"><h2>Referred by</h2><a class="link" href="{{ route('admin.modules.index', 'referrals') }}">Referral ledger →</a></div>
        @if($received)
            <div class="detail-row"><span>Referrer</span><strong>{{ $received->referrer?->username ?? 'User #'.$received->referrer_user_id }}</strong></div>
            <div class="detail-row"><span>Referrer email</span><strong>{{ $received->referrer?->email ?? '—' }}</strong></div>
            <div class="detail-row"><span>Attribution status</span><strong>{{ str($received->status)->replace('_', ' ')->title() }}</strong></div>
            <div class="detail-row"><span>Attributed</span><strong>{{ $received->attributed_at?->format('M j, Y H:i') ?? '—' }}</strong></div>
            <div class="detail-row"><span>Qualified</span><strong>{{ $received->qualified_at?->format('M j, Y H:i') ?? 'Not qualified yet' }}</strong></div>
            @if($received->referrer_user_id)
                <a class="button secondary small" style="margin-top:12px" href="{{ route('admin.users.show', $received->referrer_user_id) }}">Open referrer account</a>
            @endif
        @else
            <p class="muted">This account signed up directly and was not attributed to a referral code.</p>
        @endif
    </section>

    <section class="card">
        <div class="section-head"><h2>Referral wallet earnings</h2><span class="badge">{{ $money($earningsTotal, (string) $referralCurrency) }} {{ $referralCurrency }}</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>When</th><th>Entry</th><th>Amount</th><th>Status</th><th>Description</th></tr></thead>
            <tbody>
            @forelse($earnings as $entry)
                @php($direction = $entry->direction instanceof \BackedEnum ? $entry->direction->value : (string) $entry->direction)
                <tr>
                    <td class="muted">{{ $entry->created_at?->format('M j, Y H:i') }}</td>
                    <td class="mono">{{ str($entry->entry_type)->replace('_', ' ') }}</td>
                    <td>{{ $money($entry->amount_minor, (string) ($entry->wallet?->currency ?? $referralCurrency)) }} <span class="muted">{{ ucfirst($direction) }}</span></td>
                    <td><span class="badge">{{ $entry->status?->value ?? $entry->status }}</span></td>
                    <td class="muted">{{ $entry->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No referral wallet entries yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="section-head"><h2>Users referred by this account</h2><span class="badge">{{ number_format($sent->total()) }}</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>User</th><th>Email</th><th>Status</th><th>Attributed</th><th>Qualified</th><th></th></tr></thead>
        <tbody>
        @forelse($sent as $referral)
            <tr>
                <td>
                    <strong>{{ $referral->referredUser?->username ?? 'User #'.$referral->referred_user_id }}</strong>
                    <div class="muted mono">#{{ $referral->referred_user_id }}</div>
                </td>
                <td class="muted">{{ $referral->referredUser?->email ?? '—' }}</td>
                <td><span class="badge {{ str($referral->status) === 'qualified' ? 'green' : 'gold' }}">{{ str($referral->status)->replace('_', ' ')->title() }}</span></td>
                <td class="muted">{{ $referral->attributed_at?->format('M j, Y H:i') ?? '—' }}</td>
                <td class="muted">{{ $referral->qualified_at?->format('M j, Y H:i') ?? '—' }}</td>
                <td><a class="button secondary small" href="{{ route('admin.users.show', $referral->referred_user_id) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">This account has not referred anybody yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>Showing {{ $sent->firstItem() ?? 0 }}–{{ $sent->lastItem() ?? 0 }} of {{ $sent->total() }}</span>{{ $sent->links() }}</div>
</section>
@endsection
