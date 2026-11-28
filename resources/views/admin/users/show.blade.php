@extends('admin.layout')
@section('content')
@php
    $adminRole = (string) (auth('admin')->user()?->role ?? '');
@endphp
@php
    $money = fn ($minor, $currency) => number_format(\App\Application\Money\CurrencyMinorUnits::fromMinor((int) $minor, (string) $currency), \App\Application\Money\CurrencyMinorUnits::precision((string) $currency));
    $isStaffEditor = in_array($adminRole, ['super_admin', 'admin'], true);
    $impersonationLinkTtl = (int) config('impersonation.link_ttl_seconds', 120);
    $impersonationSessionMinutes = (int) config('impersonation.session_minutes', 60);
    $impersonationLinkTtlLabel = $impersonationLinkTtl >= 120
        ? round($impersonationLinkTtl / 60).' minutes'
        : $impersonationLinkTtl.' seconds';
    $isModerator = in_array($adminRole, ['super_admin', 'admin', 'moderator'], true);
@endphp
@include('admin.users._header', ['tab' => 'overview'])

<div class="grid detail-grid" style="align-items:start">
    <section class="card">
        <div class="section-head"><h2>Account snapshot</h2><span class="badge">{{ $account->referral_code ?: 'No referral code' }}</span></div>
        <div class="detail-row"><span>Phone</span><strong>{{ $account->phone ?: '—' }}</strong></div>
        <div class="detail-row"><span>Country</span><strong>{{ $account->country?->name ?? ($account->country_code ?: '—') }}</strong></div>
        <div class="detail-row"><span>Preferred / wallet currency</span><strong>{{ $account->preferred_currency }} / {{ $account->wallet_currency }}</strong></div>
        <div class="detail-row"><span>Email verified</span><strong>{{ $account->email_verified_at?->format('M j, Y H:i') ?? 'Not verified' }}</strong></div>
        <div class="detail-row"><span>Two-factor</span><strong>{{ $account->two_factor_enabled_at?->format('M j, Y') ?? ($account->two_factor ? 'Enabled' : 'Not enabled') }}</strong></div>
        <div class="detail-row"><span>Security PIN</span><strong>{{ $account->security_pin ? 'Set' : 'Not set' }}</strong></div>
        <div class="detail-row"><span>Referred by</span><strong>{{ $account->referralAttribution?->referrer?->username ?? 'Direct sign-up' }}</strong></div>
        <div class="detail-row"><span>Joined</span><strong>{{ $account->created_at?->format('M j, Y H:i') }}</strong></div>
        <div class="detail-row"><span>Last device activity</span><strong>{{ $account->devices->max('last_seen_at')?->diffForHumans() ?? 'No device recorded' }}</strong></div>
        @if($account->is_banned)
            <div class="detail-row"><span>Ban reason</span><strong style="max-width:60%;text-align:right">{{ $account->ban_reason ?: '—' }}</strong></div>
        @endif
        @if($account->trashed())
            <div class="detail-row"><span>Deleted at</span><strong>{{ $account->deleted_at?->format('M j, Y H:i') }}</strong></div>
        @endif
    </section>

    <section class="card">
        <div class="section-head"><h2>Wallet balances</h2><a class="link" href="{{ route('admin.users.wallet', $account->id) }}">Open wallet →</a></div>
        @forelse($account->wallets->sortBy('wallet_type') as $wallet)
            @php($walletType = $wallet->wallet_type instanceof \BackedEnum ? $wallet->wallet_type->value : (string) $wallet->wallet_type)
            <div class="detail-row">
                <span>{{ str($walletType)->replace('_', ' ')->title() }} · {{ $wallet->currency }} <span class="muted mono">{{ $wallet->status }}</span></span>
                <strong>{{ $money($wallet->balance_minor, $wallet->currency) }} {{ $wallet->currency }}</strong>
            </div>
        @empty
            <p class="muted">No wallets have been created for this account.</p>
        @endforelse
        @if($walletTotals)
            <div class="detail-row" style="border-top:1px solid var(--line);margin-top:8px;padding-top:12px">
                <span>Total</span>
                <strong>
                    @foreach($walletTotals as $currency => $minor)
                        {{ $money($minor, $currency) }} {{ $currency }}@if(! $loop->last) · @endif
                    @endforeach
                </strong>
            </div>
        @endif
        <a class="button secondary small" style="margin-top:12px" href="{{ route('admin.users.wallet', $account->id) }}">Ledger & adjustment</a>
    </section>

    <section class="card">
        <div class="section-head"><h2>Subscription &amp; add-ons</h2><a class="link" href="{{ route('admin.modules.index', 'subscription-plans') }}">Plans →</a></div>
        @forelse($subscriptions as $subscription)
            <div class="detail-row">
                <span>{{ $subscription->plan?->name ?? 'Unknown plan' }} <span class="muted">{{ $subscription->plan?->group?->name }}</span></span>
                <span class="badge {{ ($subscription->status?->value ?? '') === 'active' ? 'green' : '' }}">{{ str($subscription->status?->value ?? 'unknown')->replace('_', ' ')->title() }}</span>
            </div>
            <div class="muted" style="font-size:11px;margin:-4px 0 6px">{{ $subscription->starts_at?->format('M j, Y') ?? '—' }} → {{ $subscription->ends_at?->format('M j, Y') ?? 'open ended' }}</div>
        @empty
            <p class="muted">This account has never held a package subscription.</p>
        @endforelse
        @if($account->packageAddons->isNotEmpty())
            <div style="margin-top:12px"><strong style="font-size:12px">Add-ons</strong></div>
            @foreach($account->packageAddons as $addon)
                <div class="detail-row">
                    <span>{{ $addon->addon?->name ?? 'Add-on #'.$addon->package_addon_id }}</span>
                    <span class="badge">{{ $addon->status }}</span>
                </div>
            @endforeach
        @endif
    </section>

    <section class="card">
        <div class="section-head"><h2>Platform activity</h2><a class="link" href="{{ route('admin.users.tasks', $account->id) }}">All records →</a></div>
        <div class="stat-list">
            <div class="stat-tile"><span>Review submissions</span><strong>{{ number_format($counters['review_submissions']) }}</strong></div>
            <div class="stat-tile"><span>Ad shares</span><strong>{{ number_format($counters['ad_shares']) }}</strong></div>
            <div class="stat-tile"><span>Campaign orders</span><strong>{{ number_format($counters['campaign_orders']) }}</strong></div>
            <div class="stat-tile"><span>VAS orders</span><strong>{{ number_format($counters['vtu_orders']) }}</strong></div>
            <div class="stat-tile"><span>Withdrawals</span><strong>{{ number_format($counters['withdrawals']) }}</strong></div>
            <div class="stat-tile"><span>Pending withdrawals</span><strong>{{ number_format($counters['withdrawals_pending']) }}</strong></div>
            <div class="stat-tile"><span>Referrals</span><strong>{{ number_format($counters['referrals']) }}</strong></div>
            <div class="stat-tile"><span>Unread notifications</span><strong>{{ number_format($counters['unread_notifications']) }}</strong></div>
            <div class="stat-tile"><span>Devices</span><strong>{{ number_format($counters['devices']) }}</strong></div>
            <div class="stat-tile"><span>Support tickets</span><strong>{{ number_format($counters['tickets']) }}</strong></div>
        </div>
    </section>
