<?php

use App\Http\Controllers\AgentExportController;
use App\Http\Controllers\BillTopUpController;
use App\Http\Controllers\PackagePurchaseController;
use App\Http\Controllers\ShareAccessPurchaseController;
use App\Http\Controllers\MinuteTopUpPurchaseController;
use App\Http\Controllers\LoanApplicationFeeController;
use App\Http\Controllers\LoanGuarantorFormFeeController;
use App\Http\Controllers\LoanGuarantorFormPdfController;
use App\Http\Controllers\LoanReceiptController;
use App\Http\Controllers\Product\ProductCheckoutController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SpecialCouponPurchaseController;
use App\Http\Controllers\StoragePurchaseController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\VtpassWebhookController;
use App\Http\Controllers\WebsiteRenewalController;
use App\Http\Controllers\WithdrawalReceiptController;
use App\Http\Middleware\EnsureActivated;
use App\Http\Middleware\EnsureDownloadable;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\RedirectWithdrawalPageToTelegram;
use App\Livewire\AboutUs;
use App\Livewire\Agents;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\NewPassword;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\SetupPin;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\ContactUs;
use App\Livewire\Dashboard\Account\Beneficiary;
use App\Livewire\Dashboard\Account\ChangePassword;
use App\Livewire\Dashboard\Account\ChangePin;
use App\Livewire\Dashboard\Account\DebitCard;
use App\Livewire\Dashboard\Account\EditProfile;
use App\Livewire\Dashboard\Account\PaymentPreview;
use App\Livewire\Dashboard\Account\PaymentSettings;
use App\Livewire\Dashboard\Account\Profile;
use App\Livewire\Dashboard\Account\ResetPin;
use App\Livewire\Dashboard\Account\SupportTickets;
use App\Livewire\Dashboard\ActivateAccount;
use App\Livewire\Dashboard\Agent\AgentBatch;
use App\Livewire\Dashboard\Agent\AgentDashboard;
use App\Livewire\Dashboard\Agent\LiveChats;
use App\Livewire\Dashboard\Agent\Transfer;
use App\Livewire\Dashboard\Calls\CallActive;
use App\Livewire\Dashboard\Calls\CallPreview;
use App\Livewire\Dashboard\Chat\Purchase;
use App\Livewire\Dashboard\Cloud\CloudHome;
use App\Livewire\Dashboard\Cloud\Inbox as CloudInbox;
use App\Livewire\Dashboard\Dashboard;
use App\Livewire\Dashboard\Earnings\Earnings;
use App\Livewire\Dashboard\Earnings\Shareholder;
use App\Livewire\Dashboard\Earnings\ShareholderCallType;
use App\Livewire\Dashboard\Finance\Loan;
use App\Livewire\Dashboard\Finance\Rewards;
use App\Livewire\Dashboard\Finance\Transaction;
use App\Livewire\Dashboard\Marketing\Ads;
use App\Livewire\Dashboard\Marketing\Analytics;
use App\Livewire\Dashboard\Marketing\Invite;
use App\Livewire\Dashboard\Minutes\Paylater;
use App\Livewire\Dashboard\Minutes\Plans;
use App\Livewire\Dashboard\Vas\Purchase as VasPurchase;
use App\Livewire\Dashboard\Vas\Services as VasServices;
use App\Livewire\Dashboard\Vas\VtuSuccess as VasSuccess;
use App\Livewire\Dashboard\Withdrawals\Withdraw;
use App\Livewire\Dashboard\Withdrawals\WithdrawalSuccess;
use App\Livewire\Home;
use App\Livewire\Privacy;
use App\Livewire\Product\Checkout as ProductCheckout;
use App\Livewire\Product\Index as ProductIndex;
use App\Livewire\Product\Success as ProductSuccess;
use App\Livewire\Terms;
use App\Livewire\TopEarners;
use App\Livewire\VerifyToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', Home::class)->name('home');

// Telegram Mini App — a dedicated mobile shell talking to /api/v1. It handles
// its own auth bootstrap (Telegram initData exchange), so it stays public.
Route::view('/mini-app', 'mini-app')->name('mini-app');

Route::post('/webhooks/vtpass', VtpassWebhookController::class)->name('webhooks.vtpass');
Route::post('/webhooks/payments/{provider}', PaymentWebhookController::class)->name('webhooks.payments');
Route::post('/webhooks/telegram', TelegramWebhookController::class)->name('webhooks.telegram');

