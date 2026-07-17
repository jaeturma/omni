<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DocumentSequenceController;
use App\Http\Controllers\FiscalPeriodController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\GovernmentDeductionController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductServiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReceivableReportController;
use App\Http\Controllers\RoleMatrixController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TaxProfileController;
use App\Http\Controllers\UnitOfMeasureController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/business-profile', [BusinessProfileController::class, 'edit'])->name('business-profile.edit');
    Route::put('/business-profile', [BusinessProfileController::class, 'update'])->name('business-profile.update');
    Route::get('/tax-profile', [TaxProfileController::class, 'edit'])->name('tax-profile.edit');
    Route::put('/tax-profile', [TaxProfileController::class, 'update'])->name('tax-profile.update');
    Route::post('/tax-profile/rates', [TaxProfileController::class, 'storeRate'])->name('tax-profile.rates.store');
    Route::get('/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
    Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
    Route::patch('/fiscal-periods/{fiscalPeriod}/status', [FiscalPeriodController::class, 'update'])->name('fiscal-periods.status.update');
    Route::get('/document-sequences', [DocumentSequenceController::class, 'index'])->name('document-sequences.index');
    Route::post('/document-sequences', [DocumentSequenceController::class, 'store'])->name('document-sequences.store');
    Route::put('/document-sequences/{documentSequence}', [DocumentSequenceController::class, 'update'])->name('document-sequences.update');
    Route::post('/document-sequences/{documentSequence}/issue', [DocumentSequenceController::class, 'issue'])->name('document-sequences.issue');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::get('/roles', RoleMatrixController::class)->name('roles.index');
    Route::get('/system-settings', [SystemSettingController::class, 'edit'])->name('system-settings.edit');
    Route::put('/system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');
    Route::resource('customers', CustomerController::class)->except('show');
    Route::resource('suppliers', SupplierController::class)->except('show');
    Route::resource('units-of-measure', UnitOfMeasureController::class)
        ->parameters(['units-of-measure' => 'unit_of_measure'])
        ->except('show');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('products-services', ProductServiceController::class)
        ->parameters(['products-services' => 'product_service'])
        ->except('show');
    Route::resource('brands', BrandController::class)->except('show');
    Route::resource('warehouses', WarehouseController::class)->except('show');
    Route::resource('payment-methods', PaymentMethodController::class)
        ->parameters(['payment-methods' => 'payment_method'])
        ->except('show');
    Route::resource('banks', BankController::class)->except('show');
    Route::patch('/quotations/{quotation}/status', [QuotationController::class, 'transition'])->name('quotations.transition');
    Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::resource('quotations', QuotationController::class);
    Route::post('/quotations/{quotation}/convert', [SalesOrderController::class, 'convert'])->name('quotations.convert');
    Route::patch('/sales-orders/{sales_order}/status', [SalesOrderController::class, 'transition'])->name('sales-orders.transition');
    Route::patch('/sales-orders/{sales_order}/fulfillment', [SalesOrderController::class, 'fulfill'])->name('sales-orders.fulfill');
    Route::resource('sales-orders', SalesOrderController::class);
    Route::patch('/deliveries/{delivery}/status', [DeliveryController::class, 'transition'])->name('deliveries.transition');
    Route::get('/deliveries/{delivery}/print', [DeliveryController::class, 'print'])->name('deliveries.print');
    Route::resource('deliveries', DeliveryController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('/sales-invoices/{sales_invoice}/status', [SalesInvoiceController::class, 'transition'])->name('sales-invoices.transition');
    Route::get('/sales-invoices/{sales_invoice}/print', [SalesInvoiceController::class, 'print'])->name('sales-invoices.print');
    Route::resource('sales-invoices', SalesInvoiceController::class)->except('destroy');
    Route::patch('/customer-payments/{customer_payment}/status', [CustomerPaymentController::class, 'transition'])->name('customer-payments.transition');
    Route::post('/customer-payments/{customer_payment}/allocations', [CustomerPaymentController::class, 'allocate'])->name('customer-payments.allocate');
    Route::resource('customer-payments', CustomerPaymentController::class)->except('destroy');
    Route::get('/receivables', [ReceivableReportController::class, 'index'])->name('receivables.index');
    Route::get('/receivables/summary', [ReceivableReportController::class, 'summary'])->name('receivables.summary');
    Route::get('/receivables/unapplied', [ReceivableReportController::class, 'unapplied'])->name('receivables.unapplied');
    Route::get('/receivables/print', [ReceivableReportController::class, 'print'])->name('receivables.print');
    Route::get('/receivables/export', [ReceivableReportController::class, 'export'])->name('receivables.export');
    Route::get('/customers/{customer}/statement', [ReceivableReportController::class, 'statement'])->name('customer-statements.show');
    Route::get('/customers/{customer}/statement/print', [ReceivableReportController::class, 'statementPrint'])->name('customer-statements.print');
    Route::patch('/government-deductions/{government_deduction}/status', [GovernmentDeductionController::class, 'transition'])->name('government-deductions.transition');
    Route::resource('government-deductions', GovernmentDeductionController::class)->except('destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
