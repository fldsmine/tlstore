<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Application\Settings\SystemSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Dedicated settings screen for `system_settings`.
 *
 * Replaces the JSON textarea in the generic module editor with typed controls,
 * grouped one tab per settings group. Access mirrors the `system-settings`
 * module: super admins only.
 */
final class SystemSettingsController
{
    private const FALLBACK_TAB = 'general';

    public function __construct(private readonly SystemSettingsManager $manager) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $settings = $this->manager->settings();
        $tabs = $this->manager->tabs($settings);
        $tab = $this->resolveTab($request->query('tab', $request->old('tab')), $tabs);

        return view('admin.settings.index', [
            'title' => 'System settings',
            'tabs' => $tabs,
            'activeTab' => $tab,
            'activeTabMeta' => $tabs[$tab],
            'fields' => $this->manager->fields($tab, $settings),
            'sensitiveKeys' => $this->manager->sensitiveKeys($settings),
            'settingCount' => $settings->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $settings = $this->manager->settings();
        $tabs = $this->manager->tabs($settings);
        $tab = $this->resolveTab($request->input('tab'), $tabs);
        $fields = $this->manager->fields($tab, $settings);

        $validated = $request->validate(
            $this->manager->rules($fields),
            [],
            $this->attributeNames($fields),
        );

        $adminId = Auth::guard('admin')->id() !== null ? (int) Auth::guard('admin')->id() : null;
        $sensitiveKeys = $this->manager->sensitiveKeys($settings);

        $changes = DB::transaction(function () use ($fields, $validated, $settings, $adminId, $sensitiveKeys, $request): array {
            $changes = $this->manager->save($fields, $validated, $settings, $adminId);
            $this->manager->audit($changes, $sensitiveKeys, $adminId, $request->ip());

            return $changes;
        });

        // The public payload for the website/app is cached; drop it so edited
        // public settings take effect immediately (same as the JSON API does).
        Cache::forget('api:frontend:v2');
        Cache::forget('api:frontend:v3');

        $message = $changes === []
            ? 'Nothing changed — the saved values already matched.'
            : 'Saved '.count($changes).' '.Str::plural('setting', count($changes)).'.';

        return redirect()
            ->route('admin.settings.index', ['tab' => $tab])
            ->with('success', $message);
    }

    private function authorizeAccess(): void
    {
        // `system-settings` is super-admin only in AdminConsoleController.
        abort_unless(Auth::guard('admin')->user()?->role === 'super_admin', 403);
    }

    /** @param array<string, array<string, mixed>> $tabs */
    private function resolveTab(mixed $tab, array $tabs): string
    {
        $tab = is_string($tab) ? $tab : '';

        return array_key_exists($tab, $tabs) ? $tab : self::FALLBACK_TAB;
    }

    /**
     * Human labels for validation errors, e.g. "Brand name" instead of
     * "values.about::brand_name".
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, string>
     */
    private function attributeNames(array $fields): array
    {
        $names = [];

        foreach ($fields as $field) {
            $names[$field['rule_name']] = Str::lower($field['label']);
            $names[$field['rule_name'].'.*'] = Str::lower($field['label']);
            foreach (['names', 'urls'] as $part) {
                $names[$field['rule_name'].'.'.$part] = Str::lower($field['label']);
                $names[$field['rule_name'].'.'.$part.'.*'] = Str::lower($field['label']);
            }
            foreach (array_keys((array) $field['options']) as $option) {
                $names[$field['rule_name'].'.'.$option] = Str::lower($field['label']);
            }
            $names[$field['rule_name'].'.rows'] = Str::lower($field['label']);
            foreach (['platform', 'action', 'value'] as $part) {
                $names[$field['rule_name'].'.rows.*.'.$part] = Str::lower($field['label']);
            }
            $names['public.'.$field['form_key']] = 'public flag for '.Str::lower($field['label']);
        }

        return $names;
    }
}
