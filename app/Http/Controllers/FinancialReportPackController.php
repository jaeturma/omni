<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialReportPackRequest;
use App\Models\FiscalPeriod;
use App\Services\FinancialReportOutput;
use App\Services\FinancialReportPack;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportPackController extends Controller
{
    public function show(FinancialReportPackRequest $request, FinancialReportPack $reportPack, FinancialReportOutput $output): View
    {
        [$filters, $period, $pack] = $this->pack($request, $reportPack);

        return view('financial-report-pack.show', compact('filters', 'period', 'pack') + [
            'reportMetadata' => $output->metadata($request->user(), 'Management Financial Report Pack', $filters, 'Accrual basis · Posted general ledger'),
        ]);
    }

    public function download(FinancialReportPackRequest $request, FinancialReportPack $reportPack, FinancialReportOutput $output): StreamedResponse
    {
        [$filters, $period, $pack] = $this->pack($request, $reportPack);
        $metadata = $output->metadata($request->user(), 'Management Financial Report Pack', $filters, 'Accrual basis · Posted general ledger');

        return response()->streamDownload(function () use ($filters, $period, $pack, $metadata, $output): void {
            $stream = fopen('php://output', 'w');
            $output->writeCsvMetadata($stream, $metadata, $filters);
            fputcsv($stream, ['Fiscal Period', $period->fiscalYear->name.' · '.$period->name]);
            $this->writeStatement($stream, 'Income Statement', $pack['income_statement']['summary']);
            $this->writeStatement($stream, 'Balance Sheet', $pack['balance_sheet']['summary']);
            $this->writeStatement($stream, 'Cash-flow Statement', $pack['cash_flow_statement']['summary']);
            $this->writeStatement($stream, "Owner's Equity Statement", $pack['owner_equity_statement']['summary']);
            $this->writeStatement($stream, 'Trial Balance Summary', $pack['trial_balance_summary']['totals']);
            $this->writeStatement($stream, 'AR Aging Summary', $pack['ar_aging_summary']);
            $this->writeStatement($stream, 'AP Aging Summary', $pack['ap_aging_summary']);
            $this->writeStatement($stream, 'Cash-position Summary', ['cash_and_cash_equivalents' => $pack['cash_position_summary']['total'],
                'unreconciled' => $pack['cash_position_summary']['unreconciled']]);
            $this->writeStatement($stream, 'Inventory-valuation Summary', $pack['inventory_valuation_summary']);
            fclose($stream);
        }, $output->filename('management-financial-report-pack', $filters), ['Content-Type' => 'text/csv']);
    }

    /** @return array{array<string, mixed>, FiscalPeriod, array<string, mixed>} */
    private function pack(FinancialReportPackRequest $request, FinancialReportPack $reportPack): array
    {
        $filters = $request->validated();
        $period = FiscalPeriod::query()->with('fiscalYear')->findOrFail($filters['fiscal_period_id']);

        return [$filters, $period, $reportPack->generate($filters, $period)];
    }

    /** @param resource $stream
     * @param  array<string, mixed>  $values
     */
    private function writeStatement($stream, string $title, array $values): void
    {
        fputcsv($stream, []);
        fputcsv($stream, [$title]);
        fputcsv($stream, ['Line', 'Amount']);
        foreach ($values as $label => $amount) {
            if ($amount !== null && ! is_array($amount)) {
                fputcsv($stream, [str($label)->headline()->toString(), $amount]);
            }
        }
    }
}
