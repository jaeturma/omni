<?php

namespace App\Http\Requests;

class FinancialReportPackRequest extends FinancialDashboardRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('financial-report-pack.generate')) {
            return false;
        }

        return ! $this->routeIs('financial-report-pack.download')
            || (bool) $this->user()->can('financial-report-pack.download');
    }
}
