@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $money = fn ($minor, $currency) => number_format(\App\Application\Money\CurrencyMinorUnits::fromMinor((int) $minor, (string) $currency), \App\Application\Money\CurrencyMinorUnits::precision((string) $currency));
@endphp
@include('admin.users._header', ['tab' => 'withdrawals'])

<div class="grid detail-grid" style="align-items:start">
    <section class="card">
        <div class="section-head"><h2>Withdrawal summary</h2><span class="badge">{{ number_format($requests->total()) }} requests</span></div>
        @forelse($byStatus as $row)
            <div class="detail-row">
                <span>{{ str($row->status instanceof \BackedEnum ? $row->status->value : $row->status)->replace('_', ' ')->title() }} · {{ $row->currency }}</span>
                <strong>{{ number_format((int) $row->total) }} × {{ $money($row->amount_minor ?? 0, (string) $row->currency) }}</strong>
            </div>
        @empty
            <p class="muted">This account has never requested a withdrawal.</p>
        @endforelse
    </section>

    <section class="card">
        <div class="section-head">
            <h2>Payout method</h2>
            <a class="link" href="{{ route('admin.modules.index', 'withdrawal-options') }}">Methods →</a>
        </div>
        @if($account->payoutProfile)
            <div class="detail-row"><span>Saved option</span><strong>{{ $account->payoutProfile->option?->name ?? 'Method #'.$account->payoutProfile->withdrawal_option_id }}</strong></div>
            <pre style="white-space:pre-wrap;overflow-wrap:anywhere;background:#f7faf7;padding:12px;border-radius:10px;font-size:11px">{{ json_encode($account->payoutProfile->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        @else
            <p class="muted">No payout method is saved. The user must add one before a withdrawal can be settled.</p>
        @endif
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="section-head"><h2>Withdrawal requests</h2><a class="link" href="{{ route('admin.modules.index', 'withdrawal-moderation') }}">Withdrawal moderation →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Wallet</th><th>Amount</th><th>Fee</th><th>Method</th><th>Status</th><th>Requested</th><th>Processed</th><th>Note</th></tr></thead>
        <tbody>
        @forelse($requests as $withdrawal)
            @php($walletType = $withdrawal->wallet?->wallet_type instanceof \BackedEnum ? $withdrawal->wallet->wallet_type->value : (string) ($withdrawal->wallet?->wallet_type ?? '—'))
            <tr>
                <td class="mono">{{ $withdrawal->id }}</td>
                <td>{{ str($walletType)->replace('_', ' ')->title() }}</td>
                <td><strong>{{ $money($withdrawal->amount_minor, (string) $withdrawal->currency) }} {{ $withdrawal->currency }}</strong></td>
                <td class="muted">{{ $money($withdrawal->fee_minor, (string) $withdrawal->currency) }}</td>
                <td>{{ $withdrawal->option?->name ?? '—' }}</td>
                <td><span class="badge {{ in_array($withdrawal->status?->value, ['paid'], true) ? 'green' : (in_array($withdrawal->status?->value, ['rejected', 'failed', 'cancelled'], true) ? 'red' : 'gold') }}">{{ str($withdrawal->status?->value ?? 'unknown')->title() }}</span></td>
                <td class="muted">{{ ($withdrawal->requested_at ?? $withdrawal->created_at)?->format('M j, Y H:i') }}</td>
                <td class="muted">{{ $withdrawal->processed_at?->format('M j, Y H:i') ?? '—' }}</td>
                <td class="muted">{{ $withdrawal->failure_reason ?: ($withdrawal->provider_reference ?: '—') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted">No withdrawal requests for this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }}</span>{{ $requests->links() }}</div>
</section>

<section class="card" style="margin-top:18px">
    <div class="section-head"><h2>Manual deposit submissions</h2><a class="link" href="{{ route('admin.modules.index', 'deposit-moderation') }}">Deposit moderation →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Amount</th><th>Bank reference</th><th>Status</th><th>Submitted</th><th>Reviewed</th></tr></thead>
        <tbody>
        @forelse($recentDeposits as $deposit)
            <tr>
                <td class="mono">{{ $deposit->id }}</td>
                <td>{{ $money($deposit->amount_minor, (string) $deposit->currency) }} {{ $deposit->currency }}</td>
                <td class="mono">{{ $deposit->bank_reference ?: '—' }}</td>
                <td><span class="badge">{{ str($deposit->status instanceof \BackedEnum ? $deposit->status->value : $deposit->status)->replace('_', ' ')->title() }}</span></td>
                <td class="muted">{{ $deposit->submitted_at?->format('M j, Y H:i') ?? $deposit->created_at?->format('M j, Y H:i') }}</td>
                <td class="muted">{{ $deposit->reviewed_at?->format('M j, Y H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No manual deposits submitted by this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
