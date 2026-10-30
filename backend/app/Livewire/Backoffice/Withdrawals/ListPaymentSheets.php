<?php

namespace App\Livewire\Backoffice\Withdrawals;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\WithdrawalPaymentSheet;
use App\Services\WithdrawalPaymentSheetService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.backoffice')]
#[Title('Payment Sheets — Backoffice')]
class ListPaymentSheets extends Component
{
    use AuthorizesAdminActions, WithPagination;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public string $reProcessor = 'general';

    public string $reFormat = 'csv';

    public function openDetail(string $id): void
    {

        $sheet = WithdrawalPaymentSheet::query()->findOrFail($id);
        $this->detailId = $sheet->id;
        $this->reProcessor = array_key_exists($sheet->processor, WithdrawalPaymentSheetService::PROCESSORS) ? $sheet->processor : 'general';
        $this->reFormat = 'csv';
        $this->showDetail = true;
    }

    public function download(): ?StreamedResponse
    {

        $this->authorizeWrite();

        $sheet = $this->detailId ? WithdrawalPaymentSheet::query()->find($this->detailId) : null;

        if (! $sheet
            || ! array_key_exists($this->reProcessor, WithdrawalPaymentSheetService::PROCESSORS)
            || ! array_key_exists($this->reFormat, WithdrawalPaymentSheetService::FORMATS)) {
            return null;
        }

        $result = app(WithdrawalPaymentSheetService::class)->reDownload($sheet, $this->reProcessor, $this->reFormat);

        return response()->streamDownload(
            fn () => print ($result['content']),
            $result['file_name'],
            ['Content-Type' => $result['mime_type']],
        );
    }

    public function render(): View
    {
        return view('livewire.backoffice.withdrawals.list-payment-sheets', [
            'sheets' => WithdrawalPaymentSheet::query()->latest()->paginate(15),
            'processors' => WithdrawalPaymentSheetService::PROCESSORS,
            'formats' => WithdrawalPaymentSheetService::FORMATS,
            'detailSheet' => $this->detailId ? WithdrawalPaymentSheet::query()->find($this->detailId) : null,
        ]);
    }
}
