<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFiscalPeriodStatusRequest;
use App\Models\FiscalPeriod;
use Illuminate\Http\RedirectResponse;

class FiscalPeriodController extends Controller
{
    public function update(UpdateFiscalPeriodStatusRequest $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        $status = $request->validated('status');
        $fiscalPeriod->update($status === 'closed'
            ? ['status' => 'closed', 'closed_at' => now(), 'closed_by' => $request->user()->id]
            : ['status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id]);

        return back()->with('success', "Fiscal period {$status}.");
    }
}
