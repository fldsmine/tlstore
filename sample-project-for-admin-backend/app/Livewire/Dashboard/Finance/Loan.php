<?php

namespace App\Livewire\Dashboard\Finance;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Services\CurrencyService;
use App\Services\LoanService;
use App\Services\Payments\LoanApplicationFeeService;
use App\Services\Payments\LoanGuarantorFormService;
use App\Support\DisplayCurrency;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Loan')]
class Loan extends Component
{
    public string $loanType = 'student';

    public ?float $amount = null;

    public string $dueDate = '';

    public string $accountName = '';

    public string $accountNumber = '';

    public string $bankName = '';

    public bool $agreementAccepted = false;

    public bool $showSummary = false;

    public string $feeCouponCode = '';

    public ?string $submittedId = null;

    public ?string $detailId = null;

    public ?string $guarantorLoanId = null;

    /** @var array<string, mixed> */
    public array $guarantorForm = [
        'full_name' => '',
        'relationship' => '',
        'phone' => '',
        'email' => '',
        'residential_address' => '',
        'signature' => '',
        'consent' => false,
    ];

    public function mount(): void
    {
        $loanService = app(LoanService::class);
        $this->loanType = array_key_first($loanService->availableTypes()) ?? LoanType::STUDENT->value;
        $this->dueDate = now()->addDays($loanService->repaymentDays())->toDateString();

        $submittedId = session()->pull('loan_application_id');
        if (is_string($submittedId) && $submittedId !== '') {
            $this->submittedId = auth()->user()->loans()->whereKey($submittedId)->value('id');
        }
    }

    public function proceedToSummary(): void
    {
        app(LoanService::class)->prepareApplication(
            auth()->user(),
            $this->loanType,
            $this->usdAmount(),
            $this->dueDate,
            $this->agreementAccepted,
            accountName: $this->accountName,
            accountNumber: $this->accountNumber,
            bankName: $this->bankName,
        );

        $this->resetValidation();
        $this->showSummary = true;
    }

    public function backToApplication(): void
    {
        $this->showSummary = false;
        $this->resetValidation();
    }

    public function submitWithCoupon(): void
    {
        $this->validate([
            'feeCouponCode' => ['required', 'string', 'max:32'],
        ], [
            'feeCouponCode.required' => 'Enter a loan application fee coupon.',
        ]);

        $loan = app(LoanService::class)->applyWithCoupon(
            auth()->user(),
            $this->loanType,
            $this->usdAmount(),
            $this->dueDate,
            $this->agreementAccepted,
            $this->feeCouponCode,
            accountName: $this->accountName,
            accountNumber: $this->accountNumber,
            bankName: $this->bankName,
        );

        $this->submittedId = $loan->id;
        $this->showSummary = false;
        $this->feeCouponCode = '';
        $this->dispatch('toast', message: 'Fee coupon accepted — your loan application is now under review.', type: 'success');
    }

    public function payApplicationFee(): mixed
    {
        $result = app(LoanApplicationFeeService::class)->start(
            auth()->user(),
            $this->loanType,
            $this->usdAmount(),
            $this->dueDate,
            $this->agreementAccepted,
            $this->accountName,
            $this->accountNumber,
            $this->bankName,
        );

        if (! $result['ok']) {
            $this->dispatch('toast', message: $result['error'] ?? 'Could not start the application fee payment.', type: 'error');

            return null;
        }

        return redirect()->away((string) $result['checkout_url']);
    }

    public function cancelPendingPayment(string $reference): mixed
    {
        $status = app(LoanApplicationFeeService::class)->cancelPendingPayment(auth()->user(), $reference);
        [$type, $message] = match ($status) {
            'cancelled' => ['success', 'Pending payment request cancelled. You can submit a new loan application.'],
            'completed' => ['info', 'Payment was verified, so the loan application was kept and cannot be cancelled.'],
            'unavailable' => ['warning', 'Payment status could not be verified. Your pending request was left unchanged; please try again shortly.'],
            'failed' => ['warning', 'The payment is no longer pending. You can review the loan page and submit again if needed.'],
            default => ['warning', 'This payment request is no longer pending. The loan page has been refreshed.'],
        };

        session()->flash('toast', ['type' => $type, 'message' => $message]);

        return $this->redirect(route('loan'), navigate: true);
    }

