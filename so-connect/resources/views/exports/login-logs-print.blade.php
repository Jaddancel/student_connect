<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Activity Export</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #1f2937;
            background: #fff;
            padding: 24px;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .meta {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e5e7eb;
        }

        thead {
            background: #f3f4f6;
        }

        th {
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 7px 10px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge.login {
            background: #dcfce7;
            color: #166534;
        }

        .badge.logout {
            background: #e5e7eb;
            color: #374151;
        }

        .empty-row td {
            font-style: italic;
            color: #9ca3af;
        }

        .print-btn {
            display: inline-block;
            background: #1d4ed8;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        @media print {
            .print-btn {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    <h1>Login / Logout Activity Export</h1>
    <p class="meta">Generated: {{ now()->format('F j, Y \\a\\t g:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th>User Email</th>
                <th>Name</th>
                <th>Interaction</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log['user_email'] ?? '-' }}</td>
                    <td>{{ $log['name'] ?? '-' }}</td>
                    <td>
                        <span class="badge {{ $log['interaction'] === 'LOGIN' ? 'login' : 'logout' }}">
                            {{ $log['interaction'] }}
                        </span>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($log['logged_at'])->format('d M Y h:i A') }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="4">No activity recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
