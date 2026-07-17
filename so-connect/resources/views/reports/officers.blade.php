<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Organization Officers</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; font-weight: 700; margin: 0 0 2px; }
        .meta { font-size: 10px; color: #6b7280; margin: 0 0 16px; }
        .org { margin-bottom: 18px; page-break-inside: avoid; }
        .org-name { font-size: 13px; font-weight: 700; color: #111827; margin: 0 0 6px; border-bottom: 2px solid #1d4ed8; padding-bottom: 3px; }
        .org-name small { font-weight: 400; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #d1d5db; }
        thead { background: #f3f4f6; }
        th { padding: 6px 8px; text-align: left; font-size: 10px; font-weight: 700; color: #374151; border-bottom: 1px solid #d1d5db; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .empty { font-style: italic; color: #9ca3af; padding: 8px; }
        .no-orgs { font-style: italic; color: #9ca3af; }
    </style>
</head>

<body>
    <h1>Organization Officers</h1>
    <p class="meta">Generated {{ $generatedAt->format('F j, Y \\a\\t g:i A') }}</p>

    @forelse($sections as $section)
        <div class="org">
            <p class="org-name">
                {{ $section['name'] }}
                @if (!empty($section['initials']))
                    <small>({{ $section['initials'] }})</small>
                @endif
            </p>

            @if (count($section['officers']) > 0)
                <table>
                    <thead>
                        <tr>
                            <th style="width: 28%;">Name</th>
                            <th style="width: 28%;">Email</th>
                            <th style="width: 14%;">Role</th>
                            <th style="width: 18%;">Position</th>
                            <th style="width: 12%;">Member Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($section['officers'] as $officer)
                            <tr>
                                <td>{{ $officer['name'] }}</td>
                                <td>{{ $officer['email'] }}</td>
                                <td>{{ $officer['role'] }}</td>
                                <td>{{ $officer['position'] }}</td>
                                <td>{{ $officer['member_since'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty">No officers recorded for this organization.</p>
            @endif
        </div>
    @empty
        <p class="no-orgs">No organizations are registered.</p>
    @endforelse
</body>

</html>
