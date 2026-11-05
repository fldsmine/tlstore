<?php

namespace App\Features\System\Http\Controllers\Api\V1;

use App\Application\Support\Media;
use App\Models\AppVersion;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AppVersionController
{
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'build' => ['required', 'integer', 'min:0'],
        ]);
        $latest = AppVersion::query()->where('platform', $data['platform'])->where('is_active', true)
            ->whereNotNull('published_at')->where('published_at', '<=', now())
            ->orderByDesc('build_number')->first();
        if (! $latest) return response()->json(['data' => ['update_available' => false, 'force_update' => false, 'latest' => null, 'changes' => []]]);

        $available = $latest->build_number > (int) $data['build'];
        $force = $available && ($latest->force_update || $latest->minimum_supported_build > (int) $data['build']);
        $download = $latest->platform === 'ios' ? $latest->app_store_url : ($latest->play_store_url ?: $latest->direct_download_url);
        if (! $download && $latest->direct_download_path) $download = Media::url($latest->direct_download_path);
        $changes = AppVersion::query()->where('platform', $latest->platform)->where('is_active', true)
            ->whereNotNull('published_at')->where('published_at', '<=', now())->where('build_number', '>', (int) $data['build'])
            ->orderByDesc('build_number')->limit(10)->get(['version_name', 'build_number', 'title', 'release_notes']);

        return response()->json(['data' => [
            'update_available' => $available,
            'force_update' => $force,
            'download_url' => $download,
            'latest' => [
                'version_name' => $latest->version_name, 'build_number' => $latest->build_number,
                'minimum_supported_build' => $latest->minimum_supported_build, 'title' => $latest->title,
                'release_notes' => $latest->release_notes, 'direct_download_url' => $latest->direct_download_url,
                'play_store_url' => $latest->play_store_url, 'app_store_url' => $latest->app_store_url,
                'force_update' => $force, 'published_at' => $latest->published_at,
            ],
            'changes' => $changes,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['platform' => ['sometimes', Rule::in(['android', 'ios'])], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = AppVersion::query()->with('creator:id,full_name')->orderByDesc('created_at');
        if (isset($data['platform'])) $query->where('platform', $data['platform']);
        $page = $query->paginate($data['per_page'] ?? 30);
        $page->getCollection()->transform(fn (AppVersion $version) => $this->present($version));
        return response()->json(['data' => $page]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $publish = $request->boolean('publish_now');
        unset($data['apk'], $data['publish_now'], $data['remove_apk']);
        if ($request->hasFile('apk')) $data['direct_download_path'] = $request->file('apk')->store('app-releases', 'public');
        if ($publish) $data['published_at'] = now();
        $version = AppVersion::query()->create($data);
        $this->audit($request, 'app_version.created', $version, null);
        return response()->json(['data' => $this->present($version)], 201);
    }

    public function update(Request $request, AppVersion $appVersion): JsonResponse
    {
        $before = $appVersion->only(['platform', 'version_name', 'build_number', 'minimum_supported_build', 'title', 'release_notes', 'force_update', 'is_active', 'published_at']);
        $data = $this->validated($request, $appVersion);
        unset($data['apk'], $data['publish_now'], $data['remove_apk']);
        if ($request->boolean('remove_apk')) {
            if ($appVersion->direct_download_path) Storage::disk('public')->delete($appVersion->direct_download_path);
            $data['direct_download_path'] = null;
        }
        if ($request->hasFile('apk')) {
            if ($appVersion->direct_download_path) Storage::disk('public')->delete($appVersion->direct_download_path);
            $data['direct_download_path'] = $request->file('apk')->store('app-releases', 'public');
        }
        if ($request->boolean('publish_now') && ! $appVersion->published_at) $data['published_at'] = now();
        $appVersion->fill($data)->save();
        $this->audit($request, 'app_version.updated', $appVersion, $before);
        return response()->json(['data' => $this->present($appVersion->refresh())]);
    }

    public function destroy(Request $request, AppVersion $appVersion): JsonResponse
    {
        $before = $appVersion->only(['platform', 'version_name', 'build_number', 'is_active', 'published_at']);
        if ($appVersion->direct_download_path) Storage::disk('public')->delete($appVersion->direct_download_path);
        $this->audit($request, 'app_version.deleted', $appVersion, $before);
        $appVersion->delete();
        return response()->json(['message' => 'Release deleted.']);
    }

    private function validated(Request $request, ?AppVersion $existing = null): array
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'version_name' => ['required', 'string', 'max:32'],
            'build_number' => ['required', 'integer', 'min:1', Rule::unique('app_versions')->where(fn ($q) => $q->where('platform', $request->input('platform')))->ignore($existing?->id)],
            'minimum_supported_build' => ['nullable', 'integer', 'min:0'],
            'title' => ['nullable', 'string', 'max:180'], 'release_notes' => ['nullable', 'string', 'max:10000'],
            'direct_download_url' => ['nullable', 'url', 'max:2048'], 'play_store_url' => ['nullable', 'url', 'max:2048'], 'app_store_url' => ['nullable', 'url', 'max:2048'],
            'force_update' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
            'publish_now' => ['sometimes', 'boolean'], 'remove_apk' => ['sometimes', 'boolean'],
            'apk' => ['nullable', 'file', 'mimes:apk', 'max:204800'],
        ]);
        $data['minimum_supported_build'] = $data['minimum_supported_build'] ?? 0;
        return $data;
    }

    private function present(AppVersion $version): array
    {
        $row = $version->toArray();
        $row['apk_url'] = $version->direct_download_path ? Media::url($version->direct_download_path) : null;
        $row['creator'] = $version->creator?->only(['id', 'full_name']);
        return $row;
    }

    private function audit(Request $request, string $action, AppVersion $version, ?array $before): void
    {
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'app_version',
            'entity_id' => (string) $version->id,
            'before_values' => $before,
            'after_values' => $action === 'app_version.deleted' ? null : $version->only(['platform', 'version_name', 'build_number', 'minimum_supported_build', 'title', 'release_notes', 'force_update', 'is_active', 'published_at']),
            'request_id' => $request->header('X-Request-ID'),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
            'created_at' => now(),
        ]);
    }
}
