<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\UserRankLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserRankLevelController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => UserRankLevel::query()->withCount('qualifications')->orderBy('sort_order')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $level = UserRankLevel::query()->create($data);
        $this->audit($request, 'user_rank_level.created', $level, null, $data);
        return response()->json(['data' => $level], 201);
    }

    public function update(Request $request, UserRankLevel $rankLevel): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $before = $rankLevel->only(array_keys($data));
        $rankLevel->fill($data)->save();
        $this->audit($request, 'user_rank_level.updated', $rankLevel, $before, $rankLevel->only(array_keys($data)));
        return response()->json(['data' => $rankLevel->refresh()]);
    }

    public function destroy(Request $request, UserRankLevel $rankLevel): JsonResponse
    {
        if ($rankLevel->qualifications()->exists()) {
            return response()->json(['message' => 'This level already has user qualifications. Disable it instead of deleting it.'], 409);
        }
        $before = $rankLevel->toArray();
        $rankLevel->delete();
        $this->audit($request, 'user_rank_level.deleted', $rankLevel, $before, null);
        return response()->json(['message' => 'Ranking level deleted.']);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'level' => [$required, 'integer', 'between:1,99', 'unique:user_rank_levels,level'.($partial ? ','.$this->routeLevelId() : '')],
            'name' => [$required, 'string', 'max:60'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'minimum_subscribed_referrals' => [$required, 'integer', 'min:0'],
            'minimum_referral_cash_inflow_usd' => [$required, 'numeric', 'min:0'],
            'minimum_user_cash_inflow_usd' => [$required, 'numeric', 'min:0'],
            'monthly_reward_usd' => [$required, 'numeric', 'min:0'],
            'reward_duration_months' => [$required, 'integer', 'between:1,36'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,99'],
        ];
    }

    private function routeLevelId(): string
    {
        return (string) request()->route('rankLevel')?->id;
    }

    private function audit(Request $request, string $action, UserRankLevel $level, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'user_rank_level',
            'entity_id' => (string) $level->id,
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
