<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialDashboardRequest;
use App\Models\FiscalPeriod;
use App\Services\FinancialDashboard;
use Illuminate\View\View;

class FinancialDashboardController extends Controller
{
    public function __invoke(FinancialDashboardRequest $request, FinancialDashboard $dashboard): View
    {
        $filters = $request->validated();
        $period = FiscalPeriod::query()->with('fiscalYear:id,name,starts_on')->findOrFail($filters['fiscal_period_id']);

        return view('dashboard', [
            'filters' => $filters,
            'period' => $period,
            'periods' => FiscalPeriod::query()->with('fiscalYear:id,name')->latest('starts_on')->get(),
        ] + $dashboard->generate($filters, $period));
    }
}
