<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DataBackupController;
use App\Http\Controllers\Admin\MobileAppPackageController;
use App\Http\Controllers\Admin\OfflineSyncReviewController;
use App\Http\Controllers\Admin\PeriodReportController;
use App\Http\Controllers\Admin\PreferenceController;
use App\Http\Controllers\Admin\RoundResultController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ThreeDigitDrawController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DesktopOwnerSetupController;
use App\Http\Controllers\DrawModeController;
use App\Http\Controllers\Operator\AgentSessionController;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\Operator\OfflineSaleSyncController;
use App\Http\Controllers\Operator\SaleEntryController;
use App\Http\Controllers\Operator\SaleVoidController;
use App\Http\Controllers\Operator\ThreeDigitSaleController;
use App\Http\Middleware\RecordAdminActivity;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(
        auth()->check() ? 'dashboard' : 'login'
    );
})->name('home');

Route::get('/desktop/owner-setup', [DesktopOwnerSetupController::class, 'show'])
    ->name('desktop.owner-setup');
Route::post('/desktop/owner-setup', [DesktopOwnerSetupController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('desktop.owner-setup.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [
        AuthenticatedSessionController::class,
        'create',
    ])->name('login');
    Route::post('/login', [
        AuthenticatedSessionController::class,
        'store',
    ])->middleware('throttle:login')->name('login.store');

    Route::get('/forgot-password', [
        PasswordResetLinkController::class,
        'create',
    ])->name('password.request');
    Route::post('/forgot-password', [
        PasswordResetLinkController::class,
        'store',
    ])->middleware('throttle:6,1')->name('password.email');

    Route::get('/reset-password/{token}', [
        NewPasswordController::class,
        'create',
    ])->name('password.reset');
    Route::post('/reset-password', [
        NewPasswordController::class,
        'store',
    ])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware(['auth', 'active', 'preferences'])->group(function (): void {
    Route::post('/logout', [
        AuthenticatedSessionController::class,
        'destroy',
    ])->name('logout');

    Route::get('/dashboard', [
        DashboardController::class,
        'index',
    ])->name('dashboard');
    Route::get('/dashboard/live', [
        DashboardController::class,
        'live',
    ])->name('dashboard.live');
    Route::view('/manual', 'manual.index')->name('manual');
    Route::get('/draw-mode', [DrawModeController::class, 'index'])->name('draw-mode.index');
    Route::post('/draw-mode', [DrawModeController::class, 'store'])->name('draw-mode.store');
    Route::post('/draw-mode/switch', [DrawModeController::class, 'switch'])->name('draw-mode.switch');
    Route::get('/reports/summary', [
        PeriodReportController::class,
        'index',
    ])->name('reports.summary');
    Route::get('/reports/summary/export', [
        PeriodReportController::class,
        'export',
    ])->middleware('throttle:30,1')->name('reports.summary.export');
    Route::post('/operator/offline-sync', [
        OfflineSaleSyncController::class,
        'store',
    ])->middleware('throttle:30,1')->name('operator.offline-sync.store');
    Route::post('/operator/rounds/{round}/agents/{agent}/claim', [
        AgentSessionController::class,
        'claim',
    ])->name('operator.agent-sessions.claim');
    Route::get('/operator/sales', [
        SaleEntryController::class,
        'workspace',
    ])->name('operator.sales.workspace');
    Route::post('/operator/sales/agent', [
        SaleEntryController::class,
        'select',
    ])->middleware('throttle:60,1')->name('operator.sales.select');
    Route::get('/operator/sales/setup', [
        SaleEntryController::class,
        'setup',
    ])->name('operator.sales.setup');
    Route::get('/operator/sales/entry', [
        SaleEntryController::class,
        'entry',
    ])->name('operator.sales.entry');
    Route::get('/three-digit/sales', [
        ThreeDigitSaleController::class,
        'workspace',
    ])->name('three-digit.sales.workspace');
    Route::post('/three-digit/draws/{draw}/agents/{agent}/claim', [
        ThreeDigitSaleController::class,
        'claim',
    ])->name('three-digit.sales.claim');
    Route::post('/three-digit/draws/{draw}/agents/{agent}/sales', [
        ThreeDigitSaleController::class,
        'store',
    ])->middleware('throttle:120,1')->name('three-digit.sales.store');
    Route::post('/three-digit/draws/{draw}/agents/{agent}/sales/batch', [
        ThreeDigitSaleController::class,
        'storeBatch',
    ])->middleware('throttle:120,1')->name('three-digit.sales.store-batch');
    Route::get('/operator/sessions/{agentSession}/sales', [
        SaleEntryController::class,
        'show',
    ])->name('operator.sales.show');
    Route::get('/operator/sessions/{agentSession}/offline', [
        SaleEntryController::class,
        'offlineShell',
    ])->name('operator.sales.offline');
    Route::post('/operator/sessions/{agentSession}/sales', [
        SaleEntryController::class,
        'store',
    ])->middleware('throttle:120,1')->name('operator.sales.store');
    Route::post('/operator/sessions/{agentSession}/sales/batch', [
        SaleEntryController::class,
        'storeBatch',
    ])->middleware('throttle:120,1')->name('operator.sales.store-batch');
    Route::post('/operator/sessions/{agentSession}/sales/{saleInput}/void', [
        SaleVoidController::class,
        'void',
    ])->middleware('throttle:60,1')->name('operator.sales.void');
    Route::post('/operator/sessions/{agentSession}/sales/{saleInput}/restore', [
        SaleVoidController::class,
        'restore',
    ])->middleware('throttle:60,1')->name('operator.sales.restore');
    Route::post('/operator/sessions/{agentSession}/three-digit-sales/{saleInput}/void', [
        SaleVoidController::class,
        'voidThreeDigit',
    ])->middleware('throttle:60,1')->name('operator.three-digit-sales.void');
    Route::post('/operator/sessions/{agentSession}/three-digit-sales/{saleInput}/restore', [
        SaleVoidController::class,
        'restoreThreeDigit',
    ])->middleware('throttle:60,1')->name('operator.three-digit-sales.restore');

    Route::post('/preferences/locale', [PreferenceController::class, 'locale'])
        ->middleware('throttle:60,1')->name('preferences.locale');
    Route::patch('/preferences/display', [PreferenceController::class, 'display'])
        ->middleware('throttle:60,1')->name('preferences.display');
    Route::patch('/preferences/sale-entry', [PreferenceController::class, 'saleEntry'])
        ->middleware('throttle:60,1')->name('preferences.sale-entry');
    Route::patch('/preferences/notifications', [PreferenceController::class, 'notifications'])
        ->middleware('throttle:60,1')->name('preferences.notifications');
    Route::patch('/preferences/security', [PreferenceController::class, 'security'])
        ->middleware('throttle:60,1')->name('preferences.security');

    Route::middleware(['admin', RecordAdminActivity::class])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/settings', [
            SettingsController::class,
            'index',
        ])->name('settings.index');
        Route::put('/settings/round-schedule', [
            SettingsController::class,
            'updateSchedule',
        ])->middleware('throttle:120,1')->name('settings.schedule');
        Route::put('/settings/payouts', [
            SettingsController::class,
            'updatePayoutSettings',
        ])->middleware('throttle:120,1')->name('settings.payouts');
        Route::get('/three-digit', [ThreeDigitDrawController::class, 'index'])
            ->name('three-digit.index');
        Route::put('/three-digit/draws/{draw}/settings', [ThreeDigitDrawController::class, 'updateSettings'])
            ->middleware('throttle:120,1')->name('three-digit.draws.settings');
        Route::post('/three-digit/draws/{draw}/hot-numbers', [ThreeDigitDrawController::class, 'storeHotNumbers'])
            ->middleware('throttle:120,1')->name('three-digit.draws.hot-numbers.store');
        Route::delete('/three-digit/draws/{draw}/hot-numbers/{hotNumber}', [ThreeDigitDrawController::class, 'destroyHotNumber'])
            ->middleware('throttle:120,1')->name('three-digit.draws.hot-numbers.destroy');
        Route::get('/three-digit/results', [ThreeDigitDrawController::class, 'results'])
            ->name('three-digit.results');
        Route::get('/three-digit/draws/{draw}/result', [ThreeDigitDrawController::class, 'showResult'])
            ->name('three-digit.results.show');
        Route::patch('/three-digit/draws/{draw}/result', [ThreeDigitDrawController::class, 'updateResult'])
            ->middleware('throttle:120,1')->name('three-digit.results.update');
        Route::get('/settlements', [
            RoundResultController::class,
            'index',
        ])->name('settlements.index');
        Route::get('/rounds/{round}/settlement', [
            RoundResultController::class,
            'show',
        ])->name('rounds.settlement');
        Route::patch('/rounds/{round}/result', [
            RoundResultController::class,
            'update',
        ])->middleware('throttle:120,1')->name('rounds.result.update');
        Route::post('/settings/agents', [
            SettingsController::class,
            'storeAgent',
        ])->middleware('throttle:120,1')->name('settings.agents.store');
        Route::patch('/settings/agents/{agent}', [
            SettingsController::class,
            'updateAgent',
        ])->middleware('throttle:120,1')->name('settings.agents.update');
        Route::patch('/settings/agents/{agent}/three-digit-commission', [
            SettingsController::class,
            'updateAgentThreeDigitCommission',
        ])->middleware('throttle:120,1')->name('settings.agents.three-digit-commission');
        Route::post('/settings/agents/mobile-access/issue-all', [
            SettingsController::class,
            'issueAllAgentMobileAccess',
        ])->middleware('throttle:10,1')->name('settings.agents.mobile-access.issue-all');
        Route::post('/settings/agents/{agent}/mobile-access/issue', [
            SettingsController::class,
            'issueAgentMobileAccess',
        ])->middleware('throttle:30,1')->name('settings.agents.mobile-access.issue');
        Route::patch('/settings/agents/{agent}/mobile-access', [
            SettingsController::class,
            'updateAgentMobileAccess',
        ])->middleware('throttle:30,1')->name('settings.agents.mobile-access');
        Route::delete('/settings/agents/{agent}/mobile-access/devices', [
            SettingsController::class,
            'revokeAgentMobileTokens',
        ])->middleware('throttle:30,1')->name('settings.agents.mobile-access.devices');
        Route::delete('/settings/agents/{agent}/mobile-access', [
            SettingsController::class,
            'disableAgentMobileAccess',
        ])->middleware('throttle:30,1')->name('settings.agents.mobile-access.disable');
        Route::post('/settings/mobile-app', [
            MobileAppPackageController::class,
            'store',
        ])->middleware('throttle:10,1')->name('settings.mobile-app.store');
        Route::get('/settings/mobile-app/download', [
            MobileAppPackageController::class,
            'download',
        ])->middleware('throttle:30,1')->name('settings.mobile-app.download');
        Route::delete('/settings/mobile-app', [
            MobileAppPackageController::class,
            'destroy',
        ])->middleware('throttle:10,1')->name('settings.mobile-app.destroy');
        Route::patch('/settings/rounds/{round}/agents/{agent}/amount-limit', [
            SettingsController::class,
            'updateAgentRoundAmountLimit',
        ])->middleware('throttle:120,1')->name('settings.rounds.agents.amount-limit');
        Route::patch('/settings/rounds/{round}/agents/{agent}/amount-limit-3d', [
            SettingsController::class,
            'updateAgentRoundAmountLimit3d',
        ])->middleware('throttle:120,1')->name('settings.rounds.agents.amount-limit-3d');
        Route::patch('/settings/rounds/{round}/operators/{operator}/amount-limits', [
            SettingsController::class,
            'updateOperatorRoundAmountLimit',
        ])->middleware('throttle:120,1')->name('settings.rounds.operators.amount-limits');
        Route::patch('/settings/rounds/{round}/amount-limits', [
            SettingsController::class,
            'updateRoundAmountLimits',
        ])->middleware('throttle:120,1')->name('settings.rounds.amount-limits');
        Route::patch('/settings/rounds/{round}/number-amount-limits', [
            SettingsController::class,
            'updateRoundNumberAmountLimits',
        ])->middleware('throttle:120,1')->name('settings.rounds.number-amount-limits');
        Route::post('/settings/rounds/{round}/hot-numbers', [
            SettingsController::class,
            'storeHotNumbers',
        ])->middleware('throttle:120,1')->name('settings.hot-numbers.store');
        Route::delete('/settings/rounds/{round}/hot-numbers/{hotNumber}', [
            SettingsController::class,
            'destroyHotNumber',
        ])->middleware('throttle:120,1')->name('settings.hot-numbers.destroy');
        Route::post('/settings/rounds/{round}/hot-numbers-3d', [
            SettingsController::class,
            'storeThreeDigitHotNumbers',
        ])->middleware('throttle:120,1')->name('settings.hot-numbers-3d.store');
        Route::delete('/settings/rounds/{round}/hot-numbers-3d/{hotNumber}', [
            SettingsController::class,
            'destroyThreeDigitHotNumber',
        ])->middleware('throttle:120,1')->name('settings.hot-numbers-3d.destroy');
        Route::get('/offline-reviews', [
            OfflineSyncReviewController::class,
            'index',
        ])->name('offline-reviews.index');
        Route::get('/offline-reviews/pending', [
            OfflineSyncReviewController::class,
            'pending',
        ])->name('offline-reviews.pending');
        Route::patch('/offline-reviews/{offlineSyncReview}', [
            OfflineSyncReviewController::class,
            'update',
        ])->middleware('throttle:120,1')->name('offline-reviews.update');
        Route::patch('/settings/rules/{codeRule}', [
            SettingsController::class,
            'updateConfiguredRule',
        ])->middleware('throttle:120,1')->name('settings.rules.update');
        Route::post('/settings/rules/custom-number-lists', [
            SettingsController::class,
            'storeCustomNumberList',
        ])->middleware('throttle:120,1')->name('settings.rules.custom.store');
        Route::delete('/settings/rules/custom-number-lists/{codeRule}', [
            SettingsController::class,
            'destroyCustomNumberList',
        ])->middleware('throttle:120,1')->name('settings.rules.custom.destroy');
        Route::patch('/settings/rounds/{round}/reopen', [
            SettingsController::class,
            'reopenRound',
        ])->middleware('throttle:120,1')->name('settings.rounds.reopen');
        Route::patch('/settings/rounds/{round}/close', [
            SettingsController::class,
            'closeRound',
        ])->middleware('throttle:120,1')->name('settings.rounds.close');
        Route::patch('/settings/rounds/{round}/number-limits', [
            SettingsController::class,
            'updateRoundNumberLimits',
        ])->middleware('throttle:120,1')->name('settings.rounds.number-limits');
        Route::get('/users', [
            UserController::class,
            'index',
        ])->name('users.index');
        Route::post('/users', [
            UserController::class,
            'store',
        ])->middleware('throttle:10,1')->name('users.store');
        Route::patch('/users/{user}/status', [
            UserController::class,
            'toggleStatus',
        ])->middleware('throttle:120,1')->name('users.status');
        Route::get('/data-backup', [DataBackupController::class, 'index'])
            ->name('data-backup.index');
        Route::post('/data-backup', [DataBackupController::class, 'store'])
            ->middleware('throttle:6,1')->name('data-backup.store');
        Route::get('/data-backup/{file}/download', [DataBackupController::class, 'download'])
            ->where('file', 'wya-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.sql')
            ->middleware('throttle:30,1')->name('data-backup.download');
        Route::delete('/data-backup/{file}', [DataBackupController::class, 'destroy'])
            ->where('file', 'wya-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.sql')
            ->middleware('throttle:30,1')->name('data-backup.destroy');
        Route::post('/data-backup/restore', [DataBackupController::class, 'restore'])
            ->middleware('throttle:2,1')->name('data-backup.restore');
        Route::get('/activity-log', [ActivityLogController::class, 'index'])
            ->name('activity-log.index');
        Route::get('/activity-log/export', [ActivityLogController::class, 'export'])
            ->name('activity-log.export');
    });
});
