<?php

namespace App\Livewire\Backoffice\Concerns;

use Illuminate\Database\QueryException;

trait AuthorizesAdminActions
{
    protected function authorizeWrite(): void
    {
        abort_unless(auth('admin')->user()?->canWrite() ?? false, 403);
    }

    public function canWrite(): bool
    {
        return auth('admin')->user()?->canWrite() ?? false;
    }

    protected function safeDelete(\Closure $delete, string $label = 'item', ?string $successMessage = null): void
    {
        try {
            $delete();
            $this->dispatch('toast', message: $successMessage ?? ucfirst($label).' deleted.', type: 'success');
        } catch (QueryException $e) {
            $this->dispatch('toast', message: 'Cannot delete this '.$label.' — it is still in use. Disable it instead.', type: 'warning');
        }
    }
}
