<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Comparative {{ $report_label }}</title>@vite(['resources/css/app.css'])</head>
<body class="bg-white p-8 text-slate-950">
    <x-financial-report-metadata :metadata="$reportMetadata" />
    <h1 class="text-2xl font-bold">Comparative {{ $report_label }}</h1>
    <p class="text-sm">{{ $current_label }} compared with {{ $comparison_label }}</p>
    <table class="mt-5 min-w-full text-sm">
        <thead><tr><th class="text-left">Account</th><th class="text-right">Current</th><th class="text-right">Comparison</th><th class="text-right">Variance</th><th class="text-right">Variance %</th></tr></thead>
        <tbody>
            @foreach($sections as $section)
                <tr class="border-t font-semibold"><td colspan="5">{{ $section['label'] }}</td></tr>
                @foreach($section['rows'] as $row)<tr><td>{{ $row['account']->code }} — {{ $row['account']->name }}</td><td class="text-right">{{ $row['display_current_amount'] }}</td><td class="text-right">{{ $row['display_comparison_amount'] }}</td><td class="text-right">{{ $row['display_absolute_variance'] }}</td><td class="text-right">{{ $row['display_percentage_variance'] ?? 'N/A' }}</td></tr>@endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
