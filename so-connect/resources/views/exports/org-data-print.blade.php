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

        /* Org-chart diagram (two levels: President -> all other officers) */
        .org-chart {
            text-align: center;
            padding-top: 4px;
        }

        .oc-root {
            text-align: center;
        }

        /* vertical connector from the President down to the bus line */
        .oc-trunk {
            width: 1px;
            height: 18px;
            background: #94a3b8;
            margin: 0 auto;
        }

        .oc-children {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .oc-child {
            position: relative;
            padding: 18px 10px 0 10px;
        }

        /* vertical line up from each child to the horizontal bus */
        .oc-child::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            width: 1px;
            height: 18px;
            background: #94a3b8;
        }

        /* horizontal bus line; segments join to form one continuous line */
        .oc-child::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: #94a3b8;
        }

        .oc-child:first-child::after {
            left: 50%;
        }

        .oc-child:last-child::after {
            right: 50%;
        }

        .oc-child:only-child::after {
            display: none;
        }

        .oc-node {
            display: inline-block;
            width: 150px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
            text-align: center;
            vertical-align: top;
        }

        .oc-node-role {
            padding: 5px 8px;
            font-size: 10px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            background: #3b82f6;
        }

        .oc-root .oc-node-role {
            background: #1e3a8a;
        }

        .oc-node-body {
            padding: 6px 8px;
        }

        .oc-node-name {
            font-size: 11px;
            font-weight: 700;
            color: #1f2937;
        }

        .oc-node-tenure {
            font-size: 10px;
            color: #6b7280;
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
                    @php
                        // Rank purely for ordering: lower = more senior. President/Chair/Head = root (0).
                        $rankFn = function ($role) {
                            $r = strtolower(trim((string) $role));
                            if ($r === '') {
                                return 9;
                            }
                            if (
                                (str_contains($r, 'president') || str_contains($r, 'chair') || str_contains($r, 'head')) &&
                                !str_contains($r, 'vice') &&
                                !str_contains($r, 'co-') &&
                                !str_contains($r, 'co ')
                            ) {
                                return 0;
                            }
                            if (str_contains($r, 'vice') || str_starts_with($r, 'vp') || str_contains($r, 'v.p')) {
                                return 1;
                            }
                            if (str_contains($r, 'secretary') || str_contains($r, 'treasurer') || str_contains($r, 'auditor')) {
                                return 2;
                            }
                            return 3;
                        };

                        // Build a single node payload (role, name, tenure) reused for root + children.
                        $nodeFn = function ($officer) {
                            $u = $officer->getRelations()['user'] ?? null;
                            $p = $u?->getRelations()['profile'] ?? null;
                            $name = trim(($p?->first_name ?? '') . ' ' . ($p?->last_name ?? ''));
                            if ($name === '') {
                                $name = 'User #' . ($officer->getAttributes()['user'] ?? '?');
                            }
                            $tenure = $officer->member_since
                                ? 'Since ' . $officer->member_since->format('M Y')
                                : null;

                            return [
                                'role' => $officer->role ?: 'Officer',
                                'name' => $name,
                                'tenure' => $tenure,
                            ];
                        };

                        $sorted = $org->officersOfThisOrganization
                            ->sortBy(fn ($o) => $rankFn($o->role))
                            ->values();
                        $root = $sorted->first(fn ($o) => $rankFn($o->role) === 0) ?? $sorted->first();
                        $others = $sorted
                            ->reject(fn ($o) => $o->org_officer_id === $root->org_officer_id)
                            ->values();
                    @endphp

                    <div class="org-chart">
                        <div class="oc-root">
                            @php($rootNode = $nodeFn($root))
                            <div class="oc-node">
                                <div class="oc-node-role">{{ $rootNode['role'] }}</div>
                                <div class="oc-node-body">
                                    <div class="oc-node-name">{{ $rootNode['name'] }}</div>
                                    @if ($rootNode['tenure'])
                                        <div class="oc-node-tenure">{{ $rootNode['tenure'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($others->isNotEmpty())
                            <div class="oc-trunk"></div>
                            <div class="oc-children">
                                @foreach ($others as $officer)
                                    @php($node = $nodeFn($officer))
                                    <div class="oc-child">
                                        <div class="oc-node">
                                            <div class="oc-node-role">{{ $node['role'] }}</div>
                                            <div class="oc-node-body">
                                                <div class="oc-node-name">{{ $node['name'] }}</div>
                                                @if ($node['tenure'])
                                                    <div class="oc-node-tenure">{{ $node['tenure'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
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
