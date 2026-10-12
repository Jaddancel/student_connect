<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Semester;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data behind the super admin System Dashboard: headline totals, server
 * uptime, and the period-driven charts (activity, user registrations, error
 * rate). Periods are calendar windows in the display timezone; the activity
 * chart overlays each semester's start (red line) and its preparation window
 * (the `vacation_days` before it starts, shaded).
 */
class SystemDashboardService
{
    public const DEFAULT_PERIOD = 'quarterly';

    public const PERIOD_LABELS = [
        'three_days' => '3 Days',
        'weekly' => 'Weekly',
        'quarterly' => 'Quarterly',
        'semester' => 'Semester',
        'yearly' => 'Yearly',
    ];

    /** Plural noun used in chart titles ("Activity over the last 4 quarters"). */
    private const PERIOD_NOUNS = [
        'three_days' => '3-day periods',
        'weekly' => 'weeks',
        'quarterly' => 'quarters',
        'semester' => 'semesters',
        'yearly' => 'years',
    ];

    /** How many periods the activity line spans. */
    private const ACTIVITY_PERIODS = [
        'three_days' => 4,
        'weekly' => 6,
        'quarterly' => 4,
        'semester' => 4,
        'yearly' => 3,
    ];

    /** Resolution of each activity point, in hours. */
    private const ACTIVITY_STEP_HOURS = [
        'three_days' => 6,
        'weekly' => 24,
        'quarterly' => 24 * 7,
        'semester' => 24 * 7,
        'yearly' => 24 * 14,
    ];

    private const REGISTRATION_PERIODS = 8;

    private const STORAGE_CACHE_KEY = 'system-dashboard.storage-bytes';

    public function normalizePeriod(mixed $period): string
    {
        return is_string($period) && array_key_exists($period, self::PERIOD_LABELS)
            ? $period
            : self::DEFAULT_PERIOD;
    }

    /**
     * @return array{registered_accounts:int, active_organizations:int, documents_generated:int, storage_bytes:int, storage_label:string}
     */
    public function summary(): array
    {
        $storageBytes = $this->storageBytes();

        return [
            'registered_accounts' => DB::table('users')->where('user_type', '>', 1)->count(),
            'active_organizations' => Organization::query()->accreditationActive()->count(),
            'documents_generated' => DB::table('generated_documents')->where('status', 'generated')->count(),
            'storage_bytes' => $storageBytes,
            'storage_label' => self::formatBytes($storageBytes),
        ];
    }

