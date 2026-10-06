<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizational Data Export</title>
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

        .org-block {
            margin-bottom: 32px;
            page-break-inside: avoid;
        }

        .org-header {
            background: #1d4ed8;
            color: #fff;
            padding: 10px 14px;
            border-radius: 6px 6px 0 0;
        }

        .org-header h2 {
            font-size: 14px;
            font-weight: 700;
        }

        .org-header p {
            font-size: 11px;
            opacity: 0.85;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }

        .chart-section {
            margin-top: 12px;
            padding: 12px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 6px 6px;
        }

        .chart-section h4 {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .chart-tree {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chart-node {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 11px;
            text-align: center;
            min-width: 100px;
        }

        .chart-node .role {
            font-weight: 700;
            color: #1d4ed8;
        }

        .chart-node .name {
            color: #374151;
            margin-top: 2px;
        }

        .empty {
            color: #9ca3af;
            font-style: italic;
            font-size: 11px;
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
    <h1>Organizational Data Export</h1>
    <p class="meta">Generated: {{ now()->format('F j, Y \a\t g:i A') }}</p>

    @forelse ($organizations as $org)
        <div class="org-block">
            <div class="org-header">
                <h2>{{ $org->org_name }}@if ($org->org_initials)
                        ({{ $org->org_initials }})
                    @endif
                </h2>
                <p>Organization ID: {{ $org->organization_id }} &bull; Type: {{ $org->organization_type ?? '—' }}</p>
            </div>

            @if ($org->officersOfThisOrganization->isNotEmpty())
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Position</th>
                            <th>Member Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($org->officersOfThisOrganization as $i => $officer)
                            @php
                                $officerUser = $officer->getRelations()['user'] ?? null;
                                $officerProfile = $officerUser?->getRelations()['profile'] ?? null;
                                $officerName = trim(
                                    ($officerProfile?->first_name ?? '') . ' ' . ($officerProfile?->last_name ?? ''),
                                );
                                $officerPosition = $officer->position ?: $officerProfile?->position ?? null;
                                $officerPosition = is_string($officerPosition)
                                    ? trim($officerPosition)
                                    : $officerPosition;
                                $officerPosition = $officerPosition !== '' ? $officerPosition : null;
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $officer->getAttributes()['user'] ?? '—' }}</td>
                                <td>{{ $officerName ?: '—' }}</td>
                                <td><span class="badge">{{ $officer->role }}</span></td>
                                <td>{{ $officerPosition ?? '—' }}</td>
                                <td>{{ optional($officer->member_since)->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="chart-section">
                    <h4>Officer Composition Chart</h4>
                    <div class="chart-tree">
                        @foreach ($org->officersOfThisOrganization as $officer)
                            @php
                                $chartUser = $officer->getRelations()['user'] ?? null;
                                $chartProfile = $chartUser?->getRelations()['profile'] ?? null;
                                $chartName = trim(
                                    ($chartProfile?->first_name ?? '') . ' ' . ($chartProfile?->last_name ?? ''),
                                );
                                $chartPosition = $officer->position ?: $chartProfile?->position ?? null;
                                $chartPosition = is_string($chartPosition) ? trim($chartPosition) : $chartPosition;
                                $chartPosition = $chartPosition !== '' ? $chartPosition : null;
                            @endphp
                            <div class="chart-node">
                                <div class="role">{{ $officer->role }}</div>
                                @if ($chartPosition)
                                    <div class="name" style="font-style:italic;font-size:10px;">{{ $chartPosition }}
                                    </div>
                                @endif
                                <div class="name">
                                    {{ $chartName ?: 'User #' . ($officer->getAttributes()['user'] ?? '?') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div style="padding:12px 14px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;">
                    <span class="empty">No officers registered for this organization.</span>
                </div>
            @endif
        </div>
        @empty
            <p class="empty">No organizations found.</p>
        @endforelse
    </body>

    </html>
