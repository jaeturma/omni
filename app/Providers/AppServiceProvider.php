<?php

namespace App\Providers;

use App\Models\BusinessProfile;
use App\Services\SystemSettings;
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
        View::composer('components.app-layout', function ($view): void {
            $view->with('businessDisplayName', BusinessProfile::active()->value('trade_name'));
            $view->with('applicationDisplayName', app(SystemSettings::class)->get('application_display_name'));
        });
    }
}
