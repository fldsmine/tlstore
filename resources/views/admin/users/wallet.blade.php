@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $money = fn ($minor, $currency) => number_format(\App\Application\Money\CurrencyMinorUnits::fromMinor((int) $minor, (string) $currency), \App\Application\Money\CurrencyMinorUnits::precision((string) $currency));
    $canAdjust = in_array($adminRole, ['super_admin', 'admin', 'finance'], true);
@endphp
@include('admin.users._header', ['tab' => 'wallet'])

<div class="grid detail-grid" style="align-items:start">
    <section class="card">
        <div class="section-head"><h2>Balances</h2><span class="badge">{{ $wallets->count() }} {{ $wallets->count() === 1 ? 'wallet' : 'wallets' }}</span></div>
        @forelse($wallets as $wallet)
            @php($walletType = $wallet->wallet_type instanceof \BackedEnum ? $wallet->wallet_type->value : (string) $wallet->wallet_type)
            <div class="detail-row">
                <span>
                    <strong>{{ str($walletType)->replace('_', ' ')->title() }}</strong>
                    <div class="muted mono">Wallet #{{ $wallet->id }} · {{ $wallet->status }} · {{ $wallet->currency }}</div>
                </span>
                <strong>{{ $money($wallet->balance_minor, $wallet->currency) }} {{ $wallet->currency }}</strong>
            </div>
        @empty
            <p class="muted">No wallets yet. Posting an adjustment creates the matching wallet on demand.</p>
        @endforelse
        @if($walletTotals)
            <div class="detail-row" style="border-top:1px solid var(--line);margin-top:8px;padding-top:12px">
                <span>Total held</span>
                <strong>
                    @foreach($walletTotals as $currency => $minor)
                        {{ $money($minor, $currency) }} {{ $currency }}@if(! $loop->last) · @endif
                    @endforeach
                </strong>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="section-head"><h2>Manual adjustment</h2>@unless($canAdjust)<span class="badge">Finance only</span>@endunless</div>
        @if($canAdjust && ! $account->trashed())
            <form method="POST" action="{{ route('admin.users.action', [$account->id, 'wallet-adjustment']) }}" class="form-grid">
                @csrf
                <div class="field">
                    <label for="wallet_type">Wallet</label>
                    <select class="select" id="wallet_type" name="wallet_type" required>
                        @foreach(['review', 'review_group_b', 'review_group_c', 'share', 'referral', 'bonus', 'topup_fund'] as $type)
                            <option value="{{ $type }}" @selected(old('wallet_type') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="direction">Direction</label>
                    <select class="select" id="direction" name="direction">
                        <option value="credit">Credit (add funds)</option>
                        <option value="debit">Debit (remove funds)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="amount_minor">Amount (minor units, {{ $account->wallet_currency }})</label>
                    <input class="input" id="amount_minor" name="amount_minor" type="number" min="1" step="1" required>
                    <span class="field-help">100 = 1.00 {{ $account->wallet_currency }}. Every adjustment posts a ledger entry.</span>
                </div>
                <div class="field">
                    <label for="reason">Reason</label>
                    <input class="input" id="reason" name="reason" required minlength="4" maxlength="500" placeholder="Shown on the ledger entry">
                </div>
                <div class="field full"><button class="button" type="submit">Post adjustment</button></div>
            </form>
        @else
            <p class="muted">{{ $account->trashed() ? 'Restore the account before adjusting its wallets.' : 'Your role can inspect wallets but cannot move money. Ask a finance administrator to post adjustments.' }}</p>
        @endif
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="section-head">
        <h2>Ledger entries</h2>
        <form method="GET" action="{{ route('admin.users.wallet', $account->id) }}" class="row-actions">
            <select class="select" name="type" style="width:auto">
                <option value="">All wallets</option>
                @foreach($walletTypes as $type)
                    @php($typeValue = $type instanceof \BackedEnum ? $type->value : (string) $type)
                    <option value="{{ $typeValue }}" @selected($typeFilter === $typeValue)>{{ str($typeValue)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
            <button class="button secondary small" type="submit">Filter</button>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Wallet</th><th>Entry</th><th>Direction</th><th>Amount</th><th>Status</th><th>Description</th></tr></thead>
        <tbody>
        @forelse($entries as $entry)
            @php($entryType = $entry->entry_type instanceof \BackedEnum ? $entry->entry_type->value : (string) $entry->entry_type)
            @php($direction = $entry->direction instanceof \BackedEnum ? $entry->direction->value : (string) $entry->direction)
            @php($walletType = $entry->wallet?->wallet_type instanceof \BackedEnum ? $entry->wallet->wallet_type->value : (string) ($entry->wallet?->wallet_type ?? '—'))
            <tr>
                <td class="muted">{{ $entry->created_at?->format('M j, Y H:i') }}</td>
                <td>{{ str($walletType)->replace('_', ' ')->title() }}<div class="muted mono">{{ $entry->wallet?->currency }}</div></td>
                <td class="mono">{{ str($entryType)->replace('_', ' ') }}</td>
                <td><span class="badge {{ $direction === 'credit' ? 'green' : 'red' }}">{{ ucfirst($direction) }}</span></td>
                <td><strong>{{ $money($entry->amount_minor, (string) ($entry->wallet?->currency ?? $account->wallet_currency)) }}</strong></td>
                <td><span class="badge {{ ($entry->status?->value ?? '') === 'posted' ? 'green' : 'gold' }}">{{ $entry->status?->value ?? $entry->status }}</span></td>
                <td class="muted">{{ $entry->description ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No ledger entries for this account yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="pagination"><span>Showing {{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }}</span>{{ $entries->links() }}</div>
</section>
@endsection