Route::get('/about-us', AboutUs::class)->name('about');
Route::get('/top-earners', TopEarners::class)->name('top-earners');
Route::get('/agents', Agents::class)->name('agents');
Route::get('/privacy', Privacy::class)->name('privacy');
Route::get('/terms', Terms::class)->name('terms');
Route::get('/contact', ContactUs::class)->name('contact');
Route::get('/verify-token', VerifyToken::class)->name('verify-token');

// File downloads (receipts, token exports) — GET routes the download bridge can hand to a native
// downloader. They sit outside the auth/onboarding/access-gate middleware on purpose: a cookie-less
// client (the Telegram Mini App's downloadFile) would otherwise be redirected to the login page
// instead of receiving the file. EnsureDownloadable accepts either a live session or the temporary
// signature the dashboard renders into the link.
Route::middleware(EnsureDownloadable::class)->group(function () {
    Route::get('/downloads/receipt/{reference}', WithdrawalReceiptController::class)->name('withdrawal.receipt');
    Route::get('/downloads/loan-receipt/{loan}', LoanReceiptController::class)->name('loan.receipt');
    Route::get('/downloads/loan-guarantor-form/{loan}', LoanGuarantorFormPdfController::class)->name('loan.guarantor.form.download');
    Route::get('/dashboard/view-guarantor-form/{loan}', [LoanGuarantorFormPdfController::class, 'view'])->name('loan.guarantor.form.view');
    Route::get('/downloads/batch/{batch}', AgentExportController::class.'@batch')->name('agent.batches.export');
    Route::get('/downloads/tokens', AgentExportController::class.'@tokens')->name('agent.tokens.export');
});

// ── Product pages (product.n-calls.com) ─────────────────────────────────────────────────────────
// A guest storefront for the eSIMs: no auth, no account, no activation gate — the buyer leaves a
// name, phone and email, pays through the gateway this page is configured with (Paystack by
// default), and the product is emailed to them. Registered at /products on EVERY host so the flow
// works before the domain's DNS/vhost exists; when config('product.host') is set, that host also
// serves the storefront at its root.
Route::prefix('products')->group(function () {
    Route::get('/', ProductIndex::class)->name('product.index');

    // The fixed paths come BEFORE the {slug} wildcard so a product can never shadow them.
    Route::get('/return/{reference}', [ProductCheckoutController::class, 'callback'])->name('product.callback');
    Route::get('/receipt/{reference}', ProductSuccess::class)->name('product.success');

    Route::post('/{slug}/checkout', [ProductCheckoutController::class, 'start'])
        ->middleware('throttle:'.config('product.checkout_throttle', '10,1'))
        ->name('product.start');
    Route::get('/{slug}', ProductCheckout::class)->name('product.checkout');
});

// The dedicated domain's root shows the same storefront (only when a host is configured — never a
// catch-all that could shadow the main site when the setting is blank).
if (filled(config('product.host'))) {
    Route::domain(config('product.host'))->group(function () {
        Route::get('/', ProductIndex::class)->name('product.index.host');
        Route::redirect('/store', '/', 301);
    });
}

// ── Digital product pages (soft.n-calls.com) ───────────────────────────────────────────────────
// A guest storefront for the digital products (catalog products only): no auth, no account. The
// buyer leaves a name, phone and email, pays through Paystack by default, and the product is
// emailed to them. Registered at /digital on EVERY host so the flow works before the domain's
// DNS/vhost exists; when config('digital.host') is set, that host also serves the storefront
// at its root.
Route::prefix('digital')->group(function () {
    Route::get('/', \App\Livewire\Digital\Index::class)->name('digital.index');

    Route::get('/return/{reference}', [\App\Http\Controllers\Digital\DigitalCheckoutController::class, 'callback'])->name('digital.callback');
    Route::get('/receipt/{reference}', \App\Livewire\Digital\Success::class)->name('digital.success');

    Route::post('/{slug}/checkout', [\App\Http\Controllers\Digital\DigitalCheckoutController::class, 'start'])
        ->middleware('throttle:'.config('digital.checkout_throttle', '10,1'))
        ->name('digital.start');
    Route::get('/{slug}', \App\Livewire\Digital\Checkout::class)->name('digital.checkout');
});

if (filled(config('digital.host'))) {
    Route::domain(config('digital.host'))->group(function () {
        Route::get('/', \App\Livewire\Digital\Index::class)->name('digital.index.host');
        Route::redirect('/store', '/', 301);
    });
}