    public function newApplication(): void
    {
        /*if (app(LoanService::class)->hasUnresolvedApplication(auth()->user())) {
            $this->dispatch('toast', message: 'Wait for the pending application to be approved or rejected before applying again.', type: 'warning');

            return;
        }*/

        $service = app(LoanService::class);
        $this->reset(['amount', 'accountName', 'accountNumber', 'bankName', 'agreementAccepted', 'showSummary', 'feeCouponCode', 'submittedId', 'detailId']);
        $this->loanType = array_key_first($service->availableTypes()) ?? LoanType::STUDENT->value;
        $this->dueDate = now()->addDays($service->repaymentDays())->toDateString();
        $this->resetValidation();
    }

    public function showDetails(string $id): void
    {
        $this->detailId = $id;
    }

    public function openGuarantorForm(string $loanId): void
    {
        $loan = auth()->user()->loans()->whereKey($loanId)->firstOrFail();
        if ($loan->status !== LoanStatus::APPROVED) {
            $this->dispatch('toast', message: 'The guarantor form is available only after staff approve the loan.', type: 'warning');

            return;
        }

        $this->detailId = null;
        $this->guarantorLoanId = (string) $loan->id;
        $storedForm = (array) $loan->guarantor_form_data;
        unset($storedForm['occupation'], $storedForm['employer']);
        $this->guarantorForm = array_merge([
            'full_name' => '',
            'relationship' => '',
            'phone' => '',
            'email' => '',
            'residential_address' => '',
            'signature' => '',
            'consent' => false,
        ], $storedForm);
        $this->resetValidation();
    }

    public function startGuarantorFormPayment(): mixed
    {
        if ($this->guarantorLoanId === null) {
            return null;
        }

        $result = app(LoanGuarantorFormService::class)->start(auth()->user(), $this->guarantorLoanId);
        if (! $result['ok']) {
            $this->dispatch('toast', message: $result['error'] ?? 'Could not start the guarantor-form payment.', type: 'error');

            return null;
        }

        return redirect()->away((string) $result['checkout_url']);
    }

    public function refreshGuarantorFormPayment(string $reference): void
    {
        if ($this->guarantorLoanId === null) {
            return;
        }

        $status = app(LoanGuarantorFormService::class)->refreshPendingPayment(
            auth()->user(),
            $this->guarantorLoanId,
            $reference,
        );
        [$type, $message] = match ($status) {
            'completed' => ['success', 'Payment verified — the guarantor form is now unlocked.'],
            'pending' => ['info', 'Payment is still pending. Continue checkout or check again shortly.'],
            'unavailable' => ['warning', 'The provider could not confirm payment right now. Try again shortly.'],
            default => ['info', 'This payment request is no longer pending.'],
        };

        $this->dispatch('toast', message: $message, type: $type);
    }

    public function cancelGuarantorFormPayment(string $reference): mixed
    {
        if ($this->guarantorLoanId === null) {
            return null;
        }

        $status = app(LoanGuarantorFormService::class)->cancelPendingPayment(
            auth()->user(),
            $this->guarantorLoanId,
            $reference,
        );
        [$type, $message] = match ($status) {
            'cancelled' => ['success', 'The provider confirmed the payment failed or expired. The request was cancelled; you can start a new payment.'],
            'completed' => ['info', 'Payment was verified, so the request was kept and the form is unlocked.'],
            'pending' => ['info', 'Payment is still pending with the provider. The request was left unchanged; continue checkout or check again shortly.'],
            'unavailable' => ['warning', 'Payment status could not be verified. The request was left unchanged; try again shortly.'],
            'needs_review' => ['warning', 'This payment needs staff review. It cannot be deleted or replaced yet.'],
            default => ['warning', 'This payment request is no longer pending. Your loan page has been refreshed.'],
        };

        session()->flash('toast', ['type' => $type, 'message' => $message]);

        return $this->redirect(route('loan'), navigate: true);
    }

