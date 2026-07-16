<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Officers Export</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1f2937; background: #fff; padding: 24px; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; }
        thead { background: #f3f4f6; }
        th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
        td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .empty-row td { font-style: italic; color: #9ca3af; }
        .print-btn { display: inline-block; background: #1d4ed8; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        @media print { .print-btn { display: none; } body { padding: 0; } }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    <h1>Organization Officers Export</h1>
    <p class="meta">Generated: {{ now()->format('F j, Y \\a\\t g:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th>Organization</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Position</th>
                <th>Member Since</th>
            </tr>
        </thead>
        <tbody>
            @forelse($officers as $officer)
                <tr>
                    <td>{{ $officer['organization'] }}</td>
                    <td>{{ $officer['name'] }}</td>
                    <td>{{ $officer['email'] }}</td>
                    <td>{{ $officer['role'] }}</td>
                    <td>{{ $officer['position'] }}</td>
                    <td>{{ $officer['member_since'] }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="6">No officers recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
