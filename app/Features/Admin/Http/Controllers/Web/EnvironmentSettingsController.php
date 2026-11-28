<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Application\Settings\EnvironmentFileException;
use App\Application\Settings\EnvironmentSettingsManager;
use App\Application\Settings\EnvironmentSettingsSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes provider credentials and switches to the deployment's `.env` file from
 * the settings console. Super-admin only, like every other settings write.
 */
final class EnvironmentSettingsController
{
    public function update(Request $request, EnvironmentSettingsManager $manager): RedirectResponse
    {
        $this->authorizeAccess();

        $redirect = redirect()->route('admin.settings.index', ['tab' => 'environment']);

        $problem = $manager->problem();
        if ($problem !== null) {
            return $redirect->withErrors(['environment' => $problem]);
        }

        $validated = $request->validate($manager->rules(), [], $this->attributeNames());

        $adminId = Auth::guard('admin')->id();
        $adminId = $adminId !== null ? (int) $adminId : null;

        try {
            $changes = $manager->save($validated);
        } catch (EnvironmentFileException $exception) {
            return $redirect->withErrors(['environment' => $exception->getMessage()]);
        }

        if ($changes !== []) {
            DB::transaction(fn () => $manager->audit($changes, $adminId, $request->ip()));
            $this->refreshConfiguration();
        }

        return $redirect->with('success', $changes === []
            ? 'Nothing changed — every submitted value already matched.'
            : 'Saved '.count($changes).' '.Str::plural('variable', count($changes)).'. The new values apply on the next request; queue workers were restarted.');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::guard('admin')->user()?->role === 'super_admin', 403);
    }

    /**
     * The environment itself changes underneath a cached config, and queue
     * workers hold their own copy of it, so both are refreshed.
     */
    private function refreshConfiguration(): void
    {
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        try {
            Artisan::call('queue:restart');
        } catch (Throwable) {
            // A queue driver without a cache backend must not fail the save.
        }
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        $names = [];

        foreach (EnvironmentSettingsSchema::keys() as $key => $definition) {
            $names['values.'.$key] = Str::lower($definition['provider_label'].' '.$definition['label']);
        }

        return $names;
    }
}
