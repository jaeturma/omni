<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body>
    <x-financial-report-metadata :metadata="$reportMetadata" />
    <h1>{{ $title }}</h1>
    <p>{{ $filters['start_date'] }} to {{ $filters['end_date'] }}</p>
    <p><strong>Data source:</strong> {{ $source_note }}</p>
    @foreach($sections as $section)
        <h2>{{ $section['label'] }}</h2>
        <table border="1" cellspacing="0" cellpadding="5">
            <thead><tr>@foreach($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
            <tbody>@foreach($section['rows'] as $row)<tr>@foreach($row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
    @endforeach
</body>
</html>
