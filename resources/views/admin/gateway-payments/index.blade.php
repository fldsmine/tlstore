@extends('admin.layout')
@section('content')
<div class="eyebrow">Money</div>
<div class="section-head" style="align-items:end">
    <div>
        <h2 class="page-title">Gateway payments</h2>
        <p class="page-subtitle">Korapay, Paystack, Flutterwave, Bachs and Fincra checkouts. Revalidate with the provider, or settle a payment by hand when the webhook never arrived.</p>
    </div>
    @if(in_array((string) auth('admin')->user()->role, ['super_admin'], true))
        <a class="button secondary" href="{{ route('admin.gateway-balances.index') }}">Live gateway balances</a>
    @endif
</div>

<div class="grid metric-grid">
    <div class="card metric-card"><div class="metric-icon">⏳</div><div><div class="metric-label">Awaiting settlement</div><div class="metric-value">{{ number_format($summary['awaiting']) }}</div></div></div>
    <div class="card metric-card"><div class="metric-icon">✓</div><div><div class="metric-label">Paid</div><div class="metric-value">{{ number_format($summary['paid']) }}</div></div></div>
    <div class="card metric-card"><div class="metric-icon">✕</div><div><div class="metric-label">Failed / cancelled / expired</div><div class="metric-value">{{ number_format($summary['closed']) }}</div></div></div>
</div>

<section class="card">
    <form class="toolbar" method="GET">
        <div class="search">
            <input class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, idempotency key, user email or username…">
            <button class="button secondary">Search</button>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <select class="select" name="provider" style="max-width:170px" onchange="this.form.submit()">
                <option value="">Any gateway</option>
                @foreach(\App\Application\Payments\SettleGatewayPaymentManually::GATEWAY_PROVIDERS as $provider)
                    <option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ ucfirst($provider) }}</option>
                @endforeach
            </select>
            <select class="select" name="purpose" style="max-width:170px" onchange="this.form.submit()">
                <option value="">Any purpose</option>
                <option value="topup" @selected(($filters['purpose'] ?? '') === 'topup')>Top-up</option>
                <option value="subscription" @selected(($filters['purpose'] ?? '') === 'subscription')>Subscription</option>
                <option value="package_addon" @selected(($filters['purpose'] ?? '') === 'package_addon')>Package add-on</option>
            </select>
            <select class="select" name="status" style="max-width:170px" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach(['pending','paid','failed','cancelled','expired','refunded'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <input class="input" type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" style="max-width:160px">
            <input class="input" type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" style="max-width:160px">
            <button class="button secondary">Apply</button>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Intent</th><th>User</th><th>Gateway</th><th>Purpose</th><th>Amount</th><th>Status</th><th>Created</th><th>Manage</th>
                </tr>
            </thead>
            <tbody>
            @forelse($intents as $intent)
                @php
                    $badge = match($intent->status->value) {
                        'paid' => 'green',
                        'pending' => 'gold',
                        'failed', 'cancelled', 'expired' => 'red',
                        default => '',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>#{{ $intent->id }}</strong>
                        <div class="mono muted">{{ $intent->provider_reference ?: '—' }}</div>
                    </td>
                    <td>
                        <strong>{{ $intent->user?->full_name ?: '—' }}</strong>
                        <div class="muted mono">{{ $intent->user?->email }}</div>
                    </td>
                    <td>{{ ucfirst($intent->provider) }}</td>
                    <td>{{ str($intent->purpose)->replace('_', ' ')->title() }}</td>
                    <td>
                        <strong>{{ number_format($intent->amount_minor / 100, 2) }} {{ $intent->currency }}</strong>
                        <div class="muted mono">{{ number_format($intent->amount_minor) }} minor</div>
                    </td>
                    <td><span class="badge {{ $badge }}">{{ str($intent->status->value)->title() }}</span></td>
                    <td>{{ $intent->created_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        <div class="row-actions">
                            <a class="button secondary small" href="{{ route('admin.gateway-payments.show', $intent->id) }}">Open</a>
                            @if($intent->status->value === 'pending')
                                <form class="inline-form" method="POST" action="{{ route('admin.gateway-payments.revalidate', $intent->id) }}">
                                    @csrf
                                    <button class="button secondary small">Revalidate</button>
                                </form>
                                <details>
                                    <summary class="button small">Settle</summary>
                                    <form method="POST" action="{{ route('admin.gateway-payments.decision', $intent->id) }}" style="position:absolute;z-index:4;background:#fff;border:1px solid var(--line);padding:13px;border-radius:12px;box-shadow:var(--shadow);width:280px">
                                        @csrf
                                        <select class="select" name="decision" required>
                                            <option value="approved">Approved — complete the purchase</option>
                                            <option value="rejected">Rejected — payment not verified</option>
                                        </select>
                                        <input class="input" style="margin-top:8px" name="provider_reference" placeholder="Provider reference / receipt (optional)">
                                        <textarea class="textarea" style="min-height:70px;margin-top:8px" name="note" placeholder="What did you verify? (audited)"></textarea>
                                        <button class="button" style="margin-top:8px;width:100%">Save decision</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted" style="text-align:center;padding:32px">No gateway payments match this view.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <span>Showing {{ $intents->firstItem() ?? 0 }}–{{ $intents->lastItem() ?? 0 }} of {{ $intents->total() }}</span>
        {{ $intents->links() }}
    </div>
</section>
@endsection
