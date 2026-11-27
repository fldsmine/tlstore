@extends('admin.layout')
@section('content')
<div class="eyebrow">Gateway payment</div>
<div class="section-head" style="align-items:end">
    <div>
        <h2 class="page-title">#{{ $intent->id }} · {{ ucfirst($intent->provider) }}</h2>
        <p class="page-subtitle">{{ $intent->user?->full_name }} · {{ $intent->user?->email }}</p>
    </div>
    <a class="button secondary" href="{{ route('admin.gateway-payments.index') }}">← All gateway payments</a>
</div>

<div class="grid detail-grid" style="margin-bottom:16px">
    <section class="card">
        <div class="section-head"><h2>Payment</h2><span class="badge {{ $intent->status->value === 'paid' ? 'green' : ($intent->status->value === 'pending' ? 'gold' : 'red') }}">{{ str($intent->status->value)->title() }}</span></div>
        <div class="detail-row"><span class="muted">Amount</span><strong>{{ number_format($intent->amount_minor / 100, 2) }} {{ $intent->currency }}</strong></div>
        <div class="detail-row"><span class="muted">Minor units</span><span class="mono">{{ number_format($intent->amount_minor) }}</span></div>
        <div class="detail-row"><span class="muted">Purpose</span><span>{{ str($intent->purpose)->replace('_', ' ')->title() }}</span></div>
        <div class="detail-row"><span class="muted">Provider reference</span><span class="mono">{{ $intent->provider_reference ?: '—' }}</span></div>
        <div class="detail-row"><span class="muted">Idempotency key</span><span class="mono">{{ $intent->idempotency_key }}</span></div>
        <div class="detail-row"><span class="muted">Created</span><span>{{ $intent->created_at?->format('Y-m-d H:i:s') }}</span></div>
        <div class="detail-row"><span class="muted">Expires</span><span>{{ $intent->expires_at?->format('Y-m-d H:i:s') ?? '—' }}</span></div>
        <div class="detail-row"><span class="muted">Paid at</span><span>{{ $intent->paid_at?->format('Y-m-d H:i:s') ?? '—' }}</span></div>
    </section>

    <section class="card">
        <div class="section-head"><h2>Actions</h2></div>
        @if($intent->status->value === 'pending')
            <form method="POST" action="{{ route('admin.gateway-payments.revalidate', $intent->id) }}" style="margin-bottom:14px">
                @csrf
                <button class="button secondary" style="width:100%">Revalidate with {{ ucfirst($intent->provider) }}</button>
                <div class="field-help" style="margin-top:6px">Asks the provider what really happened. A confirmed payment completes the purchase exactly like a webhook.</div>
            </form>

            <form method="POST" action="{{ route('admin.gateway-payments.decision', $intent->id) }}">
                @csrf
                <div class="field">
                    <label for="decision">Manual decision</label>
                    <select class="select" id="decision" name="decision" required>
                        <option value="approved">Approved — complete the intended action</option>
                        <option value="rejected">Rejected — payment not verified</option>
                    </select>
                </div>
                <div class="field" style="margin-top:10px">
                    <label for="provider_reference">Provider reference / receipt</label>
                    <input class="input" id="provider_reference" name="provider_reference" placeholder="e.g. provider transaction id from their dashboard">
                </div>
                <div class="field" style="margin-top:10px">
                    <label for="note">Verification note</label>
                    <textarea class="textarea" id="note" name="note" placeholder="Where did you verify this payment? Bank statement, provider dashboard, support ticket…"></textarea>
                    <span class="field-help">Stored on the audit log with your account. Approval credits the wallet or activates the package exactly once.</span>
                </div>
                <button class="button" style="margin-top:12px;width:100%">Save decision</button>
            </form>
        @else
            <p class="muted">This payment is already {{ $intent->status->value }}; no further action is available.</p>
        @endif
    </section>
</div>

<section class="card" style="margin-bottom:16px">
    <div class="section-head"><h2>History projection</h2></div>
    @if($transaction)
        <div class="detail-row"><span class="muted">Reference</span><span class="mono">{{ $transaction->reference }}</span></div>
        <div class="detail-row"><span class="muted">Type / direction</span><span>{{ $transaction->type->value }} · {{ $transaction->direction }}</span></div>
        <div class="detail-row"><span class="muted">Status</span><span class="badge {{ $transaction->status === 'completed' ? 'green' : ($transaction->status === 'pending' ? 'gold' : 'red') }}">{{ ucfirst($transaction->status) }}</span></div>
        <div class="detail-row"><span class="muted">Amount</span><span>{{ number_format((float) $transaction->amount, 2) }}</span></div>
    @else
        <p class="muted">No transaction projection exists for this payment.</p>
    @endif
</section>

<div class="grid detail-grid">
    <section class="card">
        <div class="section-head"><h2>Ledger entries</h2></div>
        @forelse($ledgerEntries as $entry)
            <div class="detail-row">
                <span class="mono">{{ $entry->entry_type }} · {{ $entry->direction }}</span>
                <span>{{ number_format($entry->amount_minor / 100, 2) }} · {{ $entry->status }}</span>
            </div>
        @empty
            <p class="muted">Nothing has been posted to the ledger for this payment.</p>
        @endforelse
    </section>

    <section class="card">
        <div class="section-head"><h2>Provider receipts</h2></div>
        @forelse($paymentTransactions as $receipt)
            <div class="detail-row">
                <span class="mono">{{ $receipt->provider_transaction_id ?: '—' }}</span>
                <span>{{ number_format($receipt->amount_minor / 100, 2) }} {{ $receipt->currency }} · {{ $receipt->status }}</span>
            </div>
        @empty
            <p class="muted">No provider receipt recorded.</p>
        @endforelse
    </section>
</div>

<section class="card" style="margin-top:16px">
    <div class="section-head"><h2>Provider metadata</h2></div>
    <pre class="mono" style="white-space:pre-wrap;word-break:break-word">{{ json_encode($intent->provider_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
</section>
@endsection
