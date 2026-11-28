<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Application\Money\CurrencyMinorUnits;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Features\Admin\Support\AdminAuditLogger;
use App\Features\Notifications\Services\FirebaseCloudMessaging;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\LedgerEntry;
use App\Models\Notification;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Dedicated Users area of the admin console.
 *
 * The generic module CRUD could only edit a handful of profile columns, so this
 * controller owns the account list, the account workspace (overview, wallet,
 * tasks, withdrawals, referrals, notifications, activity) and every account
 * action the console can take.
 */
final class UserAdminController
{
    /** Roles allowed to open the section at all. */
    private const VIEW_ROLES = ['super_admin', 'admin', 'moderator', 'finance'];

    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'state' => (string) $request->query('state', ''),
            'country' => strtoupper((string) $request->query('country', '')),
            'deleted' => (string) $request->query('deleted', 'exclude'),
            'sort' => (string) $request->query('sort', 'newest'),
            'per_page' => (int) $request->query('per_page', 25),
        ];

        $filters['per_page'] = in_array($filters['per_page'], [25, 50, 100], true) ? $filters['per_page'] : 25;
        $filters['sort'] = in_array($filters['sort'], ['newest', 'oldest', 'name', 'active'], true) ? $filters['sort'] : 'newest';
        $filters['deleted'] = in_array($filters['deleted'], ['exclude', 'include', 'only'], true) ? $filters['deleted'] : 'exclude';

        $query = User::query()
            ->with(['country:id,code,name', 'wallets'])
            ->withMax('devices', 'last_seen_at')
            ->withCount(['withdrawalRequests', 'sentReferrals']);

        $this->applyFilters($query, $filters);

        match ($filters['sort']) {
            'oldest' => $query->orderBy('created_at'),
            'name' => $query->orderByRaw('LOWER(COALESCE(full_name, username, email))'),
            'active' => $query->orderByDesc('devices_max_last_seen_at')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $users = $query->paginate($filters['per_page'])->withQueryString();

        return view('admin.users.index', [
            'title' => 'Users',
            'users' => $users,
            'filters' => $filters,
            'statuses' => UserStatus::cases(),
            'countries' => Country::query()->orderBy('name')->get(['code', 'name']),
            'stats' => $this->stats(),
        ]);
    }

    public function show(int $user): View
    {
        $account = $this->findUser($user);

        return view('admin.users.show', [
            'title' => 'User account',
            'account' => $account,
            'tab' => 'overview',
            'subscriptions' => $account->subscriptions()->with('plan.group')->latest()->limit(10)->get(),
            'countries' => Country::query()->orderBy('name')->get(['code', 'name']),
            'counters' => $this->counters($account),
            'recentTransactions' => $account->transactions()->latest()->limit(8)->get(),
            'recentNotifications' => $account->notifications()->latest()->limit(5)->get(),
            'recentAudit' => $this->activityQuery($account)->limit(6)->get(),
            'walletTotals' => $this->walletTotals($account),
        ]);
    }

    public function wallet(Request $request, int $user): View
    {
        $account = $this->findUser($user);
        $walletIds = $account->wallets->pluck('id');
        $type = (string) $request->query('type', '');

        $entries = LedgerEntry::query()
            ->whereIn('wallet_id', $walletIds)
            ->when($type !== '', fn (Builder $query) => $query->whereHas('wallet', fn (Builder $wallet) => $wallet->where('wallet_type', $type)))
            ->with('wallet')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'ledger_page')
            ->withQueryString();

        return view('admin.users.wallet', [
            'title' => 'User wallet',
            'account' => $account,
            'tab' => 'wallet',
            'wallets' => $account->wallets->sortBy('wallet_type'),
            'walletTotals' => $this->walletTotals($account),
            'entries' => $entries,
            'typeFilter' => $type,
            'walletTypes' => $account->wallets->pluck('wallet_type')->filter()->unique()->values(),
        ]);
    }

    public function tasks(Request $request, int $user): View
    {
        $account = $this->findUser($user);

        return view('admin.users.tasks', [
            'title' => 'User tasks',
            'account' => $account,
            'tab' => 'tasks',
            'counters' => $this->counters($account),
            'submissions' => $account->reviewSubmissions()
                ->with(['task.campaign.app:id,name'])
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'reviews_page'),
            'adShares' => $account->adShareTasks()
                ->with(['ad:id,title,status'])
                ->orderByDesc('id')
                ->paginate(20, ['*'], 'shares_page'),
            'orders' => $account->campaignOrders()->orderByDesc('id')->paginate(20, ['*'], 'orders_page'),
            'vtuOrders' => $account->vtuOrders()->orderByDesc('id')->paginate(20, ['*'], 'vtu_page'),
        ]);
    }

    public function withdrawals(int $user): View
    {
        $account = $this->findUser($user);

        $byStatus = $account->withdrawalRequests()
            ->selectRaw('status, currency, count(*) as total, sum(amount_minor) as amount_minor')
            ->groupBy('status', 'currency')
            ->get();

        return view('admin.users.withdrawals', [
            'title' => 'User withdrawals',
            'account' => $account,
            'tab' => 'withdrawals',
            'requests' => $account->withdrawalRequests()
                ->with(['option:id,name', 'wallet:id,wallet_type,currency'])
                ->orderByDesc('id')
                ->paginate(25),
            'byStatus' => $byStatus,
            'recentDeposits' => $account->manualDeposits()->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function referrals(int $user): View
    {
        $account = $this->findUser($user);

        $referralWalletIds = $account->wallets()
            ->where('wallet_type', 'referral')
            ->pluck('id');

        return view('admin.users.referrals', [
            'title' => 'User referrals',
            'account' => $account,
            'tab' => 'referrals',
            'sent' => $account->sentReferrals()
                ->with('referredUser:id,username,email,status,created_at')
                ->orderByDesc('id')
                ->paginate(25, ['*'], 'sent_page'),
            'received' => $account->referralAttribution()->with('referrer:id,username,email')->first(),
            'earnings' => LedgerEntry::query()
                ->whereIn('wallet_id', $referralWalletIds)
                ->with('wallet')
                ->orderByDesc('id')
                ->limit(25)
                ->get(),
            'earningsTotal' => (int) LedgerEntry::query()
                ->whereIn('wallet_id', $referralWalletIds)
                ->where('direction', 'credit')
                ->where('status', 'posted')
                ->sum('amount_minor'),
            'referredCount' => $account->sentReferrals()->count(),
            'qualifiedCount' => $account->sentReferrals()->whereNotNull('qualified_at')->count(),
        ]);
    }

    public function notifications(int $user): View
    {
        $account = $this->findUser($user);

        return view('admin.users.notifications', [
            'title' => 'User notifications',
            'account' => $account,
            'tab' => 'notifications',
            'notifications' => $account->notifications()->withCount('deliveries')->orderByDesc('id')->paginate(25),
            'preferences' => $account->notificationPreferences()->orderBy('category')->get(),
            'devices' => $account->devices()->orderByDesc('last_seen_at')->get(),
            'pushAvailable' => (bool) config('services.fcm.enabled'),
        ]);
    }

    public function activity(int $user): View
    {
        $account = $this->findUser($user);

        return view('admin.users.activity', [
            'title' => 'User activity',
            'account' => $account,
            'tab' => 'activity',
            'logs' => $this->activityQuery($account)->paginate(30),
            'tokens' => $account->tokens()->orderByDesc('last_used_at')->get(),
            'devices' => $account->devices()->orderByDesc('last_seen_at')->get(),
            'reports' => $account->appReports()->orderByDesc('id')->limit(10)->get(),
            'tickets' => $account->supportTickets()->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function updateProfile(Request $request, int $user): RedirectResponse
    {
        $account = $this->findUser($user);
        $this->authorizeRoles(['super_admin', 'admin']);
        abort_if($account->trashed(), 422, 'Restore the account before editing its profile.');

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($account->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($account->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')],
            'preferred_currency' => ['nullable', 'string', 'size:3'],
            'avatar' => ['nullable', 'url', 'max:500'],
            'cover_image' => ['nullable', 'url', 'max:500'],
            'is_member' => ['nullable', 'boolean'],
        ]);

        $before = $account->only(array_keys($data));
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : $account->country_code;
        $data['preferred_currency'] = isset($data['preferred_currency']) ? strtoupper($data['preferred_currency']) : $account->preferred_currency;
        $data['is_member'] = $request->boolean('is_member');
        $account->fill($data);
        $account->save();
        $after = $account->only(array_keys($data));
        $this->audit->log('user.profile_updated', $account, $before, $after);

        return redirect()->route('admin.users.show', $account->id)->with('success', 'Profile details saved.');
    }

    public function action(Request $request, int $user, string $action): RedirectResponse
    {
        $this->authorizeAccess();
        $account = $this->findUser($user);
        $admin = Auth::guard('admin')->user();

        if ($action === 'wallet-adjustment') {
            $this->authorizeRoles(['super_admin', 'admin', 'finance']);
            $data = $request->validate([
                'wallet_type' => ['required', Rule::in(['review', 'review_group_b', 'review_group_c', 'share', 'referral', 'topup_fund', 'bonus'])],
                'direction' => ['required', Rule::in(['credit', 'debit'])],
                'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
                'reason' => ['required', 'string', 'min:4', 'max:500'],
            ]);
            if ($account->trashed()) {
                throw ValidationException::withMessages(['user' => 'A deleted account cannot be adjusted.']);
            }
            $currency = $this->walletCurrency($account);
            DB::transaction(function () use ($account, $admin, $data, $currency): void {
                $wallet = Wallet::query()->firstOrCreate([
                    'user_id' => $account->id,
                    'wallet_type' => $data['wallet_type'],
                    'currency' => $currency,
                ], ['status' => 'active']);
                $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);
                $amount = (int) $data['amount_minor'];
                if ($data['direction'] === 'debit' && (int) $wallet->balance_minor < $amount) {
                    throw ValidationException::withMessages(['amount_minor' => 'This wallet does not have enough available funds.']);
                }
                $entry = LedgerEntry::query()->create([
                    'wallet_id' => $wallet->id,
                    'entry_type' => 'admin_adjustment',
                    'direction' => $data['direction'],
                    'amount_minor' => $amount,
                    'status' => 'posted',
                    'source_type' => 'user',
                    'source_id' => $account->id,
                    'idempotency_key' => 'admin-wallet:'.hash('sha256', $admin->id.'|'.$account->id.'|'.Str::uuid()),
                    'description' => $data['reason'],
                    'metadata' => ['admin_id' => $admin->id, 'reason' => $data['reason']],
                    'available_at' => now(),
                    'posted_at' => now(),
                ]);
                $this->audit->log('user.wallet_adjusted', $account, null, [
                    'wallet_id' => $wallet->id,
                    'wallet_type' => $data['wallet_type'],
                    'direction' => $data['direction'],
                    'amount_minor' => $amount,
                    'currency' => $currency,
                    'ledger_entry_id' => $entry->id,
                    'reason' => $data['reason'],
                ]);
            });

            return back()->with('success', "Wallet updated in the user's {$currency} currency.");
        }

        if ($action === 'ban' || $action === 'unban') {
            $this->authorizeRoles(['super_admin', 'admin', 'moderator']);
            $data = $request->validate(['reason' => [$action === 'ban' ? 'required' : 'nullable', 'string', 'min:4', 'max:2000']]);
            $before = ['is_banned' => $account->is_banned, 'status' => $account->status?->value, 'ban_reason' => $account->ban_reason];
            $account->forceFill($action === 'ban'
                ? ['is_banned' => true, 'status' => 'banned', 'ban_reason' => $data['reason']]
                : ['is_banned' => false, 'status' => 'active', 'ban_reason' => null])->save();
            $account->tokens()->delete();
            $this->audit->log('user.'.$action, $account, $before, ['reason' => $data['reason'] ?? null]);

            return back()->with('success', $action === 'ban' ? 'User banned and API sessions revoked.' : 'User unbanned and API sessions revoked.');
        }

        if ($action === 'status') {
            $this->authorizeRoles(['super_admin', 'admin']);
            $data = $request->validate([
                'status' => ['required', Rule::in(['active', 'restricted', 'suspended', 'pending'])],
                'reason' => ['nullable', 'string', 'min:4', 'max:500'],
            ]);
            $before = ['status' => $account->status?->value, 'is_banned' => $account->is_banned, 'ban_reason' => $account->ban_reason];
            $attributes = ['status' => $data['status']];
            if ($data['status'] === 'active' && $account->is_banned) {
                // Un-banning is the only way to make a banned account active again.
                $attributes['is_banned'] = false;
                $attributes['ban_reason'] = null;
            }
            $account->forceFill($attributes)->save();
            $this->audit->log('user.status_changed', $account, $before, ['status' => $data['status'], 'reason' => $data['reason'] ?? null]);

            return back()->with('success', 'Account status set to '.str_replace('_', ' ', $data['status']).'.');
        }

        if ($action === 'verify-email') {
            $this->authorizeRoles(['super_admin', 'admin']);
            abort_if($account->trashed(), 422, 'A deleted account cannot be verified.');
            if ($account->email_verified_at === null) {
                $account->forceFill(['email_verified_at' => now()])->save();
                $this->audit->log('user.email_verified_by_admin', $account, ['email_verified_at' => null], ['email_verified_at' => $account->email_verified_at?->toISOString()]);
            }

            return back()->with('success', 'Email address marked as verified.');
        }

        if ($action === 'revoke-device') {
            $this->authorizeRoles(['super_admin', 'admin', 'moderator']);
            $data = $request->validate(['device_id' => ['required', 'integer']]);
            $device = $account->devices()->whereKey($data['device_id'])->firstOrFail();
            if ($device->revoked_at === null) {
                $device->forceFill(['revoked_at' => now()])->save();
                $this->audit->log('user.device_revoked', $account, null, ['device_id' => $device->id, 'platform' => $device->platform]);
            }

            return back()->with('success', 'Device token revoked. The app will stop receiving push notifications on it.');
        }

        if ($action === 'revoke-sessions') {
            $this->authorizeRoles(['super_admin', 'admin']);
            $revoked = $account->tokens()->delete();
            $this->audit->log('user.sessions_revoked', $account, null, ['revoked_tokens' => $revoked]);

            return back()->with('success', 'All API sessions for this account were revoked.');
        }

        if ($action === 'notify') {
            $this->authorizeRoles(['super_admin', 'admin', 'moderator']);
            abort_if($account->trashed(), 422, 'A deleted account cannot be messaged.');
            $data = $request->validate([
                'title' => ['required', 'string', 'min:3', 'max:120'],
                'body' => ['required', 'string', 'min:3', 'max:1000'],
            ]);
            $notification = Notification::query()->create([
                'user_id' => $account->id,
                'notification_key' => 'admin-message-'.Str::uuid(),
                'type' => 'admin_message',
                'title' => $data['title'],
                'body' => $data['body'],
                'data' => ['sent_by_admin_id' => $admin->id],
            ]);
            $push = $this->pushAdminMessage($notification);
            $this->audit->log('user.notified', $account, null, ['notification_id' => $notification->id, 'title' => $data['title']]);

            return back()->with('success', 'In-app notification created.'.($push ? ' Push delivery attempted on the registered devices.' : ' Push is not configured, so no device notification was sent.'));
        }

        if ($action === 'reset-password') {
            $this->authorizeRoles(['super_admin', 'admin']);
            abort_if($account->trashed(), 422);
            $temporaryPassword = Str::password(20);
            $account->forceFill(['password' => Hash::make($temporaryPassword)])->save();
            $account->tokens()->delete();
            $this->audit->log('user.password_reset_by_admin', $account, null, ['tokens_revoked' => true]);

            return back()->with('temporary_password', $temporaryPassword)
                ->with('success', 'Temporary password created. Copy it now; it is shown once.');
        }

        if ($action === 'delete' || $action === 'restore') {
            $this->authorizeRoles(['super_admin', 'admin']);
            if ($action === 'delete' && ! $account->trashed()) {
                $account->tokens()->delete();
                $account->delete();
                $this->audit->log('user.deleted_by_admin', $account, null, ['soft_deleted_at' => $account->deleted_at?->toISOString()]);
            } elseif ($action === 'restore' && $account->trashed()) {
                $account->restore();
                $account->forceFill(['status' => 'active', 'is_banned' => false, 'ban_reason' => null])->save();
                $this->audit->log('user.restored_by_admin', $account, null, ['status' => 'active']);
            }

            return redirect()->route('admin.users.show', $account->id)->with('success', 'User account updated.');
        }

        abort(404);
    }

    /** @param array<string, string> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        match ($filters['deleted']) {
            'include' => $query->withTrashed(),
            'only' => $query->onlyTrashed(),
            default => $query->withoutTrashed(),
        };

        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $query->where(function (Builder $inner) use ($term, $like): void {
                $inner->where('full_name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('referral_code', 'like', $like);
                if (ctype_digit($term)) {
                    $inner->orWhere('id', (int) $term);
                }
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['country'] !== '') {
            $query->where('country_code', $filters['country']);
        }

        match ($filters['state']) {
            'banned' => $query->where('is_banned', true),
            'active' => $query->where('status', 'active')->where('is_banned', false),
            'verified' => $query->whereNotNull('email_verified_at'),
            'unverified' => $query->whereNull('email_verified_at'),
            'subscribed' => $query->whereHas('subscriptions', $this->activeSubscription()),
            'no_subscription' => $query->whereDoesntHave('subscriptions', $this->activeSubscription()),
            'with_balance' => $query->whereHas('wallets', fn (Builder $wallet) => $wallet->where('balance_minor', '>', 0)),
            default => null,
        };
    }

    private function activeSubscription(): \Closure
    {
        return static fn (Builder $query) => $query
            ->where('status', SubscriptionStatus::Active->value)
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $activeSubscription = $this->activeSubscription();

        return [
            'total' => User::query()->count(),
            'active' => User::query()->where('status', 'active')->where('is_banned', false)->count(),
            'banned' => User::query()->where('is_banned', true)->count(),
            'new_today' => User::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'new_week' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'subscribed' => User::query()->whereHas('subscriptions', $activeSubscription)->count(),
            'deleted' => User::onlyTrashed()->count(),
        ];
    }

    /** @return array<string, int> */
    private function counters(User $account): array
    {
        return [
            'review_tasks' => $account->reviewTasks()->count(),
            'review_submissions' => $account->reviewSubmissions()->count(),
            'ad_shares' => $account->adShareTasks()->count(),
            'campaign_orders' => $account->campaignOrders()->count(),
            'withdrawals' => $account->withdrawalRequests()->count(),
            'withdrawals_pending' => $account->withdrawalRequests()->whereIn('status', ['pending', 'processing'])->count(),
            'referrals' => $account->sentReferrals()->count(),
            'notifications' => $account->notifications()->count(),
            'unread_notifications' => $account->notifications()->whereNull('read_at')->count(),
            'vtu_orders' => $account->vtuOrders()->count(),
            'devices' => $account->devices()->count(),
            'tickets' => $account->supportTickets()->count(),
        ];
    }

    /** @return array<string, int> */
    private function walletTotals(User $account): array
    {
        $totals = [];
        foreach ($account->wallets as $wallet) {
            $currency = (string) $wallet->currency;
            $totals[$currency] = ($totals[$currency] ?? 0) + (int) $wallet->balance_minor;
        }

        return $totals;
    }

    private function findUser(int $id): User
    {
        $this->authorizeAccess();

        return User::withTrashed()
            ->with([
                'country:id,code,name',
                'wallets',
                'payoutProfile.option:id,name',
                'socialAccounts',
                'devices',
                'referralAttribution.referrer:id,username,email',
                'subscriptions' => fn ($query) => $query->with('plan.group')->latest(),
                'packageAddons.addon:id,name',
            ])
            ->findOrFail($id);
    }

    private function activityQuery(User $account): Builder
    {
        return AuditLog::query()
            ->with('adminActor:id,full_name')
            ->where(function (Builder $query) use ($account): void {
                $query->where('actor_user_id', $account->id)
                    ->orWhere(function (Builder $inner) use ($account): void {
                        $inner->where('entity_type', 'user')->where('entity_id', (string) $account->id);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function pushAdminMessage(Notification $notification): bool
    {
        if (! config('services.fcm.enabled')) {
            return false;
        }

        try {
            app(FirebaseCloudMessaging::class)->send($notification);

            return true;
        } catch (Throwable $error) {
            Log::warning('Admin message push failed', ['notification_id' => $notification->id, 'error' => $error->getMessage()]);

            return false;
        }
    }

    private function walletCurrency(User $account): string
    {
        return strtoupper((string) ($account->wallet_currency ?: $account->preferred_currency ?: 'USD'));
    }

    /** @param list<string> $roles */
    private function authorizeRoles(array $roles): void
    {
        abort_unless(in_array((string) (Auth::guard('admin')->user()?->role), $roles, true), 403);
    }

    private function authorizeAccess(): void
    {
        abort_unless(in_array((string) (Auth::guard('admin')->user()?->role), self::VIEW_ROLES, true), 403);
    }
}
