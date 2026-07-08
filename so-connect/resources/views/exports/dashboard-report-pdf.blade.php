<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Report</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>Executive Dashboard Report</h1>
    <p class="muted">Generated: {{ $generatedAt }}</p>

    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        @foreach ($report['totals'] as $label => $value)
            <tr>
                <td>{{ str_replace('_', ' ', ucfirst($label)) }}</td>
                <td>{{ $value }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
