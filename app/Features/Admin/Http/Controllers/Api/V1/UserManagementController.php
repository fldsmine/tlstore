<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ReferralAttribution;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UserManagementController
{
    public function show(Request $request, int $user): JsonResponse
    {
        $account = User::withTrashed()->with([
            'country:code,name',
            'payoutProfile.option',
            'socialAccounts',
            'socialIdentities',
            'wallets',
        ])->findOrFail($user);

        $wallets = $account->wallets()->orderBy('wallet_type')->orderBy('currency')->get();
        $subscriptions = $account->subscriptions()->with(['plan.group', 'paymentIntent'])
            ->latest('id')->limit(30)->get();
        $addons = $account->packageAddons()->with(['addon', 'paymentIntent'])
            ->latest('id')->limit(30)->get();
        $reviewTasks = $account->reviewTasks()->with(['campaign.app', 'submission'])
            ->latest('id')->limit(30)->get();
        $shareTasks = $account->adShareTasks()->with(['ad', 'events'])
            ->latest('id')->limit(30)->get();
        $transactions = $account->transactions()->latest('created_at')->latest('id')
            ->limit(30)->get(['id', 'type', 'direction', 'amount', 'fee', 'wallet', 'status', 'reference', 'description', 'meta', 'created_at']);
        $withdrawals = $account->withdrawalRequests()->with(['wallet', 'option', 'userSubscription.plan.group'])
            ->latest('id')->limit(30)->get();
        $deposits = $account->manualDeposits()->with('paymentIntent:id,provider,provider_reference,status,amount_minor,currency')
            ->latest('id')->limit(30)->get();
        $paymentIntents = $account->paymentIntents()->latest('id')->limit(30)->get([
            'id', 'provider', 'purpose', 'status', 'amount_minor', 'currency',
            'provider_reference', 'created_at', 'paid_at', 'expires_at',
        ]);
        $socialFollows = $account->socialFollows()->with('socialHandle')
            ->latest('id')->limit(30)->get();
        $referrals = ReferralAttribution::query()->where('referrer_user_id', $account->id)
            ->with('referredUser:id,full_name,username,email,status,created_at')
            ->withCount(['rewards as rewards_count' => fn ($query) => $query->where('beneficiary_user_id', $account->id)])
            ->latest('id')->limit(30)->get();

        $userData = [
            'id' => $account->id,
            'full_name' => $account->full_name,
            'username' => $account->username,
            'email' => $account->email,
            'phone' => $account->phone,
            'avatar' => \App\Application\Support\Media::url($account->avatar),
            'cover_image' => \App\Application\Support\Media::url($account->cover_image),
            'country_code' => $account->country_code,
            'country_name' => $account->country?->name,
            'preferred_currency' => $account->preferred_currency,
            'wallet_currency' => $account->wallet_currency ?: $account->preferred_currency,
            'role' => $account->role?->value,
            'status' => $account->status?->value,
            'is_banned' => (bool) $account->is_banned,
            'ban_reason' => $account->ban_reason,
            'email_verified_at' => $account->email_verified_at,
            'two_factor' => (bool) $account->two_factor,
            'security_pin_set' => filled($account->security_pin),
            'created_at' => $account->created_at,
            'deleted_at' => $account->deleted_at,
            'payout_profile' => $account->payoutProfile,
            'social_accounts' => $account->socialAccounts,
            'social_identities' => $account->socialIdentities->map(fn ($identity) => [
                'provider' => $identity->provider,
                'provider_user_id' => $identity->provider_user_id,
                'created_at' => $identity->created_at,
            ])->values(),
            'referrer' => $account->referralAttribution()->with('referrer:id,full_name,username,email')->first(),
        ];

        return response()->json(['data' => [
            'user' => $userData,
            'wallets' => $wallets,
            'subscriptions' => $subscriptions,
            'package_addons' => $addons,
            'review_tasks' => $reviewTasks,
            'ad_share_tasks' => $shareTasks,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'manual_deposits' => $deposits,
            'payment_intents' => $paymentIntents,
            'social_follows' => $socialFollows,
            'referrals' => $referrals,
            'stats' => [
                'review_tasks' => $account->reviewTasks()->count(),
                'reviews_submitted' => $account->reviewSubmissions()->count(),
                'ad_share_tasks' => $account->adShareTasks()->count(),
                'ad_shares_submitted' => $account->adShareTasks()->whereHas('events')->count(),
                'subscriptions' => $account->subscriptions()->count(),
                'withdrawals' => $account->withdrawalRequests()->count(),
                'transactions' => $account->transactions()->count(),
                'payment_intents' => $account->paymentIntents()->count(),
                'social_follows' => $account->socialFollows()->count(),
                'referrals' => ReferralAttribution::query()->where('referrer_user_id', $account->id)->count(),
            ],
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,restricted,suspended,pending,banned'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
        ]);
        $query = User::query()->select([
            'id', 'full_name', 'username', 'email', 'phone', 'country_code', 'preferred_currency', 'wallet_currency',
            'role', 'status', 'is_banned', 'ban_reason', 'created_at',
            'deleted_at',
        ]);
        if ($request->filled('status')) $query->where('status', (string) $request->input('status'));
        if ($request->filled('country_code')) $query->where('country_code', strtoupper((string) $request->input('country_code')));
        if ($request->filled('search')) {
            $needle = '%'.(string) $request->input('search').'%';
            $query->where(fn ($builder) => $builder->where('full_name', 'like', $needle)
                ->orWhere('username', 'like', $needle)
                ->orWhere('email', 'like', $needle)
                ->orWhere('phone', 'like', $needle));
        }

        if ($request->boolean('include_deleted')) $query->withTrashed();
        $users = $query->with(['wallets:id,user_id,wallet_type,currency,balance_minor,status'])
            ->latest()->paginate($request->integer('per_page', 30));

        return response()->json(['data' => $users]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', 'in:reviewer,owner'],
            'status' => ['sometimes', 'in:active,restricted,suspended,pending,banned'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'username' => ['sometimes', 'nullable', 'string', 'max:191', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'preferred_currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'country_code' => ['sometimes', 'nullable', 'exists:countries,code'],
            'is_banned' => ['sometimes', 'boolean'],
            'ban_reason' => ['required_if:is_banned,true', 'nullable', 'string', 'max:2000'],
        ]);
        $before = $user->only(array_keys($data));
        if (isset($data['country_code'])) $data['country_code'] = strtoupper($data['country_code']);
        if (($data['is_banned'] ?? false) === true) $data['status'] = 'banned';
        if (($data['is_banned'] ?? false) === false) {
            $data['ban_reason'] = null;
            if (! array_key_exists('status', $data) && $user->status?->value === 'banned') $data['status'] = 'active';
        }
        $user->fill($data)->save();
        AuditLog::query()->create([
            'actor_admin_id' => $request->user()->id, 'action' => 'user.updated', 'entity_type' => 'user',
            'entity_id' => (string) $user->id, 'before_values' => $before,
            'after_values' => $user->only(array_keys($data)), 'created_at' => now(),
        ]);

        return response()->json(['data' => $user->refresh()]);
    }

    public function walletAdjustment(Request $request, User $user): JsonResponse
    {
        if ($user->trashed()) {
            throw ValidationException::withMessages(['user' => 'A deleted account cannot have wallet adjustments.']);
        }
        $data = $request->validate([
            'wallet_type' => ['required', 'in:review,review_group_b,review_group_c,share,referral,topup_fund,bonus'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'direction' => ['required', 'in:credit,debit'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $walletCurrency = strtoupper($user->wallet_currency ?: $user->preferred_currency ?: 'USD');
        if ($data['currency'] !== $walletCurrency) {
            throw ValidationException::withMessages([
                'currency' => "All wallet adjustments must use this user's wallet currency ({$walletCurrency}).",
            ]);
        }

        $entry = app(\App\Application\Actions\Admin\AdjustWalletBalance::class)->handle(
            $request->user(), $user, $data['wallet_type'], $data['currency'], $data['direction'],
            (int) $data['amount_minor'], $data['reason'], $data['idempotency_key'],
        );

        return response()->json(['message' => 'Wallet adjusted.', 'data' => $entry], 201);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:4', 'max:500']]);
        if ($user->trashed()) {
            throw ValidationException::withMessages(['user' => 'A deleted account cannot have its password reset.']);
        }
        $temporaryPassword = Str::password(18);
        $user->forceFill(['password' => $temporaryPassword])->save();
        $user->tokens()->delete();
        AuditLog::query()->create([
            'actor_admin_id' => $request->user()->id,
            'action' => 'user.password_reset_by_admin',
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'after_values' => ['reason' => $data['reason'], 'tokens_revoked' => true],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Password reset. Share the temporary password securely; it is shown only once.', 'data' => ['temporary_password' => $temporaryPassword]]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:4', 'max:500']]);
        $before = ['id' => $user->id, 'email' => $user->email, 'status' => $user->status?->value];
        $user->tokens()->delete();
        $user->delete();
        AuditLog::query()->create([
            'actor_admin_id' => $request->user()->id,
            'action' => 'user.deleted_by_admin',
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'before_values' => $before,
            'after_values' => ['reason' => $data['reason'], 'deleted_at' => $user->deleted_at?->toISOString()],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'User account soft-deleted and active sessions revoked.']);
    }

    public function restore(Request $request, int $user): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:4', 'max:500']]);
        $account = User::withTrashed()->findOrFail($user);
        if (! $account->trashed()) {
            return response()->json(['message' => 'User account is already active.', 'data' => $account]);
        }

        $before = ['id' => $account->id, 'email' => $account->email, 'deleted_at' => $account->deleted_at?->toISOString()];
        $account->restore();
        $account->forceFill(['status' => 'active', 'is_banned' => false, 'ban_reason' => null])->save();
        AuditLog::query()->create([
            'actor_admin_id' => $request->user()->id,
            'action' => 'user.restored_by_admin',
            'entity_type' => 'user',
            'entity_id' => (string) $account->id,
            'before_values' => $before,
            'after_values' => ['reason' => $data['reason'], 'status' => 'active'],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'User account restored.', 'data' => $account->refresh()]);
    }
}
