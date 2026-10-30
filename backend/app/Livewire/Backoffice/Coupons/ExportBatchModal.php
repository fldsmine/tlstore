<?php

namespace App\Livewire\Backoffice\Coupons;

use App\Models\CouponBatch;
use App\Services\Agent\CouponExportService;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportBatchModal extends Component
{
    public bool $open = false;

    public ?string $batchId = null;

    public string $format = 'txt';

    public string $scope = 'all';

    #[On('open-export-batch')]
    public function openFor(string $batchId): void
    {
        $this->batchId = $batchId;
        $this->format = 'txt';
        $this->scope = 'all';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->batchId = null;
    }

    /**
     * Stream the batch out to the admin's browser.
     *
     * The file itself is built by the shared CouponExportService — the same code the agent-facing
     * GET download uses — so the CSV/TXT/PDF a coupon batch produces is identical wherever it was
     * asked for. The backoffice is a desktop browser (admins never run inside the Mini App or the
     * Android WebView), so it keeps streaming straight out of the Livewire action.
     */
    public function download(CouponExportService $exports): ?StreamedResponse
    {
        abort_unless(auth('admin')->check(), 403);

        $batch = CouponBatch::query()
            ->with(['package', 'callType', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->find($this->batchId);

        if (! $batch) {
            return null;
        }

        return $exports->batch($batch, $this->scope, $this->format);
    }

    public function render()
    {
        return view('livewire.export-batch-modal');
    }
}
