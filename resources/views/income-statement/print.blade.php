<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Income Statement</title>@vite(['resources/css/app.css'])</head>
<body class="bg-white p-8 text-slate-950">
    <x-financial-report-metadata :metadata="$reportMetadata" />
    <h1 class="text-2xl font-bold">Income Statement</h1>
    <p class="text-sm">{{ $filters['start_date'] }} to {{ $filters['end_date'] }} · As of {{ $filters['as_of'] }} · Fiscal period {{ $filters['fiscal_period_id'] ?? 'Custom' }} · {{ str($filters['report_view'])->headline() }} · Zero balances {{ $filters['show_zero_balances'] ? 'shown' : 'hidden' }}</p>
    <table class="mt-5 min-w-full text-sm">
        <thead><tr><th class="text-left">Account</th><th class="text-right">PHP</th></tr></thead>
        <tbody>
            @foreach($sections as $section)
                @continue($section['key'] === 'income_tax' && ! $has_income_tax)
                <tr class="border-t font-semibold"><td class="py-2">{{ $section['label'] }}</td><td></td></tr>
                @foreach($section['rows'] as $row)
                    <tr><td>{{ str_repeat('— ', $row['depth']) }}{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="text-right">{{ $row['display_amount'] }}</td></tr>
                @endforeach
                <tr class="font-semibold"><td>Total {{ $section['label'] }}</td><td class="text-right">{{ $section['display_total'] }}</td></tr>
                @if($section['key'] === 'contra_revenue')
                    <tr class="border-t font-bold"><td>Net Sales</td><td class="text-right">{{ $display_summary['net_sales'] }}</td></tr>
                @elseif($section['key'] === 'cost_of_sales')
                    <tr class="border-t font-bold"><td>Gross Profit</td><td class="text-right">{{ $display_summary['gross_profit'] }}</td></tr>
                @elseif($section['key'] === 'operating_expenses')
                    <tr class="border-t font-bold"><td>Operating Income</td><td class="text-right">{{ $display_summary['operating_income'] }}</td></tr>
                @elseif($section['key'] === 'other_expenses')
                    <tr class="border-t font-bold"><td>Net Income Before Tax</td><td class="text-right">{{ $display_summary['net_income_before_tax'] }}</td></tr>
                @elseif($section['key'] === 'income_tax')
                    <tr class="border-t font-bold"><td>Net Income After Tax</td><td class="text-right">{{ $display_summary['net_income_after_tax'] }}</td></tr>
                @endif
            @endforeach
            @unless($has_income_tax)<tr class="border-t font-bold"><td>Net Income</td><td class="text-right">{{ $display_summary['net_income_after_tax'] }}</td></tr>@endunless
        </tbody>
    </table>
</body>
</html>
