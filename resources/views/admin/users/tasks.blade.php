@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $money = fn ($minor, $currency) => number_format(\App\Application\Money\CurrencyMinorUnits::fromMinor((int) $minor, (string) $currency), \App\Application\Money\CurrencyMinorUnits::precision((string) $currency));
    $currency = $account->wallet_currency ?: $account->preferred_currency;
@endphp
@include('admin.users._header', ['tab' => 'tasks'])

<div class="grid metric-grid">
    <div class="card metric-card"><span class="metric-icon">✓</span><div><div class="metric-label">Review submissions</div><div class="metric-value">{{ number_format($counters['review_submissions']) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">↗</span><div><div class="metric-label">Ad shares</div><div class="metric-value">{{ number_format($counters['ad_shares']) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">◈</span><div><div class="metric-label">Campaign orders</div><div class="metric-value">{{ number_format($counters['campaign_orders']) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">＋</span><div><div class="metric-label">VAS orders</div><div class="metric-value">{{ number_format($counters['vtu_orders']) }}</div></div></div>
</div>

<section class="card" style="margin-bottom:18px">
    <div class="section-head"><h2>Review submissions</h2><a class="link" href="{{ route('admin.modules.index', 'review-moderation') }}">Review moderation →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>App</th><th>Status</th><th>Reward</th><th>Submitted</th><th>Decided</th></tr></thead>
        <tbody>
        @forelse($submissions as $submission)
            <tr>
                <td class="mono">{{ $submission->id }}</td>
                <td>
                    <strong>{{ $submission->task?->campaign?->app?->name ?? '—' }}</strong>
                    <div class="muted mono">Task #{{ $submission->review_task_id }}</div>
                </td>
                <td><span class="badge {{ ($submission->status?->value ?? '') === 'approved' ? 'green' : '' }}">{{ str($submission->status?->value ?? 'unknown')->replace('_', ' ')->title() }}</span></td>
                <td>{{ $money($submission->task?->reserved_reward_minor ?? 0, (string) ($submission->task?->reward_currency ?? $currency)) }}</td>
                <td class="muted">{{ $submission->submitted_at?->format('M j, Y H:i') ?? '—' }}</td>
                <td class="muted">{{ $submission->decided_at?->format('M j, Y H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">This account has not submitted any app reviews.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>{{ $submissions->total() }} submissions</span>{{ $submissions->links() }}</div>
</section>

<section class="card" style="margin-bottom:18px">
    <div class="section-head"><h2>Ad share tasks</h2><a class="link" href="{{ route('admin.modules.index', 'share-moderation') }}">Share moderation →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Ad</th><th>Status</th><th>Reserved reward</th><th>Claimed</th><th>Completed</th></tr></thead>
        <tbody>
        @forelse($adShares as $share)
            <tr>
                <td class="mono">{{ $share->id }}</td>
                <td>{{ $share->ad?->title ?? 'Ad #'.$share->ad_id }}<div class="muted mono">{{ $share->ad?->status }}</div></td>
                <td><span class="badge {{ ($share->status?->value ?? '') === 'completed' ? 'green' : '' }}">{{ str($share->status?->value ?? 'unknown')->replace('_', ' ')->title() }}</span></td>
                <td>{{ $money($share->reserved_reward_minor ?? 0, (string) $currency) }}</td>
                <td class="muted">{{ $share->claimed_at?->format('M j, Y H:i') ?? '—' }}</td>
                <td class="muted">{{ $share->completed_at?->format('M j, Y H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No ad share tasks for this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>{{ $adShares->total() }} tasks</span>{{ $adShares->links() }}</div>
</section>

<section class="card" style="margin-bottom:18px">
    <div class="section-head"><h2>Campaign orders</h2><a class="link" href="{{ route('admin.modules.index', 'campaign-orders') }}">All campaign orders →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Campaign</th><th>Platform / action</th><th>Quantity</th><th>Total</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($orders as $order)
            <tr>
                <td class="mono">{{ $order->id }}</td>
                <td>{{ $order->campaign_name }}<div class="muted mono">{{ $order->target_url }}</div></td>
                <td>{{ $order->platform }}<div class="muted">{{ $order->action }}</div></td>
                <td>{{ number_format((int) $order->quantity) }}</td>
                <td>{{ $money($order->total_minor, (string) $order->currency) }} {{ $order->currency }}</td>
                <td><span class="badge {{ in_array($order->status, ['approved', 'completed'], true) ? 'green' : (in_array($order->status, ['rejected', 'refunded'], true) ? 'red' : 'gold') }}">{{ str($order->status)->replace('_', ' ')->title() }}</span></td>
                <td class="muted">{{ $order->created_at?->format('M j, Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No campaign orders placed by this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>{{ $orders->total() }} orders</span>{{ $orders->links() }}</div>
</section>

<section class="card">
    <div class="section-head"><h2>VAS orders</h2><a class="link" href="{{ route('admin.modules.index', 'vtu-orders') }}">All VAS orders →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Service</th><th>Provider</th><th>Amount</th><th>Charged</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        @forelse($vtuOrders as $order)
            <tr>
                <td class="mono">{{ $order->id }}</td>
                <td>{{ str($order->service)->replace('_', ' ')->title() }}<div class="muted mono">{{ $order->variation_code ?: '—' }}</div></td>
                <td>{{ $order->provider ?: '—' }}<div class="muted mono">{{ $order->external_id ?: $order->request_id }}</div></td>
                <td>{{ $money($order->amount_ngn_minor ?? 0, 'NGN') }} NGN</td>
                <td>{{ $money($order->charged_amount_minor ?? 0, (string) ($order->currency ?: $currency)) }} {{ $order->currency }}</td>
                <td><span class="badge {{ ($order->status?->value ?? '') === 'successful' ? 'green' : '' }}">{{ str($order->status?->value ?? 'unknown')->replace('_', ' ')->title() }}</span></td>
                <td class="muted">{{ $order->created_at?->format('M j, Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No VAS orders for this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>{{ $vtuOrders->total() }} orders</span>{{ $vtuOrders->links() }}</div>
</section>
@endsection
