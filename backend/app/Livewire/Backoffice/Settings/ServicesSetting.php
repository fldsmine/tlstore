<?php

namespace App\Livewire\Backoffice\Settings;

use App\Enums\PaymentProvider;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Services Settings — Backoffice')]
class ServicesSetting extends Component
{
    use AuthorizesAdminActions;

    public string $flutterwaveBaseUrl = '';

    public string $flutterwavePublicKey = '';

    public string $flutterwaveSecretKey = '';

    public string $paystackBaseUrl = '';

    public string $paystackPublicKey = '';

    public string $paystackSecretKey = '';

    public string $korapayBaseUrl = '';

    public string $korapayPublicKey = '';

    public string $korapaySecretKey = '';

    public string $korapayCurrency = 'NGN';

    public string $bachsBaseUrl = '';

    public string $bachsPublicKey = '';

    public string $bachsSecretKey = '';

    public string $bachsCurrency = 'NGN';

    public string $bachsWebhookSecret = '';

    public string $vtpassBaseUrl = '';

    public string $vtpassApiKey = '';

    public string $vtpassPublicKey = '';

    public string $vtpassSecretKey = '';

    public string $elevenlabsBaseUrl = '';

    public string $elevenlabsApiKey = '';

    public string $mailHost = '';

    public string $mailPort = '';

    public string $mailUsername = '';

    public string $mailPassword = '';

    public string $mailFromAddress = '';

    public string $mailFromName = '';

    public string $mailDriver = 'smtp';

    public string $resendKey = '';

    /** Which manager the backoffice sidebar manages (korapay|paystack|flutterwave). */
    public string $activePaymentManager = 'korapay';

    public string $renewalProvider = 'korapay';

    public string $billProvider = 'korapay';

    public string $storageProvider = 'korapay';

    public string $packageProvider = 'korapay';

    /** Which provider collects Revenue-Authentication code purchases. */
    public string $specialCouponProvider = 'korapay';

    /** Which provider collects the insurance fee for loan application. */
    public string $loanApplicationFeeProvider = 'korapay';

    public string $manualBankAccountNumber = '';
    public string $manualBankName = '';
    public string $manualBankAccountName = '';
    public string $manualBankSwiftCode = '';

    public string $paystackCurrency = 'NGN';

    public string $flutterwaveCurrency = 'NGN';

    public string $flutterwaveSecretHash = '';

