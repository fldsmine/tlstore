<?php

namespace App\Livewire\Backoffice\Flutterwave;

use App\Livewire\Backoffice\PaymentManagers\SendMoney as BaseSendMoney;
use App\Models\FlutterwavePayout;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Flutterwave → Send money (a single transfer to a bank account).
 *
 * Beyond the shared flow this adds Flutterwave's cross-currency option: a transfer in one currency
 * may debit another wallet (`debit_currency`), which is why the wallet picker exists here and not
 * for Korapay.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Flutterwave Send Money — Backoffice')]
class SendMoney extends BaseSendMoney
{
    protected function manager(): MerchantWalletService
    {
        return app(FlutterwaveMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return FlutterwavePayout::class;
    }

    /**
     * A cross-currency transfer debits one of these wallets while the recipient is paid in the
     * transfer currency. Flutterwave only settles transfers out of the wallets it supports for
     * payouts, so the list is the payout list.
     *
     * @return list<string>
     */
    protected function debitCurrencies(): array
    {
        return app(FlutterwaveMerchantService::class)->payoutCurrencies();
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.flutterwave.send-money';
    }
}
