<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Balance Sheet</title>@vite(['resources/css/app.css'])</head>
<body class="bg-white p-8 text-slate-950">
    <x-financial-report-metadata :metadata="$reportMetadata" />
    <h1 class="text-2xl font-bold">Balance Sheet</h1>
    <p class="text-sm">As of {{ $filters['as_of'] }} · Fiscal-year start {{ $filters['fiscal_year_start'] }} · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }} · Zero balances {{ $filters['show_zero_balances'] ? 'shown' : 'hidden' }}</p>
    <p class="mt-2 text-sm font-semibold">{{ $balanced ? 'Balanced — final ready' : 'Out of balance — not final ready' }} · Difference PHP {{ $display_summary['difference'] }}</p>
    <table class="mt-5 min-w-full text-sm">
        <thead><tr><th class="text-left">Account</th><th class="text-right">PHP</th></tr></thead>
        <tbody>
            @foreach($sections as $section)
                <tr class="border-t font-semibold"><td class="py-2">{{ $section['label'] }}</td><td></td></tr>
                @foreach($section['rows'] as $row)
                    <tr><td>{{ str_repeat('— ', $row['depth']) }}{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="text-right">{{ $row['display_amount'] }}</td></tr>
                @endforeach
                <tr class="font-semibold"><td>Total {{ $section['label'] }}</td><td class="text-right">{{ $section['display_total'] }}</td></tr>
                @if($section['key'] === 'non_current_assets')
                    <tr class="border-t font-bold"><td>Total Assets</td><td class="text-right">{{ $display_summary['total_assets'] }}</td></tr>
                @elseif($section['key'] === 'non_current_liabilities')
                    <tr class="border-t font-bold"><td>Total Liabilities</td><td class="text-right">{{ $display_summary['total_liabilities'] }}</td></tr>
                @elseif($section['key'] === 'current_year_earnings')
                    <tr class="border-t font-bold"><td>Total Owner's Equity</td><td class="text-right">{{ $display_summary['total_equity'] }}</td></tr>
                    <tr class="border-t font-bold"><td>Total Liabilities and Owner's Equity</td><td class="text-right">{{ $display_summary['liabilities_and_equity'] }}</td></tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