</div>

<div class="grid detail-grid" style="margin-top:18px;align-items:start">
    <section class="card">
        <div class="section-head"><h2>Profile details</h2>@unless($isStaffEditor)<span class="badge">Read only</span>@endunless</div>
        <form method="POST" action="{{ route('admin.users.profile.update', $account->id) }}" class="form-grid">
            @csrf
            @method('PATCH')
            <div class="field"><label for="full_name">Full name</label><input class="input" id="full_name" name="full_name" value="{{ old('full_name', $account->full_name) }}" required @disabled(! $isStaffEditor)></div>
            <div class="field"><label for="username">Username</label><input class="input" id="username" name="username" value="{{ old('username', $account->username) }}" required @disabled(! $isStaffEditor)></div>
            <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $account->email) }}" required @disabled(! $isStaffEditor)></div>
            <div class="field"><label for="phone">Phone</label><input class="input" id="phone" name="phone" value="{{ old('phone', $account->phone) }}" @disabled(! $isStaffEditor)></div>
            <div class="field">
                <label for="country_code">Country</label>
                <select class="select" id="country_code" name="country_code" @disabled(! $isStaffEditor)>
                    <option value="">Not set</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->code }}" @selected(old('country_code', $account->country_code) === $country->code)>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field"><label for="preferred_currency">Preferred currency</label><input class="input" id="preferred_currency" name="preferred_currency" maxlength="3" value="{{ old('preferred_currency', $account->preferred_currency) }}" @disabled(! $isStaffEditor)></div>
            <div class="field"><label for="avatar">Avatar URL</label><input class="input" id="avatar" name="avatar" type="url" value="{{ old('avatar', $account->avatar) }}" @disabled(! $isStaffEditor)></div>
            <div class="field"><label for="cover_image">Cover image URL</label><input class="input" id="cover_image" name="cover_image" type="url" value="{{ old('cover_image', $account->cover_image) }}" @disabled(! $isStaffEditor)></div>
            <label class="checkline field full"><input type="hidden" name="is_member" value="0"><input type="checkbox" name="is_member" value="1" @checked((bool) old('is_member', $account->is_member)) @disabled(! $isStaffEditor)> <span>Member account (has active package)</span></label>
            @if($isStaffEditor)
                <div class="field full"><button class="button" type="submit">Save profile</button></div>
            @endif
        </form>
        <p class="field-help" style="margin-top:10px">Wallet currency ({{ $account->wallet_currency }}) follows the account ledger and is not changed here.</p>
    </section>

    <div class="stack">
        <section class="card">
            <div class="section-head"><h2>Account actions</h2><span class="badge">{{ str($adminRole)->replace('_', ' ')->title() }}</span></div>
            @if($adminRole === 'super_admin' && ! $account->trashed() && ! $account->is_banned && ($account->status?->value ?? $account->status) === 'active')
                <form method="POST" action="{{ route('admin.users.impersonate', $account->id) }}" target="_blank" rel="noopener" style="margin-bottom:14px">
                    @csrf
                    <button class="button" type="submit" style="width:100%">Impersonate — open the web app as this user</button>
                    <p class="field-help" style="margin:8px 0 0">
                        Opens <code>{{ url('/') }}</code> in a new tab, already signed in as {{ $account->username ?: 'this user' }}.
                        The one-time link expires in {{ $impersonationLinkTtlLabel }} and the session ends after
                        {{ $impersonationSessionMinutes }} minutes. Both steps are audited.
                    </p>
                </form>
            @elseif($adminRole === 'super_admin' && ! $account->trashed())
                <p class="field-help" style="margin:0 0 12px">Activate the account (and lift any ban) to impersonate it — the app only accepts active sessions.</p>
            @endif
            <div class="action-grid">
                @if($account->trashed())
                    @if($isStaffEditor)
                        <form method="POST" action="{{ route('admin.users.action', [$account->id, 'restore']) }}">@csrf<button class="button" type="submit">Restore account</button></form>
                    @endif
                @else
                    @if($isModerator)
                        @if($account->is_banned)
                            <form method="POST" action="{{ route('admin.users.action', [$account->id, 'unban']) }}">@csrf<button class="button" type="submit">Unban user</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.users.action', [$account->id, 'ban']) }}" onsubmit="return confirm('Ban this user and revoke their sessions?')">
                                @csrf
                                <input class="input" name="reason" required minlength="4" placeholder="Ban reason (audited)">
                                <button class="button danger" style="margin-top:8px" type="submit">Ban user</button>
                            </form>
                        @endif
                    @endif
                    @if($isStaffEditor)
                        <form method="POST" action="{{ route('admin.users.action', [$account->id, 'reset-password']) }}" onsubmit="return confirm('Reset this password and revoke all API sessions?')">@csrf<button class="button secondary" type="submit">Reset password</button></form>
                        @unless($account->email_verified_at)
                            <form method="POST" action="{{ route('admin.users.action', [$account->id, 'verify-email']) }}">@csrf<button class="button secondary" type="submit">Mark email verified</button></form>
                        @endunless
                        <form method="POST" action="{{ route('admin.users.action', [$account->id, 'revoke-sessions']) }}" onsubmit="return confirm('Sign this user out on every device?')">@csrf<button class="button secondary" type="submit">Revoke sessions</button></form>
                        <form method="POST" action="{{ route('admin.users.action', [$account->id, 'delete']) }}" onsubmit="return confirm('Soft-delete this account? It can be restored later.')">@csrf<button class="button danger" type="submit">Soft delete</button></form>
                    @endif
                @endif
            </div>
            @if($isStaffEditor)
                <form method="POST" action="{{ route('admin.users.action', [$account->id, 'status']) }}" style="margin-top:14px">
                    @csrf
                    <div class="field">
                        <label for="account-status">Set account status</label>
                        <select class="select" id="account-status" name="status">
                            @foreach(['active', 'restricted', 'suspended', 'pending'] as $value)
                                <option value="{{ $value }}" @selected(($account->status?->value ?? '') === $value)>{{ str($value)->title() }}</option>
                            @endforeach
                        </select>
                        <span class="field-help">Choosing active also lifts an existing ban.</span>
                    </div>
                    <input class="input" name="reason" placeholder="Reason (optional, audited)" style="margin-top:8px">
                    <button class="button" style="margin-top:8px" type="submit">Update status</button>
                </form>
            @endif
        </section>

        @if($isModerator && ! $account->trashed())
            <section class="card">
                <div class="section-head"><h2>Message the user</h2><a class="link" href="{{ route('admin.users.notifications', $account->id) }}">History →</a></div>
                <form method="POST" action="{{ route('admin.users.action', [$account->id, 'notify']) }}">
                    @csrf
                    <div class="field"><label for="notify-title">Title</label><input class="input" id="notify-title" name="title" maxlength="120" required placeholder="e.g. Action needed on your withdrawal"></div>
                    <div class="field" style="margin-top:10px"><label for="notify-body">Message</label><textarea class="textarea" id="notify-body" name="body" maxlength="1000" required style="min-height:90px"></textarea></div>
                    <p class="field-help" style="margin:8px 0">Creates an in-app notification. Push is attempted only when Firebase is configured.</p>
                    <button class="button" type="submit">Send notification</button>
                </form>
            </section>
        @endif
    </div>
