<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Administrator Action Logs Export</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; background: #fff; padding: 24px; }
        h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #6b7280; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        thead { background: #f3f4f6; }
        th { padding: 6px 8px; text-align: left; font-size: 10px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
        td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .badge { font-weight: 600; }
        .details { color: #6b7280; word-break: break-all; }
    </style>
</head>

<body>
    <h1>Administrator Action Logs</h1>
    <p class="meta">
        Generated {{ now()->toDateTimeString() }}
        @if (!empty($filters['from']) || !empty($filters['to']))
            · Range: {{ $filters['from'] ?? '…' }} – {{ $filters['to'] ?? '…' }}
        @endif
        @if (!empty($filters['category']))
            · Category: {{ $categories[$filters['category']] ?? $filters['category'] }}
        @endif
        @if (!empty($filters['user']))
            · User: {{ $filters['user'] }}
        @endif
        · {{ count($logs) }} {{ Str::plural('record', count($logs)) }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Category</th>
                <th>Action</th>
                <th>Description</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log['created_at'] }}</td>
                    <td>{{ $log['name'] }}<br><span class="details">{{ $log['user_email'] }}</span></td>
                    <td class="badge">{{ $log['category_label'] }}</td>
                    <td>{{ $log['action'] }}</td>
                    <td>{{ $log['description'] }}</td>
                    <td class="details">{{ $log['meta'] !== [] ? json_encode($log['meta']) : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No actions match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
