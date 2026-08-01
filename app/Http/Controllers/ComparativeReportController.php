<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComparativeReportRequest;
use App\Reports\ComparativeFinancialReport;
use App\Services\FinancialReportOutput;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComparativeReportController extends Controller
{
    public function index(ComparativeReportRequest $request, ComparativeFinancialReport $report): View
    {
        $filters = $request->validated();

        return view('comparative-reports.index', ['filters' => $filters] + $report->generate($filters));
    }

    public function print(ComparativeReportRequest $request, ComparativeFinancialReport $report, FinancialReportOutput $output): View
    {
        $filters = $request->validated();

        $comparison = $report->generate($filters);

        return view('comparative-reports.print', [
            'filters' => $filters,
            'reportMetadata' => $output->metadata($request->user(), 'Comparative '.$comparison['report_label'], $filters, 'Like-for-like posted general ledger'),
        ] + $comparison);
    }

    public function export(ComparativeReportRequest $request, ComparativeFinancialReport $report, FinancialReportOutput $output): StreamedResponse
    {
        $filters = $request->validated();
        $comparison = $report->generate($filters);
        $metadata = $output->metadata($request->user(), 'Comparative '.$comparison['report_label'], $filters, 'Like-for-like posted general ledger');

        return response()->streamDownload(function () use ($filters, $comparison, $metadata, $output): void {
            $stream = fopen('php://output', 'w');
            $output->writeCsvMetadata($stream, $metadata, $filters);
            fputcsv($stream, ['Account', $comparison['current_label'], $comparison['comparison_label'], 'Absolute Variance', 'Percentage Variance']);
            foreach ($comparison['sections'] as $section) {
                fputcsv($stream, [$section['label']]);
                foreach ($section['rows'] as $row) {
                    fputcsv($stream, [
                        $row['account']->code.' '.$row['account']->name,
                        $row['current_amount'],
                        $row['comparison_amount'],
                        $row['absolute_variance'],
                        $row['percentage_variance'],
                    ]);
                }
            }
            fclose($stream);
        }, $output->filename('comparative-'.$filters['report_type'], $filters), ['Content-Type' => 'text/csv']);
    }
}
