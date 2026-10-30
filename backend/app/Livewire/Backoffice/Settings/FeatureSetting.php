<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Feature Settings — Backoffice')]
class FeatureSetting extends Component
{
    use AuthorizesAdminActions;

    public bool $enableMinutes = false;

    public string $minutesDisabledMessage = '';

    public bool $enablePaylater = true;

    public string $paylaterAudience = 'subset';

    public string $paylaterLimit = '';

    public string $paylaterRepaymentDays = '';

    public bool $enableLoan = true;

    public string $loanMaxAmount = '';

    public string $loanMinAmount = '';

    public string $loanPresets = '';

    public string $loanRepaymentDays = '';

    public string $loanServiceFeePercent = '';

    public string $loanApplicationFeeMode = 'fixed';

    public string $loanApplicationFeePercentage = '';

    public string $loanApplicationFeeUsd = '';

    public string $loanApplicationFeeNgn = '';

    public string $loanGuarantorFormFeeNgn = '';

    public bool $enableGuarantorForm = false;

    public string $loanApplicationFeeAgentUsd = '';

    public string $loanApplicationFeeAgentNgn = '';

    /** @var array<string, bool> */
    public array $loanTypes = [];

    public string $loanRejectionReason = '';

    public bool $enableAds = true;

    public string $adsWebsiteSlots = '';

    public bool $enableRewards = true;

    public string $rewardTargetPercentage = '';

    public string $rewardMinTarget = '';

    public string $rewardMaxTarget = '';

    public string $minutesDisabledTitle = '';

    public string $paylaterDisabledTitle = '';

    public string $paylaterDisabledMessage = '';

    public string $loanDisabledTitle = '';

    public string $loanDisabledMessage = '';

    public string $adsDisabledTitle = '';

    public string $adsDisabledMessage = '';

    public string $rewardsDisabledTitle = '';

    public string $rewardsDisabledMessage = '';

    public string $websiteDurationDays = '';

    public string $websiteRenewalMode = 'online';

    public string $websiteRenewalPrice = '';

    public string $websiteCodePrice = '';

    public string $websiteCodeAgentPrice = '';

    public string $websiteCodeForeignPrice = '';

    public string $websiteCodeForeignAgentPrice = '';

    public string $websiteExpiredTitle = '';

    public string $websiteExpiredMessage = '';

    public bool $withdrawalRequireAuthorizationCode = true;

