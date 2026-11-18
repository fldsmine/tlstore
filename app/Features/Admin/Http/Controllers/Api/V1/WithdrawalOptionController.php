<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\WithdrawalOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WithdrawalOptionController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => WithdrawalOption::query()->orderBy('order')->paginate(100)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'option_id' => ['required', 'string', 'max:191', 'alpha_dash', 'unique:withdrawal_options,option_id'],
            'name' => ['required', 'string', 'max:191'], 'icon' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string'], 'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'max:80', 'distinct'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'tax' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_withdrawal' => ['required', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'], 'order' => ['sometimes', 'integer'],
        ]);
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        $option = WithdrawalOption::query()->create($data);
        $this->audit($request->user(), 'withdrawal_option.created', $option, null, $data);

        return response()->json(['data' => $option], 201);
    }

    public function update(Request $request, WithdrawalOption $option): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'], 'icon' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'], 'fields' => ['sometimes', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'max:80', 'distinct'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'tax' => ['sometimes', 'numeric', 'min:0', 'max:100'], 'min_withdrawal' => ['sometimes', 'numeric', 'min:0'],
            'is_available' => ['sometimes', 'boolean'], 'order' => ['sometimes', 'integer'],
        ]);
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        $before = $option->only(array_keys($data));
        $option->fill($data)->save();
        $this->audit($request->user(), 'withdrawal_option.updated', $option, $before, $option->only(array_keys($data)));

        return response()->json(['data' => $option->refresh()]);
    }

    private function audit(Admin $admin, string $action, WithdrawalOption $option, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => 'withdrawal_option',
            'entity_id' => (string) $option->id, 'before_values' => $before, 'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
