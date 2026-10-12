<?php

use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\ScreenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('metrics/current', [MetricsController::class, 'current'])->name('metrics.current');
        Route::get('metrics/history', [MetricsController::class, 'history'])->name('metrics.history');
        Route::get('screen/shot', [ScreenController::class, 'shot'])->name('screen.shot');
    });
});
