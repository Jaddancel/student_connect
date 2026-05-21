<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scoring Audit Log</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1f2937; background: #fff; padding: 24px; }
        h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #1d4ed8; }
        th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; color: #fff; }
        td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; color: #374151; font-size: 11px; }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) { background: #f9fafb; }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 999px; font-size: 10px; font-weight: 600; background: #fef3c7; color: #92400e; margin: 1px 2px; }
        .none { color: #9ca3af; font-style: italic; }
        .score { font-weight: 700; }
        .print-btn { display: inline-block; background: #1d4ed8; color: #fff; border: none; padding: 9px 18px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    <h1>Scoring Audit Log</h1>
    <p class="meta">
        Generated: {{ now()->format('F j, Y \a\t g:i A') }}
        @if ($semesterFilter)
            &bull; Semester: {{ $semesters->firstWhere('semester_id', $semesterFilter)?->name }}
        @endif
        &bull; {{ $rows->count() }} record(s)
    </p>

    <table>
        <thead>
            <tr>
                <th>Organization</th>
                <th>Semester</th>
                <th>Score / 550</th>
                <th>Scored By</th>
                <th>Scored At</th>
                <th>Manual Fields Used</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['org_name'] }}</td>
                    <td>{{ $row['semester'] }}</td>
                    <td class="score">{{ number_format((float) $row['total'], 0) }}</td>
                    <td>{{ $row['scorer_name'] }}</td>
                    <td>{{ $row['scored_at'] ? \Carbon\Carbon::parse($row['scored_at'])->format('M d, Y') : '—' }}</td>
                    <td>
                        @if (count($row['manual_used']) > 0)
                            @foreach ($row['manual_used'] as $mf)
                                <span class="badge">{{ $mf }}</span>
                            @endforeach
                        @else
                            <span class="none">None</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:#9ca3af; padding:20px;">No records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
