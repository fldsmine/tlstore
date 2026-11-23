<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Backups — Backoffice')]
class BackupSetting extends Component
{
    use AuthorizesAdminActions;

    public bool $enabled = false;

    public string $keepDays = '';

    public function mount(): void
    {
        $this->enabled = (bool) config('settings.backup.enabled');
        $this->keepDays = (string) config('settings.backup.keep_days');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'keepDays' => ['required', 'integer', 'min:1'],
        ], attributes: ['keepDays' => 'keep days']);

        update_setting([
            'enable_backup' => $this->enabled,
            'backup_keep_days' => (int) $this->keepDays,
        ]);

        $this->dispatch('toast', message: 'Backup settings saved successfully', type: 'success');
    }

    public function runBackup(): void
    {
        $this->authorizeWrite();

        if (! $this->isConfigured()) {
            $this->dispatch('toast', message: 'Configure S3 storage first — set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_BUCKET in the environment.', type: 'warning');

            return;
        }

        Artisan::queue('backup:run');

        $this->dispatch('toast', message: 'Backup started in the background — refresh in a minute to see it below.', type: 'success');
    }

    protected function isConfigured(): bool
    {
        return filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.secret'))
            && filled(config('filesystems.disks.s3.bucket'));
    }

    public function render(): View
    {

        $iso = Cache::get('backup:last_success_at');

        return view('livewire.backoffice.settings.backup-setting', [
            'configured' => $this->isConfigured(),
            'bucket' => (string) config('filesystems.disks.s3.bucket'),
            'lastBackupAt' => filled($iso) ? Carbon::parse($iso)->format('j M Y, g:i A') : null,
        ]);
    }
}
