@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
    $statusValue = $account->status?->value ?? (string) $account->status;
    $initials = collect(preg_split('/\s+/', trim((string) ($account->full_name ?: $account->username ?: 'U'))))
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $tabs = [
        'overview' => ['Overview', route('admin.users.show', $account->id), null],
        'wallet' => ['Wallet', route('admin.users.wallet', $account->id), $account->wallets->count()],
        'tasks' => ['Tasks & campaigns', route('admin.users.tasks', $account->id), $account->reviewSubmissions()->count() + $account->adShareTasks()->count() + $account->campaignOrders()->count()],
        'withdrawals' => ['Withdrawals', route('admin.users.withdrawals', $account->id), $account->withdrawalRequests()->count()],
        'referrals' => ['Referrals', route('admin.users.referrals', $account->id), $account->sentReferrals()->count()],
        'notifications' => ['Notifications', route('admin.users.notifications', $account->id), $account->notifications()->whereNull('read_at')->count()],
        'activity' => ['Activity & security', route('admin.users.activity', $account->id), null],
    ];
@endphp
<style>
    .user-head{display:flex;gap:15px;align-items:flex-start;flex-wrap:wrap}
    .user-avatar{width:58px;height:58px;border-radius:18px;object-fit:cover;background:#e7f3eb;color:var(--green);display:grid;place-items:center;font-weight:900;font-size:18px;flex:none;overflow:hidden}
    .user-badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}
    .user-meta{color:var(--muted);font-size:12px;margin-top:4px}
    .usertabs{display:flex;gap:4px;overflow:auto;border-bottom:1px solid var(--line);margin:16px 0;padding-bottom:0}
    .usertabs a{padding:10px 12px;font-size:12px;font-weight:800;color:#5d6e65;border-bottom:2px solid transparent;white-space:nowrap;display:flex;align-items:center;gap:6px}
    .usertabs a:hover{color:var(--green)}
    .usertabs a.active{color:var(--green);border-color:var(--green)}
    .usertabs .tab-count{background:#eff3f0;color:#58675f;border-radius:100px;padding:1px 7px;font-size:10px}
    .usertabs a.active .tab-count{background:#e6f5ec;color:#08734f}
    .stat-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .stat-tile{border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:#fbfdfb}
    .stat-tile span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);font-weight:800}
    .stat-tile strong{font-size:19px;font-weight:900;line-height:1.3}
    .stack{display:grid;gap:16px}
    .pill-list{display:flex;gap:6px;flex-wrap:wrap}
    .danger-zone{background:#fffafa}
    .action-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    @media(max-width:760px){.stat-list,.action-grid{grid-template-columns:1fr}}
</style>
<div class="eyebrow">User account</div>
<div class="section-head">
    <div class="user-head">
        <div class="user-avatar">
            @if($account->avatar)
                <img src="{{ $account->avatar }}" alt="" style="width:100%;height:100%;object-fit:cover">
            @else
                {{ $initials ?: 'U' }}
            @endif
        </div>
        <div>
            <h2 class="page-title" style="margin:0">{{ $account->full_name ?: $account->username }}</h2>
            <div class="user-meta">
                {{ '@'.($account->username ?: 'unknown') }} · {{ $account->email ?: 'no email' }} · User #{{ $account->id }}
            </div>
            <div class="user-meta">
                {{ $account->country?->name ?? ($account->country_code ?: 'No country') }} ·
                {{ $account->preferred_currency }} preferred / {{ $account->wallet_currency }} wallet ·
                Joined {{ $account->created_at?->format('M j, Y') }}
            </div>
            <div class="user-badges">
                <span class="badge {{ $account->trashed() || $account->is_banned ? 'red' : 'green' }}">
                    {{ $account->trashed() ? 'Deleted' : ($account->is_banned ? 'Banned' : str($statusValue)->replace('_', ' ')->title()) }}
                </span>
                <span class="badge">{{ str($account->role?->value ?? 'reviewer')->replace('_', ' ')->title() }}</span>
                @if($account->email_verified_at)
                    <span class="badge green">Email verified</span>
                @else
                    <span class="badge gold">Email unverified</span>
                @endif
                @if($account->two_factor_enabled_at || $account->two_factor)
                    <span class="badge green">2FA on</span>
                @else
                    <span class="badge">2FA off</span>
                @endif
                @if((int) $account->is_member === 1)
                    <span class="badge gold">Member</span>
                @endif
            </div>
        </div>
    </div>
    <div class="row-actions">
        <a class="button secondary" href="{{ route('admin.users.index') }}">← All users</a>
    </div>
</div>
@if(session('temporary_password'))
    <div class="flash">Temporary password (shown once): <code>{{ session('temporary_password') }}</code></div>
@endif
<nav class="usertabs">
    @foreach($tabs as $key => [$label, $url, $count])
        <a class="{{ ($tab ?? 'overview') === $key ? 'active' : '' }}" href="{{ $url }}">
            {{ $label }}
            @if($count !== null && $count > 0)
                <span class="tab-count" title="{{ $key === 'notifications' ? 'unread notifications' : 'records' }}">{{ $count }}</span>
            @endif
        </a>
    @endforeach
</nav>
