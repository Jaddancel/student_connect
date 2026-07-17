<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Registered Organizations</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; font-weight: 700; margin: 0 0 2px; }
        .meta { font-size: 10px; color: #6b7280; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #d1d5db; }
        thead { background: #f3f4f6; }
        th { padding: 7px 8px; text-align: left; font-size: 10px; font-weight: 700; color: #374151; border-bottom: 1px solid #d1d5db; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .num { text-align: center; }
        .empty { font-style: italic; color: #9ca3af; text-align: center; padding: 16px; }
        .summary { margin-top: 14px; font-size: 10px; color: #6b7280; }
    </style>
</head>

<body>
    <h1>Registered Organizations</h1>
    <p class="meta">Generated {{ $generatedAt->format('F j, Y \\a\\t g:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;" class="num">#</th>
                <th style="width: 40%;">Name</th>
                <th style="width: 14%;">Initials</th>
                <th style="width: 24%;">Type</th>
                <th style="width: 14%;" class="num">Officers</th>
            </tr>
        </thead>
        <tbody>
            @forelse($organizations as $org)
                <tr>
                    <td class="num">{{ $org['organization_id'] }}</td>
                    <td>{{ $org['name'] }}</td>
                    <td>{{ $org['initials'] ?: '—' }}</td>
                    <td>{{ $org['type'] }}</td>
                    <td class="num">{{ $org['officer_count'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">No organizations are registered.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (count($organizations) > 0)
        <p class="summary">Total registered organizations: {{ count($organizations) }}</p>
    @endif
</body>

</html>