</div>

<div class="grid detail-grid" style="margin-top:18px">
    <section class="card">
        <div class="section-head"><h2>Recent ledger activity</h2><a class="link" href="{{ route('admin.users.wallet', $account->id) }}">Full ledger →</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Description</th><th>Amount</th><th>Status</th><th>When</th></tr></thead>
            <tbody>
            @forelse($recentTransactions as $transaction)
                @php($transactionType = $transaction->type instanceof \BackedEnum ? $transaction->type->value : (string) $transaction->type)
                <tr>
                    <td>{{ $transaction->description ?: $transactionType }}</td>
                    <td>{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->meta['currency'] ?? $account->wallet_currency }}</td>
                    <td><span class="badge">{{ $transaction->status }}</span></td>
                    <td class="muted">{{ $transaction->created_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No transactions recorded.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card">
        <div class="section-head"><h2>Recent notifications</h2><a class="link" href="{{ route('admin.users.notifications', $account->id) }}">All →</a></div>
        @forelse($recentNotifications as $notification)
            <div class="detail-row">
                <span>{{ $notification->title }}<div class="muted" style="font-size:11px">{{ str($notification->type)->replace('_', ' ')->title() }} · {{ $notification->created_at?->diffForHumans() }}</div></span>
                <span class="badge {{ $notification->read_at ? '' : 'green' }}">{{ $notification->read_at ? 'Read' : 'Unread' }}</span>
            </div>
        @empty
            <p class="muted">No notifications have been sent to this account.</p>
        @endforelse
    </section>
</div>

<section class="card" style="margin-top:18px">
    <div class="section-head"><h2>Recent administrator activity</h2><a class="link" href="{{ route('admin.users.activity', $account->id) }}">Full history →</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Action</th><th>By</th><th>When</th></tr></thead>
        <tbody>
        @forelse($recentAudit as $log)
            <tr>
                <td><strong>{{ str_replace(['.', '_'], [' · ', ' '], $log->action) }}</strong></td>
                <td>{{ $log->adminActor?->full_name ?? 'System / the user' }}</td>
                <td class="muted">{{ $log->created_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No administrator action recorded for this account.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
