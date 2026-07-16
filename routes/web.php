<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\FiscalPeriodController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\TaxProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/business-profile', [BusinessProfileController::class, 'edit'])->name('business-profile.edit');
    Route::put('/business-profile', [BusinessProfileController::class, 'update'])->name('business-profile.update');
    Route::get('/tax-profile', [TaxProfileController::class, 'edit'])->name('tax-profile.edit');
    Route::put('/tax-profile', [TaxProfileController::class, 'update'])->name('tax-profile.update');
    Route::post('/tax-profile/rates', [TaxProfileController::class, 'storeRate'])->name('tax-profile.rates.store');
    Route::get('/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
    Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
    Route::patch('/fiscal-periods/{fiscalPeriod}/status', [FiscalPeriodController::class, 'update'])->name('fiscal-periods.status.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