    public function mount(): void
    {
        $this->enableMinutes = (bool) config('settings.minutes.enabled');
        $this->minutesDisabledMessage = (string) config('settings.minutes.disabled_message');
        $this->enablePaylater = (bool) config('settings.paylater.enabled');
        $this->paylaterAudience = (string) config('settings.paylater.audience');
        $this->paylaterLimit = (string) config('settings.paylater.limit');
        $this->paylaterRepaymentDays = (string) config('settings.paylater.repayment_days');
        $this->enableLoan = (bool) config('settings.loan.enabled');
        $this->loanMaxAmount = (string) config('settings.loan.max_amount');
        $this->loanMinAmount = (string) config('settings.loan.min_amount', 50);
        $this->loanPresets = collect((array) config('settings.loan.presets', [20, 50, 100, 250, 500]))
            ->map(fn ($amount): string => rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.'))
            ->implode(', ');
        $this->loanRepaymentDays = (string) config('settings.loan.repayment_days');
        $this->loanServiceFeePercent = (string) config('settings.loan.service_fee_percent');
        $this->loanApplicationFeeMode = (string) config('settings.loan.application_fee_mode', 'fixed');
        $this->loanApplicationFeePercentage = (string) config('settings.loan.application_fee_percentage', 1);
        $this->loanApplicationFeeUsd = (string) config('settings.loan.application_fee_usd');
        $this->loanApplicationFeeNgn = (string) config('settings.loan.application_fee_ngn');
        $this->loanGuarantorFormFeeNgn = (string) config('settings.loan.guarantor_form_fee_ngn', 1500);
        $this->enableGuarantorForm = (bool) config('settings.loan.guarantor_form_enabled');
        $this->loanApplicationFeeAgentUsd = (string) config('settings.loan.application_fee_agent_usd');
        $this->loanApplicationFeeAgentNgn = (string) config('settings.loan.application_fee_agent_ngn');
        $this->loanTypes = (array) config('settings.loan.types', []);
        $this->loanRejectionReason = (string) config('settings.loan.rejection_reason');
        $this->enableAds = (bool) config('settings.ads.enabled');
        $this->adsWebsiteSlots = (string) config('settings.ads.website_slots');
        $this->enableRewards = (bool) config('settings.rewards.enabled');
        $this->rewardTargetPercentage = (string) config('settings.rewards.target_percentage');
        $this->rewardMinTarget = (string) config('settings.rewards.min_target');
        $this->rewardMaxTarget = (string) config('settings.rewards.max_target');

        $this->minutesDisabledTitle = (string) config('settings.minutes.disabled_title');
        $this->paylaterDisabledTitle = (string) config('settings.paylater.disabled_title');
        $this->paylaterDisabledMessage = (string) config('settings.paylater.disabled_message');
        $this->loanDisabledTitle = (string) config('settings.loan.disabled_title');
        $this->loanDisabledMessage = (string) config('settings.loan.disabled_message');
        $this->adsDisabledTitle = (string) config('settings.ads.disabled_title');
        $this->adsDisabledMessage = (string) config('settings.ads.disabled_message');
        $this->rewardsDisabledTitle = (string) config('settings.rewards.disabled_title');
        $this->rewardsDisabledMessage = (string) config('settings.rewards.disabled_message');

        $this->websiteDurationDays = (string) config('settings.website.duration_days');
        $this->websiteRenewalMode = (string) config('settings.website.renewal_mode');
        $this->websiteRenewalPrice = (string) config('settings.website.renewal_price');
        $this->websiteCodePrice = (string) config('settings.website.code.price');
        $this->websiteCodeAgentPrice = (string) config('settings.website.code.agent_price');
        $this->websiteCodeForeignPrice = (string) config('settings.website.code.foreign_price');
        $this->websiteCodeForeignAgentPrice = (string) config('settings.website.code.foreign_agent_price');
        $this->websiteExpiredTitle = (string) config('settings.website.expired_title');
        $this->websiteExpiredMessage = (string) config('settings.website.expired_message');

        $this->withdrawalRequireAuthorizationCode = (bool) config('settings.withdrawal.require_authorization_code', true);
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'minutesDisabledMessage' => ['required', 'string', 'max:500'],
            'minutesDisabledTitle' => ['required', 'string', 'max:120'],
            'paylaterDisabledTitle' => ['required', 'string', 'max:120'],
            'paylaterDisabledMessage' => ['required', 'string', 'max:500'],
            'loanDisabledTitle' => ['required', 'string', 'max:120'],
            'loanDisabledMessage' => ['required', 'string', 'max:500'],
            'adsDisabledTitle' => ['required', 'string', 'max:120'],
            'adsDisabledMessage' => ['required', 'string', 'max:500'],
            'rewardsDisabledTitle' => ['required', 'string', 'max:120'],
            'rewardsDisabledMessage' => ['required', 'string', 'max:500'],
            'paylaterAudience' => ['required', 'in:everyone,subset'],
            'paylaterLimit' => ['required', 'integer', 'min:0'],
            'paylaterRepaymentDays' => ['required', 'integer', 'min:1'],
            'loanMaxAmount' => ['required', 'numeric', 'min:0'],
            'loanMinAmount' => ['required', 'numeric', 'min:1', 'lte:loanMaxAmount'],
            'loanPresets' => ['nullable', 'string', 'max:255'],
            'loanRepaymentDays' => ['required', 'integer', 'min:1'],
            'loanServiceFeePercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'loanApplicationFeeMode' => ['required', 'in:fixed,percentage'],
            'loanApplicationFeePercentage' => ['required_if:loanApplicationFeeMode,percentage', 'nullable', 'numeric', 'min:0.0001', 'max:100'],
            'loanApplicationFeeUsd' => ['required_if:loanApplicationFeeMode,fixed', 'nullable', 'numeric', 'gt:0', 'max:100000'],
            'loanApplicationFeeNgn' => ['required_if:loanApplicationFeeMode,fixed', 'nullable', 'numeric', 'gt:0', 'max:100000000'],
            'loanGuarantorFormFeeNgn' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'loanApplicationFeeAgentUsd' => ['required_if:loanApplicationFeeMode,fixed', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'loanApplicationFeeAgentNgn' => ['required_if:loanApplicationFeeMode,fixed', 'nullable', 'numeric', 'min:0', 'max:100000000'],
            'loanTypes' => ['required', 'array', 'min:1'],
            'loanTypes.*' => ['boolean'],
            'loanRejectionReason' => ['required', 'string', 'max:500'],
            'adsWebsiteSlots' => ['required', 'integer', 'min:1', 'max:10'],
            'rewardTargetPercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'rewardMinTarget' => ['required', 'numeric', 'min:0'],
            'rewardMaxTarget' => ['required', 'numeric', 'min:0'],
            'websiteDurationDays' => ['required', 'integer', 'min:1'],
            'websiteRenewalPrice' => ['required', 'numeric', 'min:0'],
            // Code-renewal fields hidden for now (online payment only):
            // 'websiteRenewalMode' => ['required', 'in:online,code'],
            // 'websiteCodePrice' => ['required', 'numeric', 'min:0'],
            // 'websiteCodeAgentPrice' => ['required', 'numeric', 'min:0'],
            // 'websiteCodeForeignPrice' => ['required', 'numeric', 'min:0'],
            // 'websiteCodeForeignAgentPrice' => ['required', 'numeric', 'min:0'],
            'websiteExpiredTitle' => ['required', 'string', 'max:120'],
            'websiteExpiredMessage' => ['required', 'string', 'max:500'],
        ]);

        $loanPresetValues = [];
        foreach (explode(',', $this->loanPresets) as $preset) {
            $preset = trim($preset);
            if ($preset === '') {
                continue;
            }

            if (! is_numeric($preset) || (float) $preset <= 0 || (float) $preset > 1000000) {
                $this->addError('loanPresets', 'Enter positive comma-separated USD amounts no greater than 1,000,000.');

                return;
            }

            $amount = round((float) $preset, 2);
            if ($amount <= 0) {
                $this->addError('loanPresets', 'Each preset must be at least $0.01.');

                return;
            }

            $loanPresetValues[] = $amount;
        }
        $loanPresetValues = array_values(array_unique($loanPresetValues, SORT_REGULAR));

        $enabledLoanTypes = collect(\App\Enums\LoanType::cases())
            ->filter(fn (\App\Enums\LoanType $type): bool => (bool) ($this->loanTypes[$type->value] ?? false));

        if ($enabledLoanTypes->isEmpty()) {
            $this->addError('loanTypes', 'Enable at least one loan type.');

            return;
        }

        update_setting([
            'enable_minutes' => $this->enableMinutes,
            'minutes_disabled_title' => $this->minutesDisabledTitle,
            'minutes_disabled_message' => $this->minutesDisabledMessage,
            'paylater_disabled_title' => $this->paylaterDisabledTitle,
            'paylater_disabled_message' => $this->paylaterDisabledMessage,
            'loan_disabled_title' => $this->loanDisabledTitle,
            'loan_disabled_message' => $this->loanDisabledMessage,
            'ads_disabled_title' => $this->adsDisabledTitle,
            'ads_disabled_message' => $this->adsDisabledMessage,
            'rewards_disabled_title' => $this->rewardsDisabledTitle,
            'rewards_disabled_message' => $this->rewardsDisabledMessage,
            'enable_paylater' => $this->enablePaylater,
            'paylater_audience' => $this->paylaterAudience,
            'paylater_limit' => (int) $this->paylaterLimit,
            'paylater_repayment_days' => (int) $this->paylaterRepaymentDays,
            'enable_loan' => $this->enableLoan,
            'loan_max_amount' => $this->loanMaxAmount,
            'loan_min_amount' => $this->loanMinAmount,
            'loan_presets' => $loanPresetValues,
            'loan_repayment_days' => (int) $this->loanRepaymentDays,
            'loan_service_fee_percent' => $this->loanServiceFeePercent,
            'loan_application_fee_mode' => $this->loanApplicationFeeMode,
            'loan_application_fee_percentage' => $this->loanApplicationFeePercentage !== ''
                ? $this->loanApplicationFeePercentage
                : config('settings.loan.application_fee_percentage', 1),
            'loan_application_fee_usd' => $this->loanApplicationFeeUsd,
            'loan_application_fee_ngn' => $this->loanApplicationFeeNgn,
            'loan_guarantor_form_fee_ngn' => $this->loanGuarantorFormFeeNgn,
            'guarantor_form_enabled' => $this->enableGuarantorForm,
            'loan_application_fee_agent_usd' => $this->loanApplicationFeeAgentUsd,
            'loan_application_fee_agent_ngn' => $this->loanApplicationFeeAgentNgn,
            'loan_types' => collect(\App\Enums\LoanType::cases())
                ->mapWithKeys(fn (\App\Enums\LoanType $type): array => [$type->value => (bool) ($this->loanTypes[$type->value] ?? false)])
                ->all(),
            'loan_rejection_reason' => $this->loanRejectionReason,
            'enable_ads' => $this->enableAds,
            'ads_website_slots' => (int) $this->adsWebsiteSlots,
            'enable_rewards' => $this->enableRewards,
            'reward_target_percentage' => $this->rewardTargetPercentage,
            'reward_min_target' => $this->rewardMinTarget,
            'reward_max_target' => $this->rewardMaxTarget,
            'website_duration_days' => (int) $this->websiteDurationDays,
            // Code renewal hidden for now — force online payment only.
            'website_renewal_mode' => 'online',
            'website_renewal_price' => $this->websiteRenewalPrice,
            'website_code_price' => $this->websiteCodePrice,
            'website_code_agent_price' => $this->websiteCodeAgentPrice,
            'website_code_foreign_price' => $this->websiteCodeForeignPrice,
            'website_code_foreign_agent_price' => $this->websiteCodeForeignAgentPrice,
            'website_expired_title' => $this->websiteExpiredTitle,
            'website_expired_message' => $this->websiteExpiredMessage,
            'withdrawal_require_authorization_code' => $this->withdrawalRequireAuthorizationCode,
        ]);

        $this->dispatch('toast', message: 'Feature settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.feature-setting');
    }
}
