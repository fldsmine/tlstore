<?php

namespace App\Livewire\Concerns;

use App\Models\Transaction;

trait InteractsWithTransactionDetails
{
    public ?string $selected = null;

    public function showDetails(string $id): void
    {
        $this->selected = $id;
    }

    public function closeDetails(): void
    {
        $this->selected = null;
    }

    protected function selectedTransaction(): ?Transaction
    {
        return $this->selected
            ? auth()->user()->transactions()->find($this->selected)
            : null;
    }
}
