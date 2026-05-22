<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Semester extends Model
{
    protected $table = 'semesters';

    protected $primaryKey = 'semester_id';

    protected $fillable = [
        'name',
        'semester_number',
        'starts_at',
        'vacation_days',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'vacation_days' => 'integer',
            'semester_number' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function workplans(): HasMany
    {
        return $this->hasMany(Workplan::class, 'semester_id', 'semester_id');
    }

    public function activePeriodStart(): Carbon
    {
        return $this->starts_at->copy()->subDays($this->vacation_days);
    }

    public function activePeriodEnd(): Carbon
    {
        return $this->starts_at->copy()->subDay();
    }

    public function isCurrentlyActive(): bool
    {
        $today = Carbon::today();

        return $today->between($this->activePeriodStart(), $this->activePeriodEnd());
    }

    /**
     * The end of this semester's event-plan date range:
     * the day before the next semester starts, or null if this is the last semester.
     */
    public function endsAt(): ?Carbon
    {
        $next = static::query()
            ->where('starts_at', '>', $this->starts_at)
            ->orderBy('starts_at')
            ->first();

        return $next ? $next->starts_at->copy()->subDay() : null;
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>', Carbon::today())->orderBy('starts_at');
    }

    /**
     * Returns true if any semester has not yet started or is in its prep period.
     * Used to gate creating a new school year — the cycle must be fully complete first.
     */
    public static function hasActiveOrUpcoming(): bool
    {
        return static::query()
            ->get()
            ->contains(fn (self $s) => $s->starts_at->isFuture() || $s->isCurrentlyActive());
    }

    /**
     * Returns the earliest semester that is currently in its active (vacation) period.
     */
    public static function currentlyActive(): ?self
    {
        return static::query()
            ->orderBy('starts_at')
            ->get()
            ->first(fn (self $s) => $s->isCurrentlyActive());
    }

    /**
     * Returns the current semester: active one first, otherwise the most recently started.
     */
    public static function current(): ?self
    {
        return static::currentlyActive()
            ?? static::query()->where('starts_at', '<=', Carbon::today())->orderByDesc('starts_at')->first();
    }

    /**
     * Returns the current school year string, using the current semester if one exists,
     * otherwise deriving it from today's date so forms are always pre-filled.
     */
    public static function currentSchoolYear(): string
    {
        $semester = static::current();
        if ($semester) {
            return $semester->schoolYear();
        }

        $today = Carbon::today();
        $year  = (int) $today->format('Y');
        $month = (int) $today->format('n');

        return $month >= 8
            ? $year . '–' . ($year + 1)
            : ($year - 1) . '–' . $year;
    }

    /**
     * Derives the school year string (e.g. "2024–2025") from starts_at.
     * Aug–Dec → that year to next. Jan–Jul → previous year to that year.
     */
    public function schoolYear(): string
    {
        $year = (int) $this->starts_at->format('Y');
        $month = (int) $this->starts_at->format('n');

        if ($month >= 8) {
            return $year . '–' . ($year + 1);
        }

        return ($year - 1) . '–' . $year;
    }

    /**
     * Returns "1st" or "2nd" based on semester_number, falling back to start month.
     */
    public function semesterLabel(): string
    {
        if ($this->semester_number === 1) {
            return '1st';
        }

        if ($this->semester_number === 2) {
            return '2nd';
        }

        $month = (int) $this->starts_at->format('n');

        return $month >= 8 ? '1st' : '2nd';
    }
}
