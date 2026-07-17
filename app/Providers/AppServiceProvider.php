<?php

namespace App\Providers;

use App\Models\BusinessProfile;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\GovernmentDeduction;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
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
