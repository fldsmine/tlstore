<?php

namespace App\Livewire\Backoffice\Payments;

use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Models\Admin;
use App\Models\PaymentResolutionLog;
use App\Models\ProductOrder;
use App\Models\Transaction;
use App\Services\Payments\PaymentResolutionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Payment Requests — Backoffice')]
class ManagePaymentRequests extends Component
{
    use WithPagination;

    #[Url]
    public string $requestGroup = 'transactions';

    #[Url]
    public string $statusFilter = 'unresolved';

    #[Url]
    public string $providerFilter = '';

    #[Url]
    public string $search = '';

    #[Url]
    public int $perPage = 25;

    public bool $showDetail = false;

    public ?string $selectedRequestId = null;

    public string $selectedKind = 'transaction';

    public string $manualAmount = '';

    public string $manualCurrency = '';

    public string $manualGatewayReference = '';

    public string $verificationSource = 'gateway_dashboard';

    public string $evidenceReference = '';

    public string $manualNote = '';

    public bool $manualAttestation = false;

    public bool $showCloseForm = false;

    public string $closeNote = '';

    public bool $closeAttestation = false;

    public function mount(): void
    {
        if (! in_array($this->requestGroup, ['transactions', 'product_orders'], true)) {
            $this->requestGroup = 'transactions';
        }
        if (! in_array($this->statusFilter, ['unresolved', 'pending', 'failed', 'completed', 'paid', 'all'], true)) {
            $this->statusFilter = 'unresolved';
        }
        if ($this->requestGroup === 'transactions' && $this->statusFilter === 'paid') {
            $this->statusFilter = 'completed';
        } elseif ($this->requestGroup === 'product_orders' && $this->statusFilter === 'completed') {
            $this->statusFilter = 'paid';
        }
        if ($this->providerFilter !== '' && ! in_array($this->providerFilter, PaymentProvider::values(), true)) {
            $this->providerFilter = '';
        }
    }

