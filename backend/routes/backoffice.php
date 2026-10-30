<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Middleware\EnsureAdminHasRole;
use App\Livewire\Backoffice\Account\ChangePassword;
use App\Livewire\Backoffice\Account\EditProfile;
use App\Livewire\Backoffice\Admins\ListAdmins;
use App\Livewire\Backoffice\Ads\ListAds;
use App\Livewire\Backoffice\Announcements\ListAnnouncements;
use App\Livewire\Backoffice\Auth\Login;
use App\Livewire\Backoffice\Banks\ListBanks;
use App\Livewire\Backoffice\CallClaims\ListCallClaims;
use App\Livewire\Backoffice\CallIds\ListCallIds;
use App\Livewire\Backoffice\CallRecords\ListCallRecords;
use App\Livewire\Backoffice\CallTypes\ListCallTypes;
use App\Livewire\Backoffice\Cloud\ListCloudBroadcasts;
use App\Livewire\Backoffice\Cloud\ListCloudDistributions;
use App\Livewire\Backoffice\Cloud\ListCloudNumbers;
use App\Livewire\Backoffice\Cloud\ListCloudStoragePlans;
use App\Livewire\Backoffice\Cloud\ShowCloudNumber;
use App\Livewire\Backoffice\Countries\ListCountries;
use App\Livewire\Backoffice\Coupons\ListBatches;
use App\Livewire\Backoffice\Coupons\ListCoupons;
use App\Livewire\Backoffice\Coupons\ShowBatch;
use App\Livewire\Backoffice\Currencies\ListCurrencies;
use App\Livewire\Backoffice\Dashboard\ShowDashboard;
use App\Livewire\Backoffice\Bachs\Balance as BachsBalance;
use App\Livewire\Backoffice\Bachs\BalanceHistory as BachsBalanceHistory;
use App\Livewire\Backoffice\Bachs\SendMoney as BachsSendMoney;
use App\Livewire\Backoffice\Bachs\VerifyPayout as BachsVerifyPayout;
use App\Livewire\Backoffice\Flutterwave\Balance as FlutterwaveBalance;
use App\Livewire\Backoffice\Flutterwave\BalanceHistory as FlutterwaveBalanceHistory;
use App\Livewire\Backoffice\Flutterwave\SendMoney as FlutterwaveSendMoney;
use App\Livewire\Backoffice\Flutterwave\VerifyPayout as FlutterwaveVerifyPayout;
use App\Livewire\Backoffice\Korapay\Balance as KorapayBalance;
use App\Livewire\Backoffice\Korapay\BalanceHistory as KorapayBalanceHistory;
use App\Livewire\Backoffice\Korapay\ListPaymentReferences;
use App\Livewire\Backoffice\Korapay\SendMoney as KorapaySendMoney;
use App\Livewire\Backoffice\Korapay\VerifyPayout as KorapayVerifyPayout;
use App\Livewire\Backoffice\Paystack\Balance as PaystackBalance;
use App\Livewire\Backoffice\Paystack\BalanceHistory as PaystackBalanceHistory;
use App\Livewire\Backoffice\Paystack\SendMoney as PaystackSendMoney;
use App\Livewire\Backoffice\Paystack\VerifyPayout as PaystackVerifyPayout;
use App\Livewire\Backoffice\Loans\ListLoans;
use App\Livewire\Backoffice\Mailing\ManageMailingCampaigns;
use App\Livewire\Backoffice\MinutePlans\ListMinutePlans;
use App\Livewire\Backoffice\Packages\ListPackages;
use App\Livewire\Backoffice\PayLater\ListPayLater;
use App\Livewire\Backoffice\Payments\ManagePaymentRequests;
use App\Livewire\Backoffice\Popups\ListPopups;
use App\Livewire\Backoffice\Products\ListProducts;
use App\Livewire\Backoffice\Products\Orders as ProductOrders;
use App\Livewire\Backoffice\Products\OrderShow as ProductOrderShow;
use App\Livewire\Backoffice\Referrals\ListReferrals;
use App\Livewire\Backoffice\Reports\InboxOpens;
use App\Livewire\Backoffice\Reports\ShowInboxOpens;
use App\Livewire\Backoffice\Reports\ShowReports;
use App\Livewire\Backoffice\Reports\ShowSignIns;
use App\Livewire\Backoffice\Reports\SignIns;
use App\Livewire\Backoffice\Reports\WithdrawalEligibleBalances;
use App\Livewire\Backoffice\RewardTargets\ListRewardTargets;
use App\Livewire\Backoffice\Settings\AnalyticsSetting;
use App\Livewire\Backoffice\Settings\BackupSetting;
use App\Livewire\Backoffice\Settings\ChatSetting;
use App\Livewire\Backoffice\Settings\CloudSetting;
use App\Livewire\Backoffice\Settings\FeatureSetting;
use App\Livewire\Backoffice\Settings\GeneralSetting;
use App\Livewire\Backoffice\Settings\ServicesSetting;
use App\Livewire\Backoffice\Settings\TokenSetting;
use App\Livewire\Backoffice\Settings\VtuSetting;
use App\Livewire\Backoffice\Settings\WithdrawalSetting;
use App\Livewire\Backoffice\Support\ListSupportTickets;
use App\Livewire\Backoffice\Support\ShowSupportTicket;
use App\Livewire\Backoffice\Telegram\ListTelegramUsers;
use App\Livewire\Backoffice\Telegram\ShowTelegramUser;
use App\Livewire\Backoffice\Transactions\ListTransactions;
use App\Livewire\Backoffice\Users\ListUsers;
use App\Livewire\Backoffice\Users\ShowUser;
use App\Livewire\Backoffice\Vas\ListVtuOrders;
use App\Livewire\Backoffice\Withdrawals\ListPaymentSheets;
use App\Livewire\Backoffice\Withdrawals\ListWithdrawals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function () {
    Route::get('login', Login::class)->name('login');
});

