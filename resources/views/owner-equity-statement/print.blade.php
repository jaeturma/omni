<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Statement of Changes in Owner's Equity</title>@vite(['resources/css/app.css'])</head>
<body class="bg-white p-8 text-slate-950">
    <x-financial-report-metadata :metadata="$reportMetadata" />
    <h1 class="text-2xl font-bold">Statement of Changes in Owner's Equity</h1>
    <p class="text-sm">{{ $filters['start_date'] }} to {{ $filters['end_date'] }} · As of {{ $filters['as_of'] }} · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }}</p>
    <p class="mt-2 text-sm font-semibold">{{ $final_ready ? 'Reconciled — final ready' : 'Not final ready' }} · Difference PHP {{ $display_summary['reconciliation_difference'] }}</p>
    <table class="mt-5 min-w-full text-sm">
        <thead><tr><th class="text-left">Equity Activity</th><th class="text-right">PHP</th></tr></thead>
        <tbody>
            @foreach($rows as $row)<tr class="{{ $row['key'] === 'closing_equity' ? 'border-t font-bold' : '' }}"><td>{{ $row['label'] }}</td><td class="text-right">{{ $row['display_amount'] }}</td></tr>@endforeach
            <tr class="border-t font-semibold"><td>Balance-sheet Closing Equity</td><td class="text-right">{{ $display_summary['balance_sheet_closing_equity'] }}</td></tr>
        </tbody>
    </table>
</body>
</html>
