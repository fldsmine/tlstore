<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CountryController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Country::query()->orderBy('name')->paginate(100)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'size:2', 'alpha', 'unique:countries,code'],
            'name' => ['required', 'string', 'max:120'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'is_available' => ['sometimes', 'boolean'],
        ]);
        $country = Country::query()->create($data);
        $this->audit($request->user(), 'country.created', $country, null, $data);

        return response()->json(['data' => $country], 201);
    }

    public function update(Request $request, Country $country): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'default_currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'is_available' => ['sometimes', 'boolean'],
        ]);
        $before = $country->only(array_keys($data));
        $country->fill($data)->save();
        $this->audit($request->user(), 'country.updated', $country, $before, $country->only(array_keys($data)));

        return response()->json(['data' => $country->refresh()]);
    }

    private function audit(Admin $admin, string $action, Country $country, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => 'country', 'entity_id' => $country->code,
            'before_values' => $before, 'after_values' => $after, 'created_at' => now(),
        ]);
    }
}
