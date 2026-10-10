<?php

use App\Http\Controllers\Admin\OfflineSyncReviewController;
use App\Http\Controllers\Admin\PeriodReportController;
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
use App\Http\Controllers\Operator\ThreeDigitSaleController;
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

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [
        AuthenticatedSessionController::class,
        'destroy',
    ])->name('logout');

    Route::get('/dashboard', [
        DashboardController::class,
        'index',
    ])->name('dashboard');
    Route::view('/manual', 'manual.index')->name('manual');
    Route::get('/draw-mode', [DrawModeController::class, 'index'])->name('draw-mode.index');
    Route::post('/draw-mode', [DrawModeController::class, 'store'])->name('draw-mode.store');
    Route::post('/draw-mode/switch', [DrawModeController::class, 'switch'])->name('draw-mode.switch');
    Route::get('/reports/summary', [
        PeriodReportController::class,
        'index',
    ])->name('reports.summary');
    Route::post('/operator/offline-sync', [
        OfflineSaleSyncController::class,
        'store',
    ])->middleware('throttle:30,1')->name('operator.offline-sync.store');
    Route::post('/operator/rounds/{round}/agents/{agent}/claim', [
        AgentSessionController::class,
        'claim',
    ])->name('operator.agent-sessions.claim');
    Route::get('/operator/sales', [
        AgentSessionController::class,
        'workspace',
    ])->name('operator.sales.workspace');
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
    ])->name('operator.sales.store');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/settings', [
            SettingsController::class,
            'index',
        ])->name('settings.index');
        Route::put('/settings/round-schedule', [
            SettingsController::class,
            'updateSchedule',
        ])->name('settings.schedule');
        Route::put('/settings/payouts', [
            SettingsController::class,
            'updatePayoutSettings',
        ])->name('settings.payouts');
        Route::get('/three-digit', [ThreeDigitDrawController::class, 'index'])
            ->name('three-digit.index');
        Route::put('/three-digit/draws/{draw}/settings', [ThreeDigitDrawController::class, 'updateSettings'])
            ->name('three-digit.draws.settings');
        Route::post('/three-digit/draws/{draw}/hot-numbers', [ThreeDigitDrawController::class, 'storeHotNumbers'])
            ->name('three-digit.draws.hot-numbers.store');
        Route::delete('/three-digit/draws/{draw}/hot-numbers/{hotNumber}', [ThreeDigitDrawController::class, 'destroyHotNumber'])
            ->name('three-digit.draws.hot-numbers.destroy');
        Route::get('/three-digit/results', [ThreeDigitDrawController::class, 'results'])
            ->name('three-digit.results');
        Route::get('/three-digit/draws/{draw}/result', [ThreeDigitDrawController::class, 'showResult'])
            ->name('three-digit.results.show');
        Route::patch('/three-digit/draws/{draw}/result', [ThreeDigitDrawController::class, 'updateResult'])
            ->name('three-digit.results.update');
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
        ])->name('rounds.result.update');
        Route::post('/settings/agents', [
            SettingsController::class,
            'storeAgent',
        ])->name('settings.agents.store');
        Route::patch('/settings/agents/{agent}', [
            SettingsController::class,
            'updateAgent',
        ])->name('settings.agents.update');
        Route::patch('/settings/agents/{agent}/three-digit-commission', [
            SettingsController::class,
            'updateAgentThreeDigitCommission',
        ])->name('settings.agents.three-digit-commission');
        Route::patch('/settings/rounds/{round}/agents/{agent}/amount-limit', [
            SettingsController::class,
            'updateAgentRoundAmountLimit',
        ])->name('settings.rounds.agents.amount-limit');
        Route::patch('/settings/rounds/{round}/agents/{agent}/amount-limit-3d', [
            SettingsController::class,
            'updateAgentRoundAmountLimit3d',
        ])->name('settings.rounds.agents.amount-limit-3d');
        Route::post('/settings/rounds/{round}/hot-numbers', [
            SettingsController::class,
            'storeHotNumbers',
        ])->name('settings.hot-numbers.store');
        Route::delete('/settings/rounds/{round}/hot-numbers/{hotNumber}', [
            SettingsController::class,
            'destroyHotNumber',
        ])->name('settings.hot-numbers.destroy');
        Route::post('/settings/rounds/{round}/hot-numbers-3d', [
            SettingsController::class,
            'storeThreeDigitHotNumbers',
        ])->name('settings.hot-numbers-3d.store');
        Route::delete('/settings/rounds/{round}/hot-numbers-3d/{hotNumber}', [
            SettingsController::class,
            'destroyThreeDigitHotNumber',
        ])->name('settings.hot-numbers-3d.destroy');
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
        ])->name('offline-reviews.update');
        Route::patch('/settings/rules/{codeRule}', [
            SettingsController::class,
            'updateConfiguredRule',
        ])->name('settings.rules.update');
        Route::post('/settings/rules/custom-number-lists', [
            SettingsController::class,
            'storeCustomNumberList',
        ])->name('settings.rules.custom.store');
        Route::delete('/settings/rules/custom-number-lists/{codeRule}', [
            SettingsController::class,
            'destroyCustomNumberList',
        ])->name('settings.rules.custom.destroy');
        Route::patch('/settings/rounds/{round}/reopen', [
            SettingsController::class,
            'reopenRound',
        ])->name('settings.rounds.reopen');
        Route::patch('/settings/rounds/{round}/close', [
            SettingsController::class,
            'closeRound',
        ])->name('settings.rounds.close');
        Route::patch('/settings/rounds/{round}/number-limits', [
            SettingsController::class,
            'updateRoundNumberLimits',
        ])->name('settings.rounds.number-limits');
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
        ])->name('users.status');
    });
});
