<?php

namespace App\Livewire\Dashboard\Vas;

use App\Models\VtuOrder;
use App\Services\Vas\VtuCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Purchase Receipt')]
class VtuSuccess extends Component
{
    public string $ref = '';

    public function mount(): void
    {
        $this->ref = (string) request()->query('ref', '');
    }

    public function render(): View
    {
        $order = VtuOrder::query()
            ->where('user_id', auth()->id())
            ->where('request_id', $this->ref)
            ->first();

        abort_if($order === null, 404);

        return view('livewire.dashboard.vas.vtu-success', [
            'order' => $order,
            'category' => VtuCatalog::category($order->service),
        ]);
    }
}
