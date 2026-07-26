<?php

namespace App\Providers;

use App\Models\BusinessProfile;
use App\Models\CashDisbursement;
use App\Models\CashReceipt;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\FundTransfer;
use App\Models\GovernmentDeduction;
use App\Models\InventoryAdjustment;
use App\Models\InventoryTransfer;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashVoucher;
use App\Models\PhysicalCount;
use App\Models\Quotation;
use App\Models\ReceivingRecord;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Observers\AutomaticSourcePostingObserver;
use App\Services\SystemSettings;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            SalesInvoice::class, CustomerPayment::class, SupplierInvoice::class, SupplierPayment::class,
            Expense::class, CashReceipt::class, CashDisbursement::class, FundTransfer::class,
            PettyCashVoucher::class, PettyCashReplenishment::class, Delivery::class, ReceivingRecord::class,
            InventoryAdjustment::class, InventoryTransfer::class, PhysicalCount::class,
        ] as $sourceModel) {
            $sourceModel::observe(AutomaticSourcePostingObserver::class);
        }

        Relation::morphMap([
            'quotation' => Quotation::class,
            'sales_order' => SalesOrder::class,
            'delivery' => Delivery::class,
            'sales_invoice' => SalesInvoice::class,
            'customer_payment' => CustomerPayment::class,
            'government_deduction' => GovernmentDeduction::class,
        ]);

        View::composer('components.app-layout', function ($view): void {
            $view->with('businessDisplayName', BusinessProfile::active()->value('trade_name'));
            $view->with('applicationDisplayName', app(SystemSettings::class)->get('application_display_name'));
        });
    }
}