// Alias prefix for the soft.n-calls.com digital storefront (same components).
Route::prefix('soft')->group(function () {
    Route::get('/', \App\Livewire\Digital\Index::class)->name('soft.index');
    Route::get('/return/{reference}', [\App\Http\Controllers\Digital\DigitalCheckoutController::class, 'callback'])->name('soft.callback');
    Route::get('/receipt/{reference}', \App\Livewire\Digital\Success::class)->name('soft.success');
    Route::post('/{slug}/checkout', [\App\Http\Controllers\Digital\DigitalCheckoutController::class, 'start'])
        ->middleware('throttle:'.config('digital.checkout_throttle', '10,1'))
        ->name('soft.start');
    Route::get('/{slug}', \App\Livewire\Digital\Checkout::class)->name('soft.checkout');
});

Route::middleware('guest')->prefix('auth')->group(function () {
    Route::get('/register', Register::class)->name('auth.register');
    Route::get('/login', Login::class)->name('auth.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('auth.forgot-password');
    Route::get('/reset-password', NewPassword::class)->name('auth.new-password');
});

Route::middleware(['gate', 'auth'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/verify-email', VerifyEmail::class)->name('auth.verify-email');
        Route::get('/setup-pin', SetupPin::class)->name('auth.setup-pin');
    });

    // Support stays available to authenticated users before activation/onboarding too.
    Route::get('/dashboard/support', SupportTickets::class)->name('support.tickets');

    Route::post('/logout', function (Request $request) {
        // Telegram Mini App users land back on the Mini App login screen;
        // everyone else goes home like before.
        $fromMiniApp = (bool) $request->session()->get('mini_app');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $fromMiniApp
            ? redirect()->route('mini-app', ['logged_out' => 1])
            : redirect()->route('home');
    })->name('logout');

    // Web Push subscription management — available to any authenticated user (even pre-onboarding).
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    // Activation is itself an onboarding step, so it sits before the EnsureOnboarded
    // gate — the component's mount() enforces the verify → activate ordering.
    Route::get('/activate', ActivateAccount::class)->name('activate');

    // Buy your activation token from a vendor via live chat — reachable BEFORE activation
    // (a new user chats a vendor to buy the token they'll activate with).
    Route::get('/buy', Purchase::class)->name('chat.purchase');

    // Pay for activation/upgrade directly (Korapay checkout) instead of buying a token — the
    // first-activation flow must be reachable before onboarding completes, so it sits here too.
    Route::post('/dashboard/plans/buy', PackagePurchaseController::class.'@start')->middleware('throttle:6,1')->name('plans.buy.start');
    Route::get('/dashboard/plans/buy/callback', PackagePurchaseController::class.'@callback')->name('plans.buy.callback');
    Route::get('/dashboard/share-access/buy/callback', ShareAccessPurchaseController::class.'@callback')->name('share-access.buy.callback');
    Route::get('/dashboard/minutes/top-up/buy/callback', MinuteTopUpPurchaseController::class.'@callback')->name('minutes.topup.buy.callback');
    Route::get('/dashboard/loan/fee/callback', [LoanApplicationFeeController::class, 'callback'])->name('loan.fee.callback');
    Route::get('/dashboard/loan/guarantor-fee/callback', [LoanGuarantorFormFeeController::class, 'callback'])->name('loan.guarantor.fee.callback');

    Route::middleware(EnsureOnboarded::class)->prefix('dashboard')->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('/change-pin', ChangePin::class)->name('change-pin');
        Route::get('/reset-pin', ResetPin::class)->name('reset-pin');

        Route::get('/transactions', Transaction::class)->name('transactions');
        // Ordinary document navigation is intentional: Livewire prefetch must not
        // consume this redirect marker before the user actually clicks.
        Route::get('/withdraw', Withdraw::class)
            ->middleware(RedirectWithdrawalPageToTelegram::class)
            ->name('withdraw');
        Route::get('/withdraw/success', WithdrawalSuccess::class)->name('withdrawal.success');
        Route::get('/profile', Profile::class)->name('profile');
        Route::get('/profile/edit', EditProfile::class)->name('profile.edit');
        Route::get('/change-password', ChangePassword::class)->name('change-password');
        Route::get('/payment-settings', PaymentSettings::class)->name('payment-settings');
        Route::get('/payment-preview', PaymentPreview::class)->name('payment-preview');
        Route::get('/invite', Invite::class)->name('invite');

        Route::get('/earnings', Earnings::class)->name('earnings');
        Route::get('/shareholder', Shareholder::class)->name('shareholder');
        Route::get('/shareholder/{callType}', ShareholderCallType::class)->name('shareholder.show');
        Route::get('/plans', Plans::class)->name('plans');
        Route::post('/share-access/buy', ShareAccessPurchaseController::class.'@start')->middleware('throttle:6,1')->name('share-access.buy.start');
        Route::post('/minutes/top-up/buy', MinuteTopUpPurchaseController::class.'@start')->middleware('throttle:6,1')->name('minutes.topup.buy.start');

        Route::post('/bills/top-up', BillTopUpController::class.'@start')->middleware('throttle:6,1')->name('bills.topup.start');
        Route::get('/bills/top-up/callback', BillTopUpController::class.'@callback')->name('bills.topup.callback');
        Route::post('/website/renew', WebsiteRenewalController::class.'@start')->middleware('throttle:6,1')->name('website.renew.start');
        Route::get('/website/renew/callback', WebsiteRenewalController::class.'@callback')->name('website.renew.callback');

        // Buy a Cloud storage plan directly with a card (Korapay) instead of a vendor token.
        Route::post('/cloud/storage/buy', StoragePurchaseController::class.'@start')->middleware('throttle:6,1')->name('cloud.storage.buy.start');
        Route::get('/cloud/storage/buy/callback', StoragePurchaseController::class.'@callback')->name('cloud.storage.buy.callback');

        Route::get('/vas', VasServices::class)->name('vas');
        Route::get('/vas/success', VasSuccess::class)->name('vas.success');
        Route::get('/vas/{category}', VasPurchase::class)->name('vas.category')
            ->whereIn('category', ['airtime', 'data', 'tv', 'electricity', 'education']);

        Route::get('/cloud', CloudHome::class)->name('cloud');
        Route::get('/cloud/inbox', CloudInbox::class)->name('cloud.inbox');
        Route::get('/special-coupon', \App\Livewire\Dashboard\SpecialCoupon\Activate::class)->name('special-coupon');

        // Buy a Revenue Authentication code with a card instead of sourcing a vendor coupon. The code
        // is issued to the buyer (storage included) the moment the charge verifies server-side.
        Route::post('/special-coupon/pay', SpecialCouponPurchaseController::class.'@start')->middleware('throttle:6,1')->name('special-coupon.buy.start');
        Route::get('/special-coupon/pay/callback', SpecialCouponPurchaseController::class.'@callback')->name('special-coupon.buy.callback');

        Route::get('/agent', AgentDashboard::class)->name('agent.dashboard');
        Route::get('/agent/chats', LiveChats::class)->name('agent.chats');
        Route::get('/agent/batches/{batch}', AgentBatch::class)->name('agent.batches.show');
        Route::get('/agent/transfer', Transfer::class)->name('agent.transfer');

        Route::middleware(EnsureActivated::class)->group(function () {
            Route::get('/beneficiaries', Beneficiary::class)->name('beneficiaries');
            Route::get('/my-website', Analytics::class)->name('my-website');
            Route::get('/call/preview', CallPreview::class)->name('call.preview');
            Route::get('/call/active', CallActive::class)->name('call.active');
            Route::get('/ads', Ads::class)->name('ads');
            Route::get('/rewards', Rewards::class)->name('rewards');
            Route::get('/loan', Loan::class)->name('loan');
            Route::get('/paylater', Paylater::class)->name('paylater');
            Route::get('/debit-card', DebitCard::class)->name('debit-card');
        });
    });
});


// ── Manual bank transfer payment page ──────────────────────────────────────────────────────────
Route::get('/manual-payment/{reference}', function (string $reference) {
    $amount = null;
    $currency = null;
    $itemName = null;

    $order = \App\Models\ProductOrder::where('reference', $reference)->first();
    if ($order !== null) {
        $amount = $order->amount_ngn;
        $currency = $order->currency;
        $itemName = $order->product_name;
    } else {
        $transaction = \App\Models\Transaction::where('reference', $reference)->first();
        if ($transaction !== null) {
            $meta = is_array($transaction->meta) ? $transaction->meta : [];
            $amount = $meta['amount_ngn'] ?? null;
            $currency = $meta['currency'] ?? null;
            $itemName = $transaction->description;
        }
    }

    return view('manual-payment', [
        'reference' => $reference,
        'amount' => $amount,
        'currency' => $currency,
        'itemName' => $itemName,
    ]);
})->name('manual-payment.show');