    /**
     * Seconds since the server (container) started: the age of PID 1, read
     * from /proc. Falls back to host uptime, or null off Linux.
     */
    public function uptimeSeconds(): ?int
    {
        $uptimeRaw = @file_get_contents('/proc/uptime');
        if ($uptimeRaw === false || $uptimeRaw === '') {
            return null;
        }

        $systemUptime = (float) strtok($uptimeRaw, ' ');

        $statRaw = @file_get_contents('/proc/1/stat');
        if (is_string($statRaw) && ($closing = strrpos($statRaw, ')')) !== false) {
            // Fields after "(comm)" start at field 3; starttime is field 22.
            $fields = preg_split('/\s+/', trim(substr($statRaw, $closing + 1)));
            $startTicks = isset($fields[19]) ? (float) $fields[19] : null;
            $ticksPerSecond = (function_exists('posix_sysconf') && defined('POSIX_SC_CLK_TCK'))
                ? (int) posix_sysconf(POSIX_SC_CLK_TCK)
                : 100;

            if ($startTicks !== null && $ticksPerSecond > 0) {
                $containerUptime = $systemUptime - ($startTicks / $ticksPerSecond);
                if ($containerUptime >= 0) {
                    return (int) floor($containerUptime);
                }
            }
        }

        return (int) floor($systemUptime);
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(string $period): array
    {
        $period = $this->normalizePeriod($period);
        $now = $this->now();

        return [
            'period' => $period,
            'period_label' => self::PERIOD_LABELS[$period],
            'period_noun' => self::PERIOD_NOUNS[$period],
            'activity' => $this->activity($period, $now),
            'registrations' => $this->registrations($period, $now),
            'error_rate' => $this->errorRate($period, $now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activity(string $period, Carbon $now): array
    {
        $majors = $this->periods($period, self::ACTIVITY_PERIODS[$period], $now);
        $domainStart = $majors[0]['start'];
        $domainEnd = $now->copy();
        $stepHours = self::ACTIVITY_STEP_HOURS[$period];

        // Points never straddle a period boundary, so each major label sits
        // exactly on the first point of its period.
        $points = [];
        foreach ($majors as $major) {
            $cursor = $major['start']->copy();
            $first = true;
            while ($cursor->lt($major['end']) && $cursor->lt($domainEnd)) {
                $next = $cursor->copy()->addHours($stepHours);
                if ($next->gt($major['end'])) {
                    $next = $major['end']->copy();
                }
                $points[] = [
                    'key' => $cursor->toIso8601String(),
                    'start' => $cursor->copy(),
                    'end' => $next->copy(),
                    'major' => $first ? $major['label'] : '',
                    'requests' => 0,
                    'generations' => 0,
                    'downloads' => 0,
                ];
                $first = false;
                $cursor = $next;
            }
        }

        $grain = $stepHours < 24 ? 'hour' : 'day';
        $sources = [
            'requests' => $this->bucketCounts('requests', 'requested_at', $domainStart, $domainEnd, $grain),
            'generations' => $this->bucketCounts('generated_documents', 'generated_at', $domainStart, $domainEnd, $grain, fn ($q) => $q->where('status', 'generated')),
            'downloads' => $this->bucketCounts('document_downloads', 'downloaded_at', $domainStart, $domainEnd, $grain),
        ];

        foreach ($sources as $series => $buckets) {
            foreach ($buckets as $bucketStart => $count) {
                $at = Carbon::parse($bucketStart, $grain === 'hour' ? 'UTC' : $this->timezone());
                foreach ($points as $i => $point) {
                    if ($at->gte($point['start']) && $at->lt($point['end'])) {
                        $points[$i][$series] += $count;
                        break;
                    }
                }
            }
        }

        $totals = ['requests' => 0, 'generations' => 0, 'downloads' => 0];
        $serialized = array_map(function (array $point) use (&$totals, $stepHours) {
            foreach (array_keys($totals) as $series) {
                $totals[$series] += $point[$series];
            }

            return [
                'key' => $point['key'],
                'major' => $point['major'],
                'range' => $this->rangeLabel($point['start'], $point['end'], $stepHours < 24),
                'requests' => $point['requests'],
                'generations' => $point['generations'],
                'downloads' => $point['downloads'],
                'total' => $point['requests'] + $point['generations'] + $point['downloads'],
            ];
        }, $points);

        return [
            'points' => $serialized,
            'totals' => $totals + ['all' => array_sum($totals)],
            'semesters' => $this->semesterMarkers($points, $domainStart, $domainEnd),
        ];
    }

    /**
     * Semester start lines and preparation windows that fall inside the chart,
     * as x positions on the point-index axis (point i sits at x = i; a date
     * between two points gets the proportional fractional position).
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array<string, mixed>>
     */
    private function semesterMarkers(array $points, Carbon $domainStart, Carbon $domainEnd): array
    {
        if ($points === []) {
            return [];
        }

        $lastIndex = count($points) - 1;
        $positionOf = function (Carbon $at) use ($points, $lastIndex): float {
            foreach ($points as $i => $point) {
                if ($at->lt($point['end'])) {
                    $span = max(1, $point['start']->diffInSeconds($point['end'], true));
                    $offset = max(0, $point['start']->diffInSeconds($at, false));

                    return round(min($lastIndex, $i + $offset / $span), 3);
                }
            }

            return (float) $lastIndex;
        };

        $markers = [];
        foreach (Semester::query()->orderBy('starts_at')->get() as $semester) {
            $start = Carbon::parse($semester->starts_at->toDateString(), $this->timezone());
            $prepStart = $start->copy()->subDays((int) $semester->vacation_days);

            $startVisible = $start->gte($domainStart) && $start->lte($domainEnd);
            $prepVisible = (int) $semester->vacation_days > 0 && $prepStart->lt($domainEnd) && $start->gt($domainStart);

            if (! $startVisible && ! $prepVisible) {
                continue;
            }

            $markers[] = [
                'label' => $semester->semesterLabel().' Sem '.$semester->schoolYear(),
                'starts_on' => $start->format('M j, Y'),
                'start_x' => $startVisible ? $positionOf($start) : null,
                'prep_from_x' => $prepVisible ? $positionOf($prepStart->max($domainStart)) : null,
                'prep_to_x' => $prepVisible ? $positionOf($start->min($domainEnd)) : null,
            ];
        }

        return $markers;
    }

    /**
     * @return array<string, mixed>
     */
    private function registrations(string $period, Carbon $now): array
    {
        $bars = array_map(fn (array $major) => [
            'label' => $major['label'],
            'count' => DB::table('users')
                ->where('user_type', '>', 1)
                ->where('user_created_at', '>=', $major['start']->copy()->utc())
                ->where('user_created_at', '<', $major['end']->copy()->utc())
                ->count(),
        ], $this->periods($period, self::REGISTRATION_PERIODS, $now));

        return [
            'labels' => array_column($bars, 'label'),
            'counts' => array_column($bars, 'count'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorRate(string $period, Carbon $now): array
    {
        $majors = $this->periods($period, 2, $now);
        $current = $majors[array_key_last($majors)];
        $previous = count($majors) > 1 ? $majors[0] : null;

        $currentRate = $this->errorRateBetween($current['start'], $now);
        $previousRate = $previous ? $this->errorRateBetween($previous['start'], $previous['end']) : null;

        $comparison = null;
        if ($currentRate['rate'] !== null && $previousRate !== null && $previousRate['rate'] !== null) {
            $comparison = match (round($currentRate['rate'], 2) <=> round($previousRate['rate'], 2)) {
                1 => 'higher',
                -1 => 'lower',
                default => 'equal',
            };
        }

        return [
            'current_label' => $current['label'],
            'previous_label' => $previous['label'] ?? null,
            'rate' => $currentRate['rate'],
            'requests' => $currentRate['requests'],
            'errors' => $currentRate['errors'],
            'previous_rate' => $previousRate['rate'] ?? null,
            'comparison' => $comparison,
        ];
    }

    /**
     * @return array{rate: float|null, requests: int, errors: int}
     */
    private function errorRateBetween(Carbon $from, Carbon $to): array
    {
        $row = DB::table('system_request_metrics')
            ->where('bucket_at', '>=', $from->copy()->utc()->startOfHour())
            ->where('bucket_at', '<', $to->copy()->utc())
            ->selectRaw('COALESCE(SUM(requests), 0) as requests, COALESCE(SUM(server_errors), 0) as errors')
            ->first();

        $requests = (int) ($row->requests ?? 0);
        $errors = (int) ($row->errors ?? 0);

        return [
            'rate' => $requests > 0 ? round($errors / $requests * 100, 2) : null,
            'requests' => $requests,
            'errors' => $errors,
        ];
    }

    /**
     * The last $count periods of the given kind, oldest first, the final one
     * containing $now. Each entry: start (inclusive), end (exclusive), label.
     *
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    public function periods(string $period, int $count, ?Carbon $now = null): array
    {
        $now ??= $this->now();

        if ($period === 'semester') {
            $semesters = $this->semesterPeriods($now);
            if ($semesters !== []) {
                return array_slice($semesters, -$count);
            }
        }

        $periods = [];
        $start = $this->periodStart($period, $now);
        for ($i = 0; $i < $count; $i++) {
            $end = $this->periodEnd($period, $start);
            array_unshift($periods, [
                'start' => $start->copy(),
                'end' => $end,
                'label' => $this->periodLabel($period, $start, $end),
            ]);
            $start = $this->periodStart($period, $start->copy()->subSecond());
        }

        return $periods;
    }

    /**
     * Semesters that have started, each running until the next one starts
     * (the last one through today). Empty when none exist yet, in which case
     * callers fall back to half-year windows.
     *
     * @return array<int, array{start: Carbon, end: Carbon, label: string}>
     */
    private function semesterPeriods(Carbon $now): array
    {
        $semesters = Semester::query()
            ->where('starts_at', '<=', $now->toDateString())
            ->orderBy('starts_at')
            ->get();

        $periods = [];
        foreach ($semesters as $i => $semester) {
            $start = Carbon::parse($semester->starts_at->toDateString(), $this->timezone());
            $next = $semesters[$i + 1] ?? null;
            $end = $next
                ? Carbon::parse($next->starts_at->toDateString(), $this->timezone())
                : $now->copy()->addDay()->startOfDay();

            $periods[] = [
                'start' => $start,
                'end' => $end,
                'label' => $semester->semesterLabel().' Sem '.$semester->schoolYear(),
            ];
        }

        return $periods;
    }

    private function periodStart(string $period, Carbon $at): Carbon
    {
        $at = $at->copy();

        return match ($period) {
            'three_days' => $this->threeDayWindowStart($at),
            'weekly' => $at->startOfWeek(Carbon::MONDAY),
            'quarterly' => $at->firstOfQuarter()->startOfDay(),
            'yearly' => $at->startOfYear(),
            // Semester fallback when no semesters are configured: half-years.
            default => $at->month <= 6 ? $at->startOfYear() : $at->startOfYear()->addMonths(6),
        };
    }

    /** 3-day windows are anchored so the current one ends with today. */
    private function threeDayWindowStart(Carbon $at): Carbon
    {
        $anchor = $this->now()->startOfDay()->subDays(2);
        $days = (int) round($anchor->diffInDays($at->copy()->startOfDay(), false));

        return $anchor->addDays((int) floor($days / 3) * 3);
    }

    private function periodEnd(string $period, Carbon $start): Carbon
    {
        return match ($period) {
            'three_days' => $start->copy()->addDays(3),
            'weekly' => $start->copy()->addWeek(),
            'quarterly' => $start->copy()->addMonthsNoOverflow(3),
            'yearly' => $start->copy()->addYear(),
            default => $start->copy()->addMonthsNoOverflow(6),
        };
    }

    private function periodLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'three_days', 'weekly' => $this->rangeLabel($start, $end, false),
            'quarterly' => 'Q'.$start->quarter.' '.$start->year,
            'yearly' => (string) $start->year,
            default => ($start->month <= 6 ? 'H1 ' : 'H2 ').$start->year,
        };
    }

    private function rangeLabel(Carbon $start, Carbon $end, bool $withTime): string
    {
        $last = $end->copy()->subSecond();

        if ($withTime) {
            return $start->format('M j, g A').' – '.$end->format($start->isSameDay($last) ? 'g A' : 'M j, g A');
        }

        $year = $last->year === $this->now()->year ? '' : ', Y';

        if ($start->isSameDay($last)) {
            return $start->format('M j'.$year);
        }

        return $start->format('M j').' – '.$last->format(($start->isSameMonth($last) ? 'j' : 'M j').$year);
    }

    /**
     * Row counts per hour (UTC, exact) or per display-timezone day.
     *
     * @return array<string, int>
     */
    private function bucketCounts(string $table, string $column, Carbon $from, Carbon $to, string $grain, ?callable $scope = null): array
    {
        $offsetMinutes = $this->now()->utcOffset();
        $expression = $grain === 'hour'
            ? "DATE_FORMAT({$column}, '%Y-%m-%d %H:00:00')"
            : "DATE(DATE_ADD({$column}, INTERVAL {$offsetMinutes} MINUTE))";

        return DB::table($table)
            ->where($column, '>=', $from->copy()->utc())
            ->where($column, '<', $to->copy()->utc())
            ->when($scope, $scope)
            ->selectRaw("{$expression} as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function storageBytes(): int
    {
        return (int) Cache::remember(self::STORAGE_CACHE_KEY, now()->addMinutes(10), function () {
            $root = storage_path('app');
            if (! is_dir($root)) {
                return 0;
            }

            $bytes = 0;
            try {
                // CATCH_GET_CHILD skips subdirectories the web user can't open
                // (e.g. root-owned scan scratch folders) instead of throwing.
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                    \RecursiveIteratorIterator::CATCH_GET_CHILD,
                );
                foreach ($iterator as $file) {
                    try {
                        if ($file->isFile() && ! $file->isLink()) {
                            $bytes += $file->getSize();
                        }
                    } catch (\RuntimeException) {
                        // Unreadable file: leave it out of the total.
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }

            return $bytes;
        });
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $value : number_format($value, $value >= 100 ? 0 : 1)).' '.$units[$unit];
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone', 'UTC'));
    }

    private function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }
}
