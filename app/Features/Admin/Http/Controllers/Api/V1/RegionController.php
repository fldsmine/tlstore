<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RegionController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Region::query()->with('countries:code,name')->orderBy('name')->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:regions,code'],
            'name' => ['required', 'string', 'max:120'],
            'is_available' => ['sometimes', 'boolean'],
            'country_codes' => ['required', 'array', 'min:1'],
            'country_codes.*' => ['required', 'string', 'size:2', 'exists:countries,code', 'distinct'],
        ]);
        $region = DB::transaction(function () use ($request, $data): Region {
            $region = Region::query()->create([
                'code' => $data['code'], 'name' => $data['name'], 'is_available' => $data['is_available'] ?? true,
            ]);
            $region->countries()->sync($data['country_codes']);
            $this->audit($request->user(), 'region.created', $region, null, $data);
            return $region;
        });

        return response()->json(['data' => $region->load('countries:code,name')], 201);
    }

    public function update(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'is_available' => ['sometimes', 'boolean'],
            'country_codes' => ['sometimes', 'array', 'min:1'],
            'country_codes.*' => ['required', 'string', 'size:2', 'exists:countries,code', 'distinct'],
        ]);
        $before = $region->load('countries:code')->toArray();
        DB::transaction(function () use ($request, $region, $data, $before): void {
            $region->fill(array_intersect_key($data, array_flip(['name', 'is_available'])))->save();
            if (array_key_exists('country_codes', $data)) {
                $region->countries()->sync($data['country_codes']);
            }
            $this->audit($request->user(), 'region.updated', $region, $before, $data);
        });

        return response()->json(['data' => $region->refresh()->load('countries:code,name')]);
    }

    private function audit(Admin $admin, string $action, Region $region, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => 'region',
            'entity_id' => (string) $region->id, 'before_values' => $before, 'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
