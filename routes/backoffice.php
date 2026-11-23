<?php

use App\Features\Admin\Http\Controllers\Backoffice\ProofController;
use App\Features\Admin\Http\Controllers\Backoffice\ReportExportController;
use App\Livewire\Backoffice\Account\ChangePassword;
use App\Livewire\Backoffice\Account\EditProfile;
use App\Livewire\Backoffice\Admins\AdminsIndex;
use App\Livewire\Backoffice\AuditLog\AuditLogIndex;
use App\Livewire\Backoffice\Auth\Login;
use App\Livewire\Backoffice\Dashboard\ShowDashboard;
use App\Livewire\Backoffice\Moderation\DepositsQueue;
use App\Livewire\Backoffice\Moderation\ReviewsQueue;
use App\Livewire\Backoffice\Moderation\SharesQueue;
use App\Livewire\Backoffice\Moderation\WithdrawalsQueue;
use App\Livewire\Backoffice\Notifications\NotificationsIndex;
use App\Livewire\Backoffice\Payments\ProvidersIndex;
use App\Livewire\Backoffice\Reports\ReportsIndex;
use App\Livewire\Backoffice\Settings\SettingsIndex;
use App\Livewire\Backoffice\Transactions\TransactionsIndex;
use App\Livewire\Backoffice\Users\ListUsers;
use App\Livewire\Backoffice\Users\ShowUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function (): void {
    Route::get('login', Login::class)->name('login');
});

Route::middleware('admin.session')->group(function (): void {
    Route::get('/', ShowDashboard::class)->name('dashboard');

    Route::post('logout', function (Request $request) {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backoffice.login');
    })->name('logout');

    Route::get('users', ListUsers::class)->name('users.index');
    Route::get('users/{user}', ShowUser::class)->whereNumber('user')->name('users.show');

    Route::get('moderation/reviews', ReviewsQueue::class)->name('moderation.reviews');
    Route::get('moderation/shares', SharesQueue::class)->name('moderation.shares');
    Route::get('moderation/manual-deposits', DepositsQueue::class)->name('moderation.deposits');
    Route::get('moderation/withdrawals', WithdrawalsQueue::class)->name('moderation.withdrawals');
    Route::get('moderation/manual-deposits/{deposit}/proof', [ProofController::class, 'deposit'])
        ->whereNumber('deposit')->name('moderation.deposits.proof');
    Route::get('moderation/shares/{event}/proof', [ProofController::class, 'share'])
        ->whereNumber('event')->name('moderation.shares.proof');

    Route::get('transactions', TransactionsIndex::class)->name('transactions.index');
    Route::get('payment-providers', ProvidersIndex::class)->name('payments.providers');
    Route::get('notifications', NotificationsIndex::class)->name('notifications.index');
    Route::get('audit-log', AuditLogIndex::class)->name('audit-log.index');

    Route::get('reports', ReportsIndex::class)->name('reports.index');
    Route::get('reports/{report}/export.csv', [ReportExportController::class, 'download'])
        ->whereIn('report', ['users', 'transactions', 'ledger', 'withdrawals'])
        ->name('reports.export');

    Route::get('settings', SettingsIndex::class)->name('settings.index');

    Route::middleware('admin.super')->group(function (): void {
        Route::get('admins', AdminsIndex::class)->name('admins.index');
    });

    Route::get('account/profile', EditProfile::class)->name('account.profile');
    Route::get('account/password', ChangePassword::class)->name('account.password');
});