    public function mount(): void
    {

        $this->flutterwaveBaseUrl = (string) config('services.flutterwave.base_url');
        $this->flutterwavePublicKey = (string) config('services.flutterwave.public_key');
        $this->flutterwaveSecretKey = (string) config('services.flutterwave.secret_key');
        $this->paystackBaseUrl = (string) config('services.paystack.base_url');
        $this->paystackPublicKey = (string) config('services.paystack.public_key');
        $this->paystackSecretKey = (string) config('services.paystack.secret_key');

        $this->korapayBaseUrl = (string) config('services.korapay.base_url');
        $this->korapayPublicKey = (string) config('services.korapay.public_key');
        $this->korapaySecretKey = (string) config('services.korapay.secret_key');
        $this->korapayCurrency = (string) config('services.korapay.currency');

        $this->bachsBaseUrl = (string) config('services.bachs.base_url');
        $this->bachsPublicKey = (string) config('services.bachs.public_key');
        $this->bachsSecretKey = (string) config('services.bachs.secret_key');
        $this->bachsCurrency = (string) config('services.bachs.currency');
        $this->bachsWebhookSecret = (string) config('services.bachs.webhook_secret');

        $this->vtpassBaseUrl = (string) config('services.vtpass.base_url');
        $this->vtpassApiKey = (string) config('services.vtpass.api_key');
        $this->vtpassPublicKey = (string) config('services.vtpass.public_key');
        $this->vtpassSecretKey = (string) config('services.vtpass.secret_key');

        $this->elevenlabsBaseUrl = (string) config('services.elevenlabs.base_url');
        $this->elevenlabsApiKey = (string) config('services.elevenlabs.key');

        $this->mailHost = (string) config('mail.mailers.smtp.host');
        $this->mailPort = (string) config('mail.mailers.smtp.port');
        $this->mailUsername = (string) config('mail.mailers.smtp.username');
        $this->mailPassword = (string) config('mail.mailers.smtp.password');
        $this->mailFromAddress = (string) config('mail.from.address');
        $this->mailFromName = (string) config('mail.from.name');
        $this->mailDriver = config('mail.default') === 'resend' ? 'resend' : 'smtp';
        $this->resendKey = (string) config('services.resend.key');

        $this->activePaymentManager = (string) config('settings.payment.active_manager');
        $this->renewalProvider = (string) config('settings.payment.renewal_provider');
        $this->billProvider = (string) config('settings.payment.bill_provider');
        $this->storageProvider = (string) config('settings.payment.storage_provider');
        $this->packageProvider = (string) config('settings.payment.package_provider');
        $this->specialCouponProvider = (string) config('settings.payment.special_coupon_provider');
        $this->loanApplicationFeeProvider = (string) config('settings.payment.loan_application_fee_provider');
        $this->manualBankAccountNumber = (string) config('settings.payment.manual_bank_account_number');
        $this->manualBankName = (string) config('settings.payment.manual_bank_name');
        $this->manualBankAccountName = (string) config('settings.payment.manual_bank_account_name');
        $this->manualBankSwiftCode = (string) config('settings.payment.manual_bank_swift_code');
        $this->paystackCurrency = (string) config('services.paystack.currency');
        $this->flutterwaveCurrency = (string) config('services.flutterwave.currency');
        $this->flutterwaveSecretHash = (string) config('services.flutterwave.secret_hash');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'flutterwaveBaseUrl' => ['nullable', 'url', 'max:255'],
            'flutterwavePublicKey' => ['nullable', 'string', 'max:255'],
            'flutterwaveSecretKey' => ['nullable', 'string', 'max:255'],
            'paystackBaseUrl' => ['nullable', 'url', 'max:255'],
            'paystackPublicKey' => ['nullable', 'string', 'max:255'],
            'paystackSecretKey' => ['nullable', 'string', 'max:255'],
            'korapayBaseUrl' => ['nullable', 'url', 'max:255'],
            'korapayPublicKey' => ['nullable', 'string', 'max:255'],
            'korapaySecretKey' => ['nullable', 'string', 'max:255'],
            'korapayCurrency' => ['required', 'string', 'size:3'],
            'bachsBaseUrl' => ['nullable', 'url', 'max:255'],
            'bachsPublicKey' => ['nullable', 'string', 'max:255'],
            'bachsSecretKey' => ['nullable', 'string', 'max:255'],
            'bachsCurrency' => ['required', 'string', 'size:3'],
            'bachsWebhookSecret' => ['nullable', 'string', 'max:255'],
            'vtpassBaseUrl' => ['nullable', 'url', 'max:255'],
            'vtpassApiKey' => ['nullable', 'string', 'max:255'],
            'vtpassPublicKey' => ['nullable', 'string', 'max:255'],
            'vtpassSecretKey' => ['nullable', 'string', 'max:255'],
            'elevenlabsBaseUrl' => ['nullable', 'url', 'max:255'],
            'elevenlabsApiKey' => ['nullable', 'string', 'max:255'],
            'mailHost' => ['nullable', 'string', 'max:255'],
            'mailPort' => ['nullable', 'string', 'max:10'],
            'mailUsername' => ['nullable', 'string', 'max:255'],
            'mailPassword' => ['nullable', 'string', 'max:255'],
            'mailFromAddress' => ['nullable', 'email', 'max:255'],
            'mailFromName' => ['nullable', 'string', 'max:255'],
            'mailDriver' => ['required', 'in:smtp,resend'],
            'resendKey' => ['nullable', 'string', 'max:255'],
            'activePaymentManager' => ['required', Rule::in(PaymentProvider::values())],
            'renewalProvider' => ['required', Rule::in(PaymentProvider::values())],
            'billProvider' => ['required', Rule::in(PaymentProvider::values())],
            'storageProvider' => ['required', Rule::in(PaymentProvider::values())],
            'packageProvider' => ['required', Rule::in(PaymentProvider::values())],
            'specialCouponProvider' => ['required', Rule::in(PaymentProvider::values())],
            'loanApplicationFeeProvider' => ['required', Rule::in(PaymentProvider::values())],
            'manualBankAccountNumber' => ['nullable', 'string', 'max:64'],
            'manualBankName' => ['nullable', 'string', 'max:120'],
            'manualBankAccountName' => ['nullable', 'string', 'max:120'],
            'manualBankSwiftCode' => ['nullable', 'string', 'max:32'],
            'paystackCurrency' => ['required', 'string', 'size:3'],
            'flutterwaveCurrency' => ['required', 'string', 'size:3'],
            'flutterwaveSecretHash' => ['nullable', 'string', 'max:255'],
        ]);

        update_setting([
            'flutterwave_base_url' => $this->flutterwaveBaseUrl,
            'flutterwave_public_key' => $this->flutterwavePublicKey,
            'flutterwave_secret_key' => $this->flutterwaveSecretKey,
            'paystack_base_url' => $this->paystackBaseUrl,
            'paystack_public_key' => $this->paystackPublicKey,
            'paystack_secret_key' => $this->paystackSecretKey,
            'korapay_base_url' => $this->korapayBaseUrl,
            'korapay_public_key' => $this->korapayPublicKey,
            'korapay_secret_key' => $this->korapaySecretKey,
            'korapay_currency' => strtoupper($this->korapayCurrency),
            'bachs_base_url' => $this->bachsBaseUrl,
            'bachs_public_key' => $this->bachsPublicKey,
            'bachs_secret_key' => $this->bachsSecretKey,
            'bachs_currency' => strtoupper($this->bachsCurrency),
            'bachs_webhook_secret' => $this->bachsWebhookSecret,
            'vtpass_base_url' => $this->vtpassBaseUrl,
            'vtpass_api_key' => $this->vtpassApiKey,
            'vtpass_public_key' => $this->vtpassPublicKey,
            'vtpass_secret_key' => $this->vtpassSecretKey,
            'elevenlabs_base_url' => $this->elevenlabsBaseUrl,
            'elevenlabs_api_key' => $this->elevenlabsApiKey,
            'mail_host' => $this->mailHost,
            'mail_port' => $this->mailPort,
            'mail_username' => $this->mailUsername,
            'mail_password' => $this->mailPassword,
            'mail_from_address' => $this->mailFromAddress,
            'mail_from_name' => $this->mailFromName,
            'mail_driver' => $this->mailDriver,
            'resend_key' => $this->resendKey,
            'active_payment_manager' => strtolower($this->activePaymentManager),
            'renewal_payment_provider' => $this->renewalProvider,
            'bill_payment_provider' => $this->billProvider,
            'storage_payment_provider' => $this->storageProvider,
            'package_payment_provider' => $this->packageProvider,
            'special_coupon_payment_provider' => $this->specialCouponProvider,
            'loan_application_fee_payment_provider' => $this->loanApplicationFeeProvider,
            'manual_bank_account_number' => $this->manualBankAccountNumber,
            'manual_bank_name' => $this->manualBankName,
            'manual_bank_account_name' => $this->manualBankAccountName,
            'manual_bank_swift_code' => $this->manualBankSwiftCode,
            'paystack_currency' => strtoupper($this->paystackCurrency),
            'flutterwave_currency' => strtoupper($this->flutterwaveCurrency),
            'flutterwave_secret_hash' => $this->flutterwaveSecretHash,
        ]);

        $this->dispatch('toast', message: 'Service settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.services-setting');
    }
}
