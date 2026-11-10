<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\App as StoreApp;
use App\Models\AppStoreListingSnapshot;
use App\Models\AppScreenshot;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

final class AppCatalogController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => StoreApp::query()->with(['screenshots', 'storeListingSnapshots'])->latest()->paginate(30)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prototype_id' => ['nullable', 'integer', 'min:1', 'unique:apps,prototype_id'],
            'name' => ['required', 'string', 'max:180'],
            'short_code' => ['required', 'string', 'max:12'],
            'store' => ['required', 'string', 'max:80'],
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'developer' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:100'],
            'install_count_label' => ['nullable', 'string', 'max:32'],
            'description' => ['required', 'string'],
            'app_icon' => ['required_without:app_icon_file', 'nullable', 'url', 'max:255'],
            'app_icon_file' => ['required_without:app_icon', 'image', 'max:5120'],
            'store_url' => ['required', 'url', 'max:2048'],
            'featured' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,pending,rejected,draft'],
        ]);
        $icon = isset($data['app_icon_file']) ? $data['app_icon_file']->store('app-icons', 'public') : $data['app_icon'];
        $app = StoreApp::query()->create([
            'prototype_id' => $data['prototype_id'] ?? null,
            'name' => $data['name'], 'short_code' => $data['short_code'], 'store' => $data['store'],
            'brand_color' => $data['brand_color'], 'developer' => $data['developer'], 'category' => $data['category'],
            'install_count_label' => $data['install_count_label'] ?? null, 'description' => $data['description'], 'app_icon' => $icon,
            'featured' => $data['featured'] ?? false,
            'status' => $data['status'] ?? 'active',
        ]);
        AppStoreListingSnapshot::query()->create([
            'app_id' => $app->id, 'store' => $app->store, 'source_url' => $data['store_url'],
            'captured_at' => now(), 'metadata' => ['source' => 'admin_curated'],
        ]);
        $this->audit($request->user(), 'app_listing.created', 'app', (string) $app->id, null, ['name' => $app->name]);
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $app->load(['screenshots', 'storeListingSnapshots'])], 201);
    }

    public function update(Request $request, StoreApp $app): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'], 'short_code' => ['sometimes', 'string', 'max:12'],
            'store' => ['sometimes', 'string', 'max:80'], 'brand_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'developer' => ['sometimes', 'string', 'max:180'], 'category' => ['sometimes', 'string', 'max:100'],
            'install_count_label' => ['sometimes', 'nullable', 'string', 'max:32'], 'description' => ['sometimes', 'string'],
            'app_icon' => ['sometimes', 'url', 'max:255'],
            'featured' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:active,pending,rejected,draft'],
        ]);
        $before = $app->only(array_keys($data));
        $app->fill($data)->save();
        $this->audit($request->user(), 'app_listing.updated', 'app', (string) $app->id, $before, $app->only(array_keys($data)));
        Cache::forget('api:login-spot:v3');

        return response()->json(['data' => $app->refresh()->load(['screenshots', 'storeListingSnapshots'])]);
    }

    public function addScreenshot(Request $request, StoreApp $app): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'max:10240'], 'alt_text' => ['required', 'string', 'max:255']]);
        $path = $data['image']->store('app-screenshots/'.$app->id, 'public');
        $screenshot = $app->screenshots()->create([
            'position' => (int) $app->screenshots()->max('position') + 1,
            'source_type' => 'uploaded', 'storage_disk' => 'public', 'storage_path' => $path,
            'alt_text' => $data['alt_text'] ?? null,
        ]);
        $this->audit($request->user(), 'app_screenshot.created', 'app_screenshot', (string) $screenshot->id, null, ['app_id' => $app->id, 'position' => $screenshot->position]);

        return response()->json(['data' => $screenshot], 201);
    }

    public function deleteScreenshot(Request $request, StoreApp $app, AppScreenshot $screenshot): JsonResponse
    {
        abort_unless($screenshot->app_id === $app->id, 404);
        $before = $screenshot->only(['app_id', 'position', 'source_type', 'storage_path', 'alt_text']);
        if ($screenshot->storage_path && $screenshot->storage_disk) {
            Storage::disk($screenshot->storage_disk)->delete($screenshot->storage_path);
        }
        $this->audit($request->user(), 'app_screenshot.deleted', 'app_screenshot', (string) $screenshot->id, $before, []);
        $screenshot->delete();

        return response()->json(['message' => 'Screenshot removed.']);
    }

    private function audit(Admin $admin, string $action, string $type, string $id, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id,
            'before_values' => $before, 'after_values' => $after, 'created_at' => now(),
        ]);
    }
}
