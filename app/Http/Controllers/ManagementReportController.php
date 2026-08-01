<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManagementReportRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Reports\ManagementProfitabilityReport;
use App\Services\FinancialReportOutput;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagementReportController extends Controller
{
    public function index(ManagementReportRequest $request, ManagementProfitabilityReport $report): View
    {
        return $this->view('management-reports.index', $request, $report);
    }

    public function print(ManagementReportRequest $request, ManagementProfitabilityReport $report, FinancialReportOutput $output): View
    {
        return $this->view('management-reports.print', $request, $report, $output);
    }

    public function export(ManagementReportRequest $request, ManagementProfitabilityReport $report, FinancialReportOutput $output): StreamedResponse
    {
        $filters = $request->validated();
        $data = $report->generate($filters, $this->canViewCosts($request));
        $metadata = $output->metadata($request->user(), $data['title'], $filters, 'Posted ledger with disclosed operational dimensions');

        return response()->streamDownload(function () use ($data, $filters, $metadata, $output): void {
            $stream = fopen('php://output', 'w');
            $output->writeCsvMetadata($stream, $metadata, $filters);
            fputcsv($stream, ['Source', $data['source_note']]);
            fputcsv($stream, []);
            foreach ($data['sections'] as $section) {
                fputcsv($stream, [$section['label']]);
                fputcsv($stream, $section['columns']);
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, array_values($row));
                }
                fputcsv($stream, []);
            }
            fclose($stream);
        }, $output->filename('management-'.$filters['report'], $filters), ['Content-Type' => 'text/csv']);
    }

    private function view(string $view, ManagementReportRequest $request, ManagementProfitabilityReport $report, ?FinancialReportOutput $output = null): View
    {
        $filters = $request->validated();
        $canViewCosts = $this->canViewCosts($request);

        $data = $report->generate($filters, $canViewCosts);

        return view($view, [
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'canViewCosts' => $canViewCosts,
            'reportMetadata' => $output?->metadata($request->user(), $data['title'], $filters, 'Posted ledger with disclosed operational dimensions'),
        ] + $data);
    }

    private function canViewCosts(ManagementReportRequest $request): bool
    {
        return (bool) ($request->user()->can('profitability.view')
            && $request->user()->can('margin.view')
            && $request->user()->can('cost-data.view'));
    }
}
