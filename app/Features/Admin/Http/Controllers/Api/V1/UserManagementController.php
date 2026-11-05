<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserManagementController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->select([
            'id', 'full_name', 'username', 'email', 'phone', 'country_code', 'preferred_currency',
            'role', 'status', 'is_banned', 'ban_reason', 'created_at',
        ]);
        if ($request->filled('status')) $query->where('status', (string) $request->input('status'));
        if ($request->filled('country_code')) $query->where('country_code', strtoupper((string) $request->input('country_code')));
        if ($request->filled('search')) {
            $needle = '%'.(string) $request->input('search').'%';
            $query->where(fn ($builder) => $builder->where('full_name', 'like', $needle)->orWhere('username', 'like', $needle)->orWhere('email', 'like', $needle));
        }

        return response()->json(['data' => $query->latest()->paginate(30)]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['sometimes', 'in:reviewer,owner'],
            'status' => ['sometimes', 'in:active,restricted,suspended,pending,banned'],
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
}
