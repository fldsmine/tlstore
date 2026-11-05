<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AnnouncementController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'data' => Announcement::query()->latest('id')->paginate($validated['per_page'] ?? 100),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateAnnouncement($request);
        $this->ensureDismissibleDialogHasAction($data);
        $this->ensureDateRange($data);
        $announcement = Announcement::query()->create($data);
        $this->audit($request->user(), 'announcement.created', $announcement, null, $announcement->toArray());

        return response()->json(['data' => $announcement], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $data = $this->validateAnnouncement($request, true);
        $effective = array_merge($announcement->toArray(), $data);
        $this->ensureDismissibleDialogHasAction($effective);
        $this->ensureDateRange($effective);
        $before = $announcement->toArray();
        $announcement->fill($data)->save();
        $this->audit($request->user(), 'announcement.updated', $announcement, $before, $announcement->fresh()->toArray());

        return response()->json(['data' => $announcement->fresh()]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $before = $announcement->toArray();
        $announcement->delete();
        $this->audit($request->user(), 'announcement.deleted', $announcement, $before, null);

        return response()->json(['message' => 'Announcement deleted.']);
    }

    private function validateAnnouncement(Request $request, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';
		$nullable = $partial ? ['sometimes', 'nullable'] : ['nullable'];

		return $request->validate([
			'title' => [$presence, 'string', 'max:255'],
			'body' => [$presence, 'string', 'max:20000'],

			'action_url' => [...$nullable, 'url', 'max:255'],
			'action_label' => [...$nullable, 'string', 'max:60'],

			'is_dismissible' => ['sometimes', 'boolean'],
			'type' => ['sometimes', 'string', 'in:info,success,warning,danger'],
			'display_type' => ['sometimes', 'integer', 'in:0,1,2'],
			'is_active' => ['sometimes', 'boolean'],

			'starts_at' => [...$nullable, 'date'],
			'ends_at' => [...$nullable, 'date'],
		]);

    }

    private function audit(Admin $admin, string $action, Announcement $announcement, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => $action,
            'entity_type' => 'announcement',
            'entity_id' => (string) $announcement->id,
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }

    private function ensureDismissibleDialogHasAction(array $data): void
    {
        if ((int) ($data['display_type'] ?? 0) === 0
            && ($data['is_dismissible'] ?? true) === false
            && empty($data['action_url'])) {
            throw ValidationException::withMessages([
                'action_url' => 'A non-dismissible pop-up must include an action link so users can continue.',
            ]);
        }
    }

    private function ensureDateRange(array $data): void
    {
        if (! empty($data['starts_at']) && ! empty($data['ends_at'])
            && strtotime((string) $data['ends_at']) < strtotime((string) $data['starts_at'])) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end time must be on or after the start time.',
            ]);
        }
    }
}
