<?php

namespace App\Livewire\Dashboard\Agent;

use App\Models\CouponBatch;
use App\Services\Agent\CouponExportService;
use App\Support\DownloadLinks;
use Livewire\Attributes\On;
use Livewire\Component;

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

    protected function batch(): ?CouponBatch
    {
        return CouponBatch::query()
            ->where('agent_id', auth()->id())
            ->with(['package', 'additionalMinutePlan', 'cloudStoragePlan'])
            ->find($this->batchId);
    }

    public function render(CouponExportService $exports)
    {
        $batch = $this->batch();
        $scope = CouponExportService::normalizeScope($this->scope);
        $format = CouponExportService::normalizeFormat($this->format);

        return view('livewire.dashboard.agent.export-batch-modal', [
            // A signed GET link: the file is streamed by AgentExportController, which is what lets
            // the Telegram Mini App and the Android app hand the URL to a native downloader instead
            // of trying to render a Livewire response as a file.
            'exportUrl' => $batch ? DownloadLinks::signed('agent.batches.export', [
                'batch' => $batch->id,
                'scope' => $scope,
                'format' => $format,
            ]) : null,
            'exportFilename' => $batch ? $exports->batchFilename($batch, $scope, $format) : null,
        ]);
    }
}