    public function updatedRequestGroup(): void
    {
        if ($this->requestGroup === 'transactions' && $this->statusFilter === 'paid') {
            $this->statusFilter = 'completed';
        } elseif ($this->requestGroup === 'product_orders' && $this->statusFilter === 'completed') {
            $this->statusFilter = 'paid';
        }

        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedProviderFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function openRequest(string $kind, string $id): void
    {
        if (! in_array($kind, ['transaction', 'product_order'], true)) {
            abort(404);
        }

        $record = $this->recordQuery($kind)->findOrFail($id);
        $this->selectedKind = $kind;
        $this->selectedRequestId = (string) $record->getKey();
        $this->manualAmount = number_format($this->expectedAmount($record), 2, '.', '');
        $this->manualCurrency = $this->expectedCurrency($record);
        $this->manualGatewayReference = '';
        $this->verificationSource = $record->provider === 'manual' ? 'bank_statement' : 'gateway_dashboard';
        $this->evidenceReference = '';
        $this->manualNote = '';
        $this->manualAttestation = false;
        $this->showCloseForm = false;
        $this->closeNote = '';
        $this->closeAttestation = false;
        $this->showDetail = true;
        $this->resetValidation();
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedRequestId = null;
        $this->selectedKind = 'transaction';
        $this->showCloseForm = false;
        $this->resetValidation();
    }

    public function recheckSelected(PaymentResolutionService $resolver): void
    {
        $this->assertSelectedRequest();
        $admin = auth('admin')->user();

        abort_unless($admin instanceof Admin, 403);

        $status = $resolver->recheck($this->selectedKind, $this->selectedRequestId, $admin);
        $this->flashOutcome($status, 'Provider recheck');
        $this->resetPage();
    }

    public function confirmManually(PaymentResolutionService $resolver): void
    {
        $this->assertSelectedRequest();

        $this->validate([
            'manualAmount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'manualCurrency' => ['required', 'string', 'size:3'],
            'manualGatewayReference' => ['required', 'string', 'min:3', 'max:120'],
            'verificationSource' => ['required', 'string', 'in:'.implode(',', array_keys(PaymentResolutionService::VERIFICATION_SOURCES))],
            'evidenceReference' => ['required', 'string', 'min:3', 'max:120'],
            'manualNote' => ['required', 'string', 'min:10', 'max:1000'],
            'manualAttestation' => ['accepted'],
        ]);

        $admin = auth('admin')->user();
        abort_unless($admin instanceof Admin, 403);

        try {
            $status = $resolver->confirmManually($this->selectedKind, $this->selectedRequestId, $admin, [
                'amount' => $this->manualAmount,
                'currency' => $this->manualCurrency,
                'gateway_reference' => $this->manualGatewayReference,
                'verification_source' => $this->verificationSource,
                'evidence_reference' => $this->evidenceReference,
                'note' => $this->manualNote,
                'attested' => $this->manualAttestation,
            ]);
        } finally {
            $this->manualAttestation = false;
        }

        $this->flashOutcome($status, 'Manual verification');
        $this->resetPage();
    }

    public function closeAsUnpaid(PaymentResolutionService $resolver): void
    {
        $this->assertSelectedRequest();
        $this->validate([
            'closeNote' => ['required', 'string', 'min:10', 'max:1000'],
            'closeAttestation' => ['accepted'],
        ]);

        $admin = auth('admin')->user();
        abort_unless($admin instanceof Admin, 403);

        try {
            $resolver->closeAsUnpaid(
                $this->selectedKind,
                $this->selectedRequestId,
                $admin,
                $this->closeNote,
                $this->closeAttestation,
            );
        } finally {
            $this->closeAttestation = false;
        }

        $this->showCloseForm = false;
        $this->dispatch('toast', message: 'Payment request closed as unpaid. The decision is recorded in its audit history.', type: 'success');
        $this->resetPage();
    }

    public function toggleCloseForm(): void
    {
        $this->assertSelectedRequest();
        abort_unless(auth('admin')->user()?->isSuper() ?? false, 403);
        $this->showCloseForm = ! $this->showCloseForm;
        $this->closeAttestation = false;
        $this->closeNote = '';
        $this->resetValidation('closeNote');
    }

    protected function pageSize(): int
    {
        return in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 25;
    }

    protected function baseQuery(): Builder
    {
        $kind = in_array($this->requestGroup, ['transactions', 'product_orders'], true)
            ? $this->requestGroup
            : 'transactions';

        $query = $kind === 'product_orders'
            ? ProductOrder::query()->with(['paymentReference'])
            : Transaction::query()->with(['user', 'paymentReference']);

        if ($kind === 'transactions') {
            $query->whereIn('type', TransactionType::gatewayChargeValues())
                ->whereIn('provider', PaymentProvider::values());
        } else {
            $query->whereIn('provider', PaymentProvider::values());
        }

        if ($this->statusFilter === 'unresolved') {
            $query->where(function (Builder $scope) use ($kind): void {
                $scope->where('status', 'pending')->orWhere(function (Builder $failed) use ($kind): void {
                    $failed->where('status', 'failed');
                    $this->scopeInitiatedCheckout($failed, $kind);
                });
            });
        } elseif ($this->statusFilter === 'failed') {
            $query->where('status', 'failed');
            $this->scopeInitiatedCheckout($query, $kind);
        } elseif (in_array($this->statusFilter, ['pending', 'completed', 'paid'], true)) {
            $query->where('status', $this->statusFilter === 'completed' && $kind === 'product_orders' ? 'paid' : $this->statusFilter);
        } elseif ($this->statusFilter !== 'all') {
            $query->where(function (Builder $scope) use ($kind): void {
                $scope->where('status', 'pending')->orWhere(function (Builder $failed) use ($kind): void {
                    $failed->where('status', 'failed');
                    $this->scopeInitiatedCheckout($failed, $kind);
                });
            });
        }

        return $query
            ->when($this->providerFilter !== '' && in_array($this->providerFilter, PaymentProvider::values(), true), fn (Builder $builder) => $builder->where('provider', $this->providerFilter))
            ->when(trim($this->search) !== '', function (Builder $builder) use ($kind): void {
                $term = '%'.trim($this->search).'%';

                if ($kind === 'product_orders') {
                    $builder->where(fn (Builder $search) => $search
                        ->where('reference', 'like', $term)
                        ->orWhere('gateway_reference', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('full_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('product_name', 'like', $term));
                } else {
                    $builder->where(fn (Builder $search) => $search
                        ->where('reference', 'like', $term)
                        ->orWhere('gateway_reference', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('provider', 'like', $term)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)));
                }
            });
    }

    public function render(): View
    {
        $admin = auth('admin')->user();
        $selected = $this->selectedRecord();
        $pendingTransactions = Transaction::query()
            ->whereIn('type', TransactionType::gatewayChargeValues())
            ->whereIn('provider', PaymentProvider::values())
            ->where('status', 'pending')
            ->count();
        $pendingOrders = ProductOrder::query()->whereIn('provider', PaymentProvider::values())->where('status', ProductOrder::STATUS_PENDING)->count();
        $failedTransactions = $this->failedForReviewQuery(Transaction::query(), 'transactions')->count();
        $failedOrders = $this->failedForReviewQuery(ProductOrder::query(), 'product_orders')->count();

        return view('livewire.backoffice.payments.manage-payment-requests', [
            'requests' => $this->baseQuery()->latest()->paginate($this->pageSize()),
            'selected' => $selected,
            'providers' => collect(PaymentProvider::cases())->mapWithKeys(fn (PaymentProvider $provider): array => [$provider->value => $provider->label()])->all(),
            'verificationSources' => PaymentResolutionService::VERIFICATION_SOURCES + ['provider_api' => 'Provider API'],
            'manualVerificationSources' => PaymentResolutionService::VERIFICATION_SOURCES,
            'pendingCount' => $pendingTransactions + $pendingOrders,
            'failedReviewCount' => $failedTransactions + $failedOrders,
            'manualResolvedToday' => PaymentResolutionLog::query()->where('action', 'manual_confirm')->where('details->outcome', 'completed')->where('created_at', '>=', now()->startOfDay())->count(),
            'resolutionActionsToday' => PaymentResolutionLog::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'canRecheck' => $admin?->canWrite() ?? false,
            'canManuallyResolve' => $admin?->isSuper() ?? false,
            'selectedHasLedger' => $selected?->paymentReference !== null,
            'selectedExpectedAmount' => $selected ? $this->expectedAmount($selected) : 0,
            'selectedExpectedCurrency' => $selected ? $this->expectedCurrency($selected) : '',
        ]);
    }

    private function selectedRecord(): Transaction|ProductOrder|null
    {
        if ($this->selectedRequestId === null || ! in_array($this->selectedKind, ['transaction', 'product_order'], true)) {
            return null;
        }

        return $this->recordQuery($this->selectedKind)->find($this->selectedRequestId);
    }

    private function recordQuery(string $kind): Builder
    {
        if ($kind === 'product_order') {
            return ProductOrder::query()
                ->whereIn('provider', PaymentProvider::values())
                ->with(['paymentReference', 'paymentResolutionLogs.admin']);
        }

        return Transaction::query()
            ->whereIn('type', TransactionType::gatewayChargeValues())
            ->whereIn('provider', PaymentProvider::values())
            ->with(['user', 'paymentReference', 'paymentResolutionLogs.admin']);
    }

    private function assertSelectedRequest(): void
    {
        abort_unless($this->selectedRequestId !== null, 404);
        abort_unless(in_array($this->selectedKind, ['transaction', 'product_order'], true), 404);
    }

    private function scopeInitiatedCheckout(Builder $query, string $kind): void
    {
        if ($kind === 'product_orders') {
                $query->where(fn (Builder $initiated) => $initiated
                    ->whereNotNull('checkout_url')
                    ->orWhereNotNull('gateway_reference')
                    ->orWhereNotNull('meta->manual_review')
                    ->orWhereNotNull('meta->reconcile_error'));

            return;
        }

        $query->where(fn (Builder $initiated) => $initiated
            ->whereNotNull('gateway_reference')
            ->orWhereNotNull('meta->checkout_url')
            ->orWhereNotNull('meta->manual_review')
            ->orWhereNotNull('meta->reconcile_error'));
    }

    private function failedForReviewQuery(Builder $query, string $kind): Builder
    {
        if ($kind === 'transactions') {
            $query->whereIn('type', TransactionType::gatewayChargeValues())->whereIn('provider', PaymentProvider::values());
        } else {
            $query->whereIn('provider', PaymentProvider::values());
        }

        $query->where('status', 'failed');
        $this->scopeInitiatedCheckout($query, $kind);

        return $query;
    }

    private function expectedAmount(Transaction|ProductOrder $record): float
    {
        return $record instanceof ProductOrder
            ? round((float) $record->amount_ngn, 2)
            : round((float) ($record->meta['amount_ngn'] ?? 0), 2);
    }

    private function expectedCurrency(Transaction|ProductOrder $record): string
    {
        return strtoupper((string) ($record instanceof ProductOrder
            ? $record->currency
            : ($record->meta['currency'] ?? '')));
    }

    private function flashOutcome(string $status, string $action): void
    {
        [$message, $tone] = match ($status) {
            'completed' => [$action.' completed successfully. The normal entitlement/fulfilment path was applied.', 'success'],
            'pending' => [$action.' found no confirmed payment yet. The request remains unresolved.', 'warning'],
            'unavailable' => [$action.' could not reach the payment provider. The request remains unchanged.', 'warning'],
            'failed' => [$action.' did not complete. Review the provider response and settlement audit before taking another action.', 'danger'],
            default => [$action.' returned status: '.$status.'.', 'warning'],
        };

        $this->dispatch('toast', message: $message, type: $tone);
    }
}
