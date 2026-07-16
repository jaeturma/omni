<?php

namespace App\Http\Controllers;

use App\Actions\CreateFiscalYear;
use App\Http\Requests\StoreFiscalYearRequest;
use App\Models\BusinessProfile;
use App\Models\FiscalYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', FiscalYear::class);
        $years = FiscalYear::query()->with('periods')->latest('starts_on')->paginate(10);

        return view('fiscal-years.index', ['years' => $years]);
    }

    public function store(StoreFiscalYearRequest $request, CreateFiscalYear $create): RedirectResponse
    {
        $create->handle(BusinessProfile::active()->firstOrFail(), $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Fiscal year and monthly periods created.');
    }
}
