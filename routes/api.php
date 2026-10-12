<?php

use App\Http\Controllers\Api\V1\AgentAuthController;
use App\Http\Controllers\Api\V1\AgentSaleUploadController;
use App\Http\Controllers\Api\V1\AgentSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AgentAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware('agent.token')->group(function (): void {
        Route::get('me', [AgentAuthController::class, 'me'])->name('me');
        Route::post('auth/logout', [AgentAuthController::class, 'logout'])->name('auth.logout');

        Route::post('sessions/open', [AgentSessionController::class, 'open'])
            ->middleware('throttle:30,1')
            ->name('sessions.open');
        Route::get('sessions/{agentSession}/state', [AgentSessionController::class, 'state'])
            ->name('sessions.state');

        Route::post('sales/upload', [AgentSaleUploadController::class, 'upload'])
            ->middleware('throttle:120,1')
            ->name('sales.upload');
    });
});