Route::middleware('auth:admin')->group(function () {
    Route::get('/', ShowDashboard::class)->name('dashboard');
    Route::get('reports', ShowReports::class)->name('reports.index');
    Route::get('reports/withdrawal-eligible-balances', WithdrawalEligibleBalances::class)->name('reports.withdrawal-eligible.index');

    // Focused reports that drill down to a page per user: who signed in (today by default, with
    // day-by-day navigation) and who opened their Cloud Inbox SMS in the dashboard.
    Route::get('reports/sign-ins', SignIns::class)->name('reports.signins.index');
    Route::get('reports/sign-ins/{user}', ShowSignIns::class)->name('reports.signins.show');
    Route::get('reports/inbox-opens', InboxOpens::class)->name('reports.inbox-opens.index');
    Route::get('reports/inbox-opens/{user}', ShowInboxOpens::class)->name('reports.inbox-opens.show');

    Route::post('logout', function (Request $request) {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backoffice.login');
    })->name('logout');

    Route::get('users', ListUsers::class)->name('users.index');
    Route::get('users/{user}', ShowUser::class)->name('users.show');
    Route::get('mailing', ManageMailingCampaigns::class)->name('mailing.index');
    Route::get('telegram-users', ListTelegramUsers::class)->name('telegram-users.index');
    Route::get('telegram-users/{identity}', ShowTelegramUser::class)->name('telegram-users.show');
    Route::get('support-tickets', ListSupportTickets::class)->name('support-tickets.index');
    Route::get('support-tickets/{ticket}', ShowSupportTicket::class)->name('support-tickets.show');

    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
    Route::post('stop-impersonating', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Merchant management for the payment managers the system implements (Korapay, Paystack,
    // Flutterwave).
    // Balance / history / verification are readable by any admin; Send Money actually disburses
    // funds, so it sits behind the super-admin role (the components re-check that role on every
    // action too — Livewire updates don't replay route middleware). Which manager is the active one
    // only decides what the sidebar offers; the pages themselves stay reachable either way.
    Route::get('korapay', KorapayBalance::class)->name('korapay.balance');
    Route::get('korapay/history', KorapayBalanceHistory::class)->name('korapay.history');
    Route::get('korapay/verify', KorapayVerifyPayout::class)->name('korapay.verify');

    Route::get('flutterwave', FlutterwaveBalance::class)->name('flutterwave.balance');
    Route::get('flutterwave/history', FlutterwaveBalanceHistory::class)->name('flutterwave.history');
    Route::get('flutterwave/verify', FlutterwaveVerifyPayout::class)->name('flutterwave.verify');

    Route::get('bachs', BachsBalance::class)->name('bachs.balance');
    Route::get('bachs/history', BachsBalanceHistory::class)->name('bachs.history');
    Route::get('bachs/verify', BachsVerifyPayout::class)->name('bachs.verify');

    Route::get('paystack', PaystackBalance::class)->name('paystack.balance');
    Route::get('paystack/history', PaystackBalanceHistory::class)->name('paystack.history');
    Route::get('paystack/verify', PaystackVerifyPayout::class)->name('paystack.verify');

    // The payment-reference ledger is provider-agnostic: charges collected through ANY gateway land
    // there, so it is shared by every manager. `korapay/references` stays as a permanent alias.
    Route::get('payment-references', ListPaymentReferences::class)->name('payment-references');
    Route::get('payment-requests', ManagePaymentRequests::class)->name('payment-requests.index');
    Route::redirect('korapay/references', '/backoffice/payment-references')->name('korapay.references');
    Route::redirect('bachs/references', '/backoffice/payment-references')->name('bachs.references');

    Route::middleware(EnsureAdminHasRole::class.':super')->group(function () {
        Route::get('korapay/send-money', KorapaySendMoney::class)->name('korapay.send-money');
        Route::get('flutterwave/send-money', FlutterwaveSendMoney::class)->name('flutterwave.send-money');
        Route::get('paystack/send-money', PaystackSendMoney::class)->name('paystack.send-money');
        Route::get('bachs/send-money', BachsSendMoney::class)->name('bachs.send-money');
    });

    Route::get('withdrawals', ListWithdrawals::class)->name('withdrawals.index');
    Route::get('withdrawals/payment-sheets', ListPaymentSheets::class)->name('withdrawals.payment-sheets');
    Route::get('transactions', ListTransactions::class)->name('transactions.index');
    Route::get('loans', ListLoans::class)->name('loans.index');
    Route::get('pay-later', ListPayLater::class)->name('pay-later.index');
    Route::get('reward-targets', ListRewardTargets::class)->name('reward-targets.index');
    Route::get('referrals', ListReferrals::class)->name('referrals.index');
    Route::get('call-logs', ListCallRecords::class)->name('call-records.index');
    Route::get('call-ids', ListCallIds::class)->name('call-ids.index');
    Route::get('call-claims', ListCallClaims::class)->name('call-claims.index');
    Route::get('bill-payments', ListVtuOrders::class)->name('vas.orders.index');

    // The catalog the product pages sell from: eSIM plans come from Packages, and these products
    // are created here (image, details, price) and published straight to the storefront.
    Route::get('products/catalog', ListProducts::class)->name('products.catalog');

    // Guest eSIM orders taken on the product pages (product.n-calls.com): the fulfilment queue —
    // what has been paid for and not yet emailed to the buyer.
    Route::get('products/orders', ProductOrders::class)->name('products.orders');
    Route::get('products/orders/{order}', ProductOrderShow::class)->name('products.orders.show');

    Route::get('packages', ListPackages::class)->name('packages.index');
    Route::get('call-types', ListCallTypes::class)->name('call-types.index');
    Route::get('minute-plans', ListMinutePlans::class)->name('minute-plans.index');
    Route::get('banks', ListBanks::class)->name('banks.index');
    Route::get('countries', ListCountries::class)->name('countries.index');
    Route::get('currencies', ListCurrencies::class)->name('currencies.index');

    Route::get('cloud/storage-plans', ListCloudStoragePlans::class)->name('cloud.storage-plans.index');
    Route::get('cloud/numbers', ListCloudNumbers::class)->name('cloud.numbers.index');
    Route::get('cloud/numbers/{number}', ShowCloudNumber::class)->name('cloud.numbers.show');
    Route::get('cloud/broadcasts', ListCloudBroadcasts::class)->name('cloud.broadcasts.index');
    Route::get('cloud/distributions', ListCloudDistributions::class)->name('cloud.distributions.index');

    Route::get('batches', ListBatches::class)->name('batches.index');
    Route::get('batches/{batch}', ShowBatch::class)->name('batches.show');
    Route::get('coupons', ListCoupons::class)->name('coupons.index');

    Route::get('ads', ListAds::class)->name('ads.index');
    Route::get('announcements', ListAnnouncements::class)->name('announcements.index');
    Route::get('popups', ListPopups::class)->name('popups.index');

    Route::get('account/profile', EditProfile::class)->name('account.profile');
    Route::get('account/password', ChangePassword::class)->name('account.password');

    Route::middleware(EnsureAdminHasRole::class.':regular')->group(function () {
        Route::get('settings/general', GeneralSetting::class)->name('settings.general');
        Route::get('settings/withdrawal', WithdrawalSetting::class)->name('settings.withdrawal');
        Route::get('settings/features', FeatureSetting::class)->name('settings.features');
        Route::get('settings/services', ServicesSetting::class)->name('settings.services');
        Route::get('settings/tokens', TokenSetting::class)->name('settings.tokens');
        Route::get('settings/vtu', VtuSetting::class)->name('settings.vtu');
        Route::get('settings/cloud', CloudSetting::class)->name('settings.cloud');
        Route::get('settings/chat', ChatSetting::class)->name('settings.chat');
        Route::get('settings/backup', BackupSetting::class)->name('settings.backup');
        Route::get('settings/analytics', AnalyticsSetting::class)->name('settings.analytics');
    });

    Route::middleware(EnsureAdminHasRole::class.':super')->group(function () {
        Route::get('admins', ListAdmins::class)->name('admins.index');
    });
});
