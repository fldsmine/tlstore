@extends('admin.layout')
@section('content')
<div class="eyebrow">Money · super admin</div>
<div class="section-head" style="align-items:end">
    <div>
        <h2 class="page-title">Live gateway balances</h2>
        <p class="page-subtitle">Read directly from each gateway's balance API at {{ $snapshot['fetched_at'] }}. Amounts are merchant settlement balances, not platform revenue.</p>
    </div>
    <div style="display:flex;gap:8px">
        <a class="button secondary" href="{{ route('admin.gateway-balances.index') }}">Refresh</a>
        <a class="button secondary" href="{{ route('admin.gateway-payments.index') }}">Gateway payments</a>
    </div>
</div>

<div class="grid detail-grid">
    @foreach($snapshot['gateways'] as $gateway)
        <section class="card">
            <div class="section-head">
                <h2>{{ $gateway['label'] }}</h2>
                <span class="badge {{ $gateway['error'] ? 'red' : 'green' }}">{{ $gateway['error'] ? 'Unavailable' : 'Live' }}</span>
            </div>
            @if($gateway['error'])
                <p class="errors">{{ $gateway['error'] }}</p>
            @else
                @foreach($gateway['balances'] as $balance)
                    <div class="detail-row">
                        <span class="muted">{{ $balance['currency'] }}</span>
                        <strong>{{ number_format($balance['available_minor'] / 100, 2, '.', ',') }} {{ $balance['currency'] }}</strong>
                    </div>
                @endforeach
            @endif
        </section>
    @endforeach
</div>

<section class="card" style="margin-top:16px">
    <p class="muted" style="margin:0">
        Balances are fetched live on every page load and never stored. A gateway with missing credentials reports "Unavailable" instead of failing the page.
        Flutterwave balances are requested per configured currency.
    </p>
</section>
@endsection
