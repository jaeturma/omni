<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cash Flow Statement</title>@vite(['resources/css/app.css'])</head>
<body class="bg-white p-8 text-slate-950">
    <h1 class="text-2xl font-bold">Cash Flow Statement — Indirect Method</h1>
    <p class="text-sm">{{ $filters['start_date'] }} to {{ $filters['end_date'] }} · As of {{ $filters['as_of'] }} · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }}</p>
    <p class="mt-2 text-sm font-semibold">{{ $final_ready ? 'Reconciled — final ready' : 'Not final ready' }} · Difference PHP {{ $display_summary['reconciliation_difference'] }}</p>
    @if($has_unclassified)<p class="mt-2 text-sm font-semibold">Material unclassified activity is shown and requires mapping.</p>@endif
    <table class="mt-5 min-w-full text-sm">
        <thead><tr><th class="text-left">Activity</th><th class="text-right">PHP</th></tr></thead>
        <tbody>
            @foreach($sections as $section)
                <tr class="border-t font-semibold"><td class="py-2">{{ $section['label'] }}</td><td></td></tr>
                @foreach($section['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td class="text-right">{{ $row['display_amount'] }}</td></tr>@endforeach
                <tr class="font-semibold"><td>Net cash from {{ str($section['label'])->lower() }}</td><td class="text-right">{{ $section['display_total'] }}</td></tr>
            @endforeach
            <tr class="border-t font-semibold"><td>Beginning cash</td><td class="text-right">{{ $display_summary['beginning_cash'] }}</td></tr>
            <tr><td>Net change</td><td class="text-right">{{ $display_summary['net_change'] }}</td></tr>
            <tr class="font-bold"><td>Ending cash</td><td class="text-right">{{ $display_summary['ending_cash'] }}</td></tr>
            <tr><td>Balance-sheet cash</td><td class="text-right">{{ $display_summary['balance_sheet_cash'] }}</td></tr>
        </tbody>
    </table>
</body>
</html>
