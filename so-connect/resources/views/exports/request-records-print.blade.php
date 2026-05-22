<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Records Export</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1f2937; background: #fff; padding: 24px; }
        h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 24px; }
        .section { margin-bottom: 32px; page-break-inside: avoid; }
        .section-header { padding: 10px 14px; border-radius: 6px 6px 0 0; color: #fff; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .section-header.accepted { background: #15803d; }
        .section-header.pending  { background: #b45309; }
        .section-header.rejected { background: #dc2626; }
        .count { font-size: 11px; font-weight: 400; opacity: 0.85; margin-left: auto; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 6px 6px; overflow: hidden; }
        thead { background: #f3f4f6; }
        th { padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
        td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .empty-row td { font-style: italic; color: #9ca3af; }
        .print-btn { display: inline-block; background: #1d4ed8; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
    <h1>Request Records Export</h1>
    <p class="meta">Generated: {{ now()->format('F j, Y \a\t g:i A') }}</p>

    @php
        $sections = [
            'accepted' => ['label' => 'Accepted Status', 'class' => 'accepted', 'rows' => $accepted],
            'pending'  => ['label' => 'Pending Status',  'class' => 'pending',  'rows' => $pending],
            'rejected' => ['label' => 'Rejected Status', 'class' => 'rejected', 'rows' => $rejected],
        ];
    @endphp

    @foreach($sections as $section)
        <div class="section">
            <div class="section-header {{ $section['class'] }}">
                {{ $section['label'] }}
                <span class="count">{{ count($section['rows']) }} record(s)</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Org ID</th>
                        <th>Request Time</th>
                        <th>Request Type</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            <td>{{ $row['user_id'] ?? '—' }}</td>
                            <td>{{ $row['org_id'] ?? '—' }}</td>
                            <td>{{ $row['request_time'] }}</td>
                            <td>{{ $row['request_type'] }}</td>
                        </tr>
                    @empty
                        <tr class="empty-row">
                            <td colspan="4">No records in this category.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
