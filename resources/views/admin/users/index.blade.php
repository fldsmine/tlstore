@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $money = fn ($minor, $currency) => number_format(\App\Application\Money\CurrencyMinorUnits::fromMinor((int) $minor, (string) $currency), \App\Application\Money\CurrencyMinorUnits::precision((string) $currency));
    $filterUrl = fn (array $overrides) => route('admin.users.index', array_filter(array_merge($filters, $overrides), fn ($value) => $value !== '' && $value !== null));
@endphp
<div class="eyebrow">Administration</div>
<div class="section-head">
    <div>
        <h2 class="page-title">Users</h2>
        <p class="page-subtitle">Search every account, then open a user workspace to manage their profile, wallet, tasks, withdrawals, referrals, notifications and security.</p>
    </div>
    <span class="badge">{{ number_format($users->total()) }} matching {{ $users->total() === 1 ? 'account' : 'accounts' }}</span>
</div>

<div class="grid metric-grid">
    <div class="card metric-card"><span class="metric-icon">♙</span><div><div class="metric-label">All accounts</div><div class="metric-value">{{ number_format($stats['total']) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">✓</span><div><div class="metric-label">Active</div><div class="metric-value">{{ number_format($stats['active']) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">⊘</span><div><div class="metric-label">Banned</div><div class="metric-value">{{ number_format($stats['banned']) }}</div></div></div>
    <div class="card metric-card"><span class="metric-icon">↗</span><div><div class="metric-label">New in 7 days</div><div class="metric-value">{{ number_format($stats['new_week']) }}</div></div></div>
</div>
<div class="pill-list" style="margin:-4px 0 16px">
    <span class="badge green">{{ number_format($stats['new_today']) }} joined today</span>
    <span class="badge gold">{{ number_format($stats['subscribed']) }} with an active package</span>
    <span class="badge red">{{ number_format($stats['deleted']) }} deleted</span>
</div>

<section class="card" style="margin-bottom:18px">
    <form method="GET" action="{{ route('admin.users.index') }}" class="form-grid">
        <div class="field full">
            <label for="user-search">Search</label>
            <input class="input" id="user-search" name="q" value="{{ $filters['q'] }}" placeholder="Name, username, email, phone, referral code or user ID">
            <span class="field-help">Wildcards are escaped. Numeric input also matches the account ID.</span>
        </div>
        <div class="field">
            <label for="user-status">Account status</label>
            <select class="select" id="user-status" name="status">
                <option value="">Any status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="user-state">Account state</label>
            <select class="select" id="user-state" name="state">
                @foreach(['' => 'Any state', 'active' => 'Active & not banned', 'banned' => 'Banned only', 'verified' => 'Email verified', 'unverified' => 'Email unverified', 'subscribed' => 'Has an active package', 'no_subscription' => 'No active package', 'with_balance' => 'Holds a wallet balance'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['state'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="user-country">Country</label>
            <select class="select" id="user-country" name="country">
                <option value="">Any country</option>
                @foreach($countries as $country)
                    <option value="{{ $country->code }}" @selected($filters['country'] === strtoupper((string) $country->code))>{{ $country->name }} ({{ $country->code }})</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="user-deleted">Deleted accounts</label>
            <select class="select" id="user-deleted" name="deleted">
                <option value="exclude" @selected($filters['deleted'] === 'exclude')>Hide deleted</option>
                <option value="include" @selected($filters['deleted'] === 'include')>Include deleted</option>
                <option value="only" @selected($filters['deleted'] === 'only')>Only deleted</option>
            </select>
        </div>
        <div class="field">
            <label for="user-sort">Sort by</label>
            <select class="select" id="user-sort" name="sort">
                <option value="newest" @selected($filters['sort'] === 'newest')>Newest first</option>
                <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest first</option>
                <option value="name" @selected($filters['sort'] === 'name')>Name (A–Z)</option>
                <option value="active" @selected($filters['sort'] === 'active')>Recently active</option>
            </select>
        </div>
        <div class="field">
            <label for="user-per-page">Rows per page</label>
            <select class="select" id="user-per-page" name="per_page">
                @foreach([25, 50, 100] as $size)
                    <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="field full row-actions">
            <button class="button" type="submit">Apply filters</button>
            <a class="button secondary" href="{{ route('admin.users.index') }}">Reset</a>
            @if($filters['state'] !== 'unverified')
                <a class="link" href="{{ $filterUrl(['state' => 'unverified', 'deleted' => 'exclude']) }}">Unverified emails →</a>
            @endif
            @if($filters['state'] !== 'banned')
                <a class="link" href="{{ $filterUrl(['state' => 'banned', 'deleted' => 'exclude']) }}">Banned accounts →</a>
            @endif
        </div>
    </form>
</section>

<section class="card">
    <div class="section-head">
        <h2>Accounts</h2>
        <span class="muted" style="font-size:11px">Balances are shown per wallet currency.</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Account</th><th>Country</th><th>Wallets</th><th>Status</th><th>Records</th><th>Last seen</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                @php
                    $userStatus = $user->status?->value ?? (string) $user->status;
                    $totals = [];
                    foreach ($user->wallets as $wallet) {
                        $totals[(string) $wallet->currency] = ($totals[(string) $wallet->currency] ?? 0) + (int) $wallet->balance_minor;
                    }
                @endphp
                <tr>
                    <td class="mono">{{ $user->id }}</td>
                    <td>
                        <strong>{{ $user->full_name ?: $user->username }}</strong>
                        <div class="muted">@if($user->username)&#64;{{ $user->username }} · @endif{{ $user->email ?: 'no email' }}</div>
                        @if($user->phone)<div class="muted mono">{{ $user->phone }}</div>@endif
                    </td>
                    <td>{{ $user->country?->name ?? ($user->country_code ?: '—') }}<div class="muted mono">{{ $user->preferred_currency }}</div></td>
                    <td>
                        @forelse($totals as $currency => $minor)
                            <div class="mono">{{ $money($minor, $currency) }} {{ $currency }}</div>
                        @empty
                            <span class="muted">No wallets</span>
                        @endforelse
                    </td>
                    <td>
                        <span class="badge {{ $user->trashed() || $user->is_banned ? 'red' : 'green' }}">{{ $user->trashed() ? 'Deleted' : ($user->is_banned ? 'Banned' : str($userStatus)->replace('_', ' ')->title()) }}</span>
                        @unless($user->email_verified_at)<div class="muted" style="margin-top:4px">Email unverified</div>@endunless
                    </td>
                    <td>
                        <div class="mono">{{ $user->withdrawal_requests_count }} withdrawals</div>
                        <div class="muted mono">{{ $user->sent_referrals_count }} referrals</div>
                    </td>
                    <td class="muted">{{ $user->devices_max_last_seen_at ? \Illuminate\Support\Carbon::parse($user->devices_max_last_seen_at)->diffForHumans() : '—' }}</td>
                    <td class="muted">{{ $user->created_at?->format('M j, Y') }}</td>
                    <td><a class="button secondary small" href="{{ route('admin.users.show', $user->id) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">No accounts match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination"><span>Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</span>{{ $users->links() }}</div>
</section>
@endsection
