<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Support\PayoutSupport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('General Settings — Backoffice')]
class GeneralSetting extends Component
{
    use AuthorizesAdminActions;

    public string $appName = '';

    public bool $enableRegistration = true;

    public bool $enableMaintenanceMode = false;

    public bool $enableEmailVerification = true;

    public string $referralBaseUrl = '';

    public bool $referralPayOnUpgrade = true;

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $contactTelegram = '';

    public string $contactTelegramGeneral = '';

    public string $contactTelegramActivation = '';

    /** The revenue-code / payout support desk (Revenue Authentication page + Telegram fallback). */
    public string $contactTelegramRevenue = '';

    public string $contactAddress = '';

    public string $facebook = '';

    public string $instagram = '';

    public string $x = '';

    public string $website = '';

    public string $tiktok = '';

    public function mount(): void
    {
        $this->appName = (string) config('settings.app.name');
        $this->enableRegistration = (bool) config('settings.app.enable_registration');
        $this->enableMaintenanceMode = (bool) config('settings.app.enable_maintenance_mode');
        $this->enableEmailVerification = (bool) config('settings.app.enable_email_verification');
        $this->referralBaseUrl = (string) config('settings.referral.base_url');
        $this->referralPayOnUpgrade = (bool) config('settings.referral.pay_on_upgrade');
        $this->contactEmail = (string) config('settings.contact.email');
        $this->contactPhone = (string) config('settings.contact.phone');
        $this->contactTelegram = (string) config('settings.contact.social.telegram');
        $this->contactTelegramGeneral = (string) config('settings.contact.telegram_general');
        $this->contactTelegramActivation = (string) config('settings.contact.telegram_activation');
        $this->contactTelegramRevenue = (string) config('settings.contact.telegram_revenue');
        $this->contactAddress = (string) config('settings.contact.address');
        $this->facebook = (string) config('settings.contact.social.facebook');
        $this->instagram = (string) config('settings.contact.social.instagram');
        $this->x = (string) config('settings.contact.social.x');
        $this->website = (string) config('settings.contact.social.website');
        $this->tiktok = (string) config('settings.contact.social.tiktok');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        // An admin may paste a full link or just the handle — expand the handle BEFORE validating, so
        // "@nextelSpecialSupport" is accepted and what lands in the database is always a usable URL.
        $this->contactTelegramRevenue = PayoutSupport::normalizeTelegramLink($this->contactTelegramRevenue);

        $this->validate([
            'appName' => ['required', 'string', 'max:120'],
            'referralBaseUrl' => ['nullable', 'url', 'max:200'],
            'contactEmail' => ['required', 'email'],
            'contactPhone' => ['nullable', 'string', 'max:40'],
            'contactTelegram' => ['nullable', 'url', 'max:200'],
            'contactTelegramGeneral' => ['nullable', 'url', 'max:200'],
            'contactTelegramActivation' => ['nullable', 'url', 'max:200'],
            'contactTelegramRevenue' => ['nullable', 'url', 'max:200'],
            'contactAddress' => ['nullable', 'string', 'max:200'],
        ]);

        update_setting([
            'app_name' => $this->appName,
            'enable_registration' => $this->enableRegistration,
            'enable_maintenance_mode' => $this->enableMaintenanceMode,
            'enable_email_verification' => $this->enableEmailVerification,
            'referral_base_url' => $this->referralBaseUrl,
            'referral_pay_on_upgrade' => $this->referralPayOnUpgrade,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'telegram_link' => $this->contactTelegram,
            'telegram_general_link' => $this->contactTelegramGeneral,
            'telegram_activation_link' => $this->contactTelegramActivation,
            'telegram_revenue_link' => $this->contactTelegramRevenue,
            'contact_address' => $this->contactAddress,
            'facebook_link' => $this->facebook,
            'instagram_link' => $this->instagram,
            'x_link' => $this->x,
            'website_link' => $this->website,
            'tiktok_link' => $this->tiktok,
        ]);

        $this->dispatch('toast', message: 'General settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.general-setting');
    }
}