    public function submitGuarantorForm(): void
    {
        if ($this->guarantorLoanId === null) {
            return;
        }

        $this->guarantorForm = array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $this->guarantorForm);
        unset($this->guarantorForm['occupation'], $this->guarantorForm['employer']);
        foreach (['email'] as $optionalField) {
            if (($this->guarantorForm[$optionalField] ?? null) === '') {
                $this->guarantorForm[$optionalField] = null;
            }
        }

        $validated = $this->validate([
            'guarantorForm.full_name' => ['required', 'string', 'max:120'],
            'guarantorForm.relationship' => ['required', 'string', 'max:80'],
            'guarantorForm.phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+() .-]{7,32}$/'],
            'guarantorForm.email' => ['nullable', 'email', 'max:255'],
            'guarantorForm.residential_address' => ['required', 'string', 'max:255'],
            'guarantorForm.signature' => ['required', 'string', 'max:120', 'same:guarantorForm.full_name'],
            'guarantorForm.consent' => ['accepted'],
        ], [
            'guarantorForm.signature.same' => 'Type the guarantor’s full name exactly as their signature.',
            'guarantorForm.consent.accepted' => 'Confirm that the guarantor has consented to be listed.',
        ]);

        try {
            app(LoanGuarantorFormService::class)->submit(
                auth()->user(),
                $this->guarantorLoanId,
                $validated['guarantorForm'],
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $target = array_key_exists($field, $this->guarantorForm) ? 'guarantorForm.'.$field : 'guarantorForm';
                $this->addError($target, $messages[0] ?? 'Please check the form and try again.');
            }

            return;
        }

        $this->guarantorLoanId = null;
        $this->dispatch('toast', message: 'Guarantor form submitted. You can now view or download the PDF.', type: 'success');
    }

    protected function entryIsLocal(): bool
    {
        return DisplayCurrency::isLocal(auth()->user());
    }

    protected function usdAmount(): float
    {
        $typed = max(0.0, (float) ($this->amount ?: 0));

        return $this->entryIsLocal()
            ? round(app(CurrencyService::class)->toUsd($typed, auth()->user()->country), 2)
            : round($typed, 2);
    }

    protected function inEntryCurrency(float $usd): float
    {
        return $this->entryIsLocal()
            ? floor(DisplayCurrency::convert($usd, auth()->user()) * 100) / 100
            : round($usd, 2);
    }

    public function render(): View
    {
        $user = auth()->user();
        $loanService = app(LoanService::class);
        $maxUsd = $loanService->maxAmount();
        $minUsd = $loanService->minAmount();
        $days = $loanService->repaymentDays();
        $types = $loanService->availableTypes();
        $presets = collect((array) config('settings.loan.presets', [20, 50, 100, 250, 500]))
            ->filter(fn ($preset): bool => is_numeric($preset) && (float) $preset >= $minUsd && (float) $preset <= $maxUsd)
            ->map(fn ($preset): float => $this->entryIsLocal() ? round(DisplayCurrency::convert((float) $preset, $user), 2) : (float) $preset)
            ->values();

        $summaryAmount = $this->usdAmount();
        $feeAmountBase = $this->amount !== null ? $summaryAmount : null;
        $customerFee = $loanService->feeForUser($user, $feeAmountBase);
        $applicationFeeDisplay = $customerFee['amount'] === null
            ? number_format((float) $customerFee['percentage'], 2).'% of loan amount'
            : (($customerFee['type'] === 'percentage' ? number_format((float) $customerFee['percentage'], 2).'% · ' : '')
                .($customerFee['currency'] === 'NGN' ? '₦' : '$').number_format((float) $customerFee['amount'], 2).' '.$customerFee['currency']);
        $payment = app(LoanApplicationFeeService::class)->availability($feeAmountBase);
        $paymentFeeDisplay = ($payment['amount'] ?? null) === null
            ? number_format((float) ($payment['fee_percentage'] ?? 0), 2).'% of loan amount'
            : (($payment['currency'] ?? '') === 'NGN'
                ? '₦'.number_format((float) $payment['amount'], 2).' NGN'
                : '$'.number_format((float) $payment['amount'], 2).' USD');

        if ($loanService->applicationFeeMode() === 'percentage') {
            $applicationFeeDisplay = $feeAmountBase === null
                ? number_format((float) $customerFee['percentage'], 2).'% of loan amount'
                : ((($payment['available'] ?? false) && ($payment['amount'] ?? null) !== null)
                    ? $paymentFeeDisplay
                    : number_format((float) $customerFee['percentage'], 2).'% of loan amount — no active payment channel');
        }

        $summaryServiceFee = round($summaryAmount * $loanService->serviceFeePercent() / 100, 2);
        $pendingPayment = $loanService->pendingFeePayment($user);
        $loans = $user->loans()->with('applicationFeeCoupon')->latest()->take(15)->get();
        $guarantorFees = app(LoanGuarantorFormService::class);
        $pendingGuarantorPayments = $guarantorFees->pendingPaymentsForLoans($user, $loans);

        return view('livewire.dashboard.finance.loan', [
            'enabled' => $loanService->isEnabled(),
            'isGuarantor' => $loanService->isGuarantorEnabled(),
            'maxAmount' => $maxUsd,
            'minAmount' => $minUsd,
            'maxEntry' => $this->inEntryCurrency($maxUsd),
            'minEntry' => $this->entryIsLocal()
                ? ceil(DisplayCurrency::convert($minUsd, $user) * 100) / 100
                : round($minUsd, 2),
            'entrySymbol' => DisplayCurrency::symbol($user),
            'entryCode' => DisplayCurrency::code($user),
            'repaymentDays' => $days,
            'serviceFeePercent' => $loanService->serviceFeePercent(),
            'applicationFeeDisplay' => $applicationFeeDisplay,
            'applicationFeeMode' => $loanService->applicationFeeMode(),
            'canUseFeeCoupon' => $loanService->applicationFeeMode() === 'fixed',
            'paymentFeeDisplay' => $paymentFeeDisplay,
            'summaryAmount' => $summaryAmount,
            'summaryServiceFee' => $summaryServiceFee,
            'summaryTotal' => round($summaryAmount + $summaryServiceFee, 2),
            'loanTypeLabel' => $types[$this->loanType] ?? 'Loan',
            'payment' => $payment,
            'hasUnresolved' => $loanService->hasUnresolvedApplication($user),
            'pendingPayment' => $pendingPayment,
            'pendingCheckout' => app(LoanApplicationFeeService::class)->openCheckout($user),
            'minDueDate' => now()->addDay()->toDateString(),
            'maxDueDate' => now()->addDays($days)->toDateString(),
            'presets' => $presets,
            'loanTypes' => $types,
            'loans' => $loans,
            'submittedLoan' => $this->submittedId ? $user->loans()->with('applicationFeeCoupon')->find($this->submittedId) : null,
            'detailLoan' => $this->detailId ? $user->loans()->with('applicationFeeCoupon')->find($this->detailId) : null,
            'guarantorFee' => $guarantorFees->availability(),
            'pendingGuarantorPayments' => $pendingGuarantorPayments,
            'guarantorFormLoan' => $this->guarantorLoanId ? $user->loans()->find($this->guarantorLoanId) : null,
        ]);
    }
}
