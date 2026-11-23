<?php

/*
|--------------------------------------------------------------------------
| API Routes (mobile application)
|--------------------------------------------------------------------------
|
| Versioned REST surface consumed by the mobile app. Everything reuses the
| same services the Livewire web app uses — this file only wires routes to
| controllers, never contains business rules.
|
*/

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\EarningsController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\MinutePlanController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NumberController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PayLaterController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\RewardController;
use App\Http\Controllers\Api\V1\TelegramAuthController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Middleware\EnsureFullApiToken;
use App\Http\Middleware\EnsureSupportTicketAccess;
use App\Http\Controllers\Api\V1\VasController;
use App\Http\Controllers\Api\V1\VtuOrderController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WebsiteController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('throttle:api')
    ->group(function (): void {

        // ── Public ────────────────────────────────────────────────────────────
        Route::get('countries', [CountryController::class, 'index']);
        Route::get('announcements', [AnnouncementController::class, 'index']);
        Route::get('packages', [PackageController::class, 'index']);
        Route::get('coupons/verify', [CouponController::class, 'verify']);

        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:api-register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api-auth');
        Route::post('password/forgot', [PasswordController::class, 'forgot'])->middleware('throttle:api-auth');
        Route::post('password/reset', [PasswordController::class, 'reset'])->middleware('throttle:api-auth');

        // Telegram Mini App: exchange validated initData for a bearer token.
        Route::post('telegram/auth', [TelegramAuthController::class, 'auth'])->middleware('throttle:api-auth');

        // ── Authenticated ─────────────────────────────────────────────────────
        Route::middleware(['auth:sanctum', EnsureFullApiToken::class])->group(function (): void {

            // Session + identity
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('user', [AuthController::class, 'user']);

            // Mini App → Livewire handoff: mint a web-session cookie so the
            // WebView can load the full dashboard as the signed-in user.
            Route::middleware('web')->post('web-session', [AuthController::class, 'webSession'])
                ->middleware('throttle:api-auth');

            // Telegram Mini App: link / unlink the Telegram identity.
            Route::post('telegram/link', [TelegramAuthController::class, 'link'])->middleware('throttle:api-auth');
            Route::post('telegram/unlink', [TelegramAuthController::class, 'unlink'])->middleware('throttle:api-auth');
            // Telegram-delivered verification auto-confirm (Mini App sign-up).
            Route::post('telegram/verify', [TelegramAuthController::class, 'verify'])->middleware('throttle:api-otp');

            // Onboarding: email verification (OTP)
            Route::post('email/verify', [AccountController::class, 'verifyEmail'])->middleware('throttle:api-otp');
            Route::post('email/resend', [AccountController::class, 'resendEmailCode'])->middleware('throttle:api-otp');

            // Profile
            Route::put('profile', [ProfileController::class, 'update']);
            Route::post('profile/avatar', [ProfileController::class, 'avatar']);
            Route::put('account/password', [AccountController::class, 'changePassword'])->middleware('throttle:api-auth');

            // Transaction PIN lifecycle
            Route::post('account/pin', [AccountController::class, 'setupPin']);
            Route::put('account/pin', [AccountController::class, 'changePin']);
            Route::post('account/pin/reset', [AccountController::class, 'resetPin'])->middleware('throttle:api-sensitive');

            // Packages / activation (token redemption)
            Route::post('activate', [PackageController::class, 'activate'])->middleware('throttle:api-sensitive');
            Route::post('upgrade', [PackageController::class, 'upgrade'])->middleware('throttle:api-sensitive');

            // Extra minutes ("shares")
            Route::get('minute-plans', [MinutePlanController::class, 'index']);
            Route::post('minutes/redeem', [MinutePlanController::class, 'redeem'])->middleware('throttle:api-sensitive');

            // Personalized website
            Route::get('website', [WebsiteController::class, 'show']);
            Route::post('website/renew', [WebsiteController::class, 'renew'])->middleware('throttle:api-sensitive');
            Route::post('website/renew/code', [WebsiteController::class, 'redeemCode'])->middleware('throttle:api-sensitive');

            // Wallets & transactions
            Route::get('wallets', [WalletController::class, 'index']);
            Route::get('transactions', [TransactionController::class, 'index']);
            Route::get('transactions/{transaction}', [TransactionController::class, 'show']);

            // Beneficiaries (allocated call numbers)
            Route::get('numbers', [NumberController::class, 'index']);

            // Withdrawals + payout account
            Route::get('withdrawals', [WithdrawalController::class, 'index']);
            Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show']);
            Route::post('withdrawals', [WithdrawalController::class, 'store'])->middleware('throttle:api-sensitive');
            Route::get('banks', [BankAccountController::class, 'banks']);
            Route::get('bank-account', [BankAccountController::class, 'show']);
            Route::post('bank-account/verify', [BankAccountController::class, 'verify'])->middleware('throttle:api-sensitive');
            Route::post('bank-account', [BankAccountController::class, 'store']);
            Route::put('bank-account', [BankAccountController::class, 'update']);

            // Bills wallet card top-up + gateway charge settlement
            Route::post('bills/topup', [PaymentController::class, 'topUp'])->middleware('throttle:api-sensitive');
            Route::post('payments/{reference}/finalize', [PaymentController::class, 'finalize'])->middleware('throttle:api-sensitive');

            // Earnings: today's claimable call pool
            Route::get('earnings', [EarningsController::class, 'index']);
            Route::get('earnings/portal', [EarningsController::class, 'portal']);
            Route::post('earnings/claim-all', [EarningsController::class, 'claimAll'])->middleware('throttle:api-sensitive');
            Route::post('earnings/{record}/claim', [EarningsController::class, 'claim'])->middleware('throttle:api-sensitive');

            // Target rewards
            Route::get('rewards', [RewardController::class, 'index']);
            Route::post('rewards/target', [RewardController::class, 'setTarget']);
            Route::post('rewards/{target}/claim', [RewardController::class, 'claim']);
            Route::post('rewards/claim-all', [RewardController::class, 'claimAll']);

            // Share-to-earn ads
            Route::get('ads', [AdController::class, 'index']);
            Route::post('ads/{ad}/share', [AdController::class, 'share'])->middleware('api.activated');

            // Referrals / team
            Route::get('referrals', [ReferralController::class, 'index']);

            // Notifications (N-Cloud inbox, incl. system notices)
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

            // VTU / bill payments
            Route::prefix('vas')->group(function (): void {
                Route::get('categories', [VasController::class, 'categories']);
                Route::get('categories/{category}', [VasController::class, 'category']);
                Route::get('variations/{serviceId}', [VasController::class, 'variations']);
                Route::post('verify', [VasController::class, 'verify'])->middleware('throttle:api-sensitive');
                Route::post('purchase', [VasController::class, 'purchase'])->middleware('throttle:api-sensitive');
            });
            Route::get('vas/orders', [VtuOrderController::class, 'index']);
            Route::get('vas/orders/{order}', [VtuOrderController::class, 'show']);

            // Pay Later (minutes on credit)
            Route::get('pay-later', [PayLaterController::class, 'index']);
            Route::post('pay-later', [PayLaterController::class, 'apply'])->middleware('throttle:api-sensitive');
            Route::post('pay-later/{credit}/repay', [PayLaterController::class, 'repay'])->middleware('throttle:api-sensitive');

            // Loans
            Route::get('loans', [LoanController::class, 'index']);
            Route::post('loans', [LoanController::class, 'store'])->middleware('throttle:api-auth');
            Route::post('loans/{loan}/guarantor-form/payment', [LoanController::class, 'startGuarantorFormPayment'])->middleware('throttle:api-sensitive');
            Route::post('loans/{loan}/guarantor-form/payment/{reference}/refresh', [LoanController::class, 'refreshGuarantorFormPayment'])->middleware('throttle:api-sensitive');
            Route::delete('loans/{loan}/guarantor-form/payment/{reference}', [LoanController::class, 'cancelGuarantorFormPayment'])->middleware('throttle:api-sensitive');
            Route::post('loans/{loan}/guarantor-form', [LoanController::class, 'submitGuarantorForm'])->middleware('throttle:api-sensitive');

            // Live chat with distributors
            Route::get('chats', [ChatController::class, 'index']);
            Route::post('chats', [ChatController::class, 'open']);
            Route::get('chats/{chat}/messages', [ChatController::class, 'messages']);
            Route::post('chats/{chat}/messages', [ChatController::class, 'storeMessage']);
            Route::post('chats/{chat}/read', [ChatController::class, 'markRead']);

            // Vendor / agent area
            Route::middleware('api.agent')->prefix('agent')->group(function (): void {
                Route::get('summary', [AgentController::class, 'summary']);
                Route::get('plans', [AgentController::class, 'plans']);
                Route::get('tokens', [AgentController::class, 'tokens']);
                Route::post('tokens', [AgentController::class, 'mintTokens'])->middleware('throttle:api-sensitive');
                Route::get('batches', [AgentController::class, 'batches']);
                Route::post('transfer', [AgentController::class, 'transfer'])->middleware('throttle:api-sensitive');
            });
        });

        // Authenticated ticket endpoints also accept a short-lived support-only token.
        Route::prefix('support-tickets')
            ->middleware(['auth:sanctum', EnsureSupportTicketAccess::class, 'throttle:30,1'])
            ->group(function (): void {
                Route::get('/', [SupportTicketController::class, 'index']);
                Route::post('/', [SupportTicketController::class, 'store']);
                Route::get('{ticket}', [SupportTicketController::class, 'show']);
                Route::post('{ticket}/messages', [SupportTicketController::class, 'reply']);
            });
    });
