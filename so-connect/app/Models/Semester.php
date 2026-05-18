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
        'starts_at',
        'vacation_days',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'vacation_days' => 'integer',
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
     * Returns the earliest semester that is currently in its active (vacation) period.
     */
    public static function currentlyActive(): ?self
    {
        return static::query()
            ->orderBy('starts_at')
            ->get()
            ->first(fn (self $s) => $s->isCurrentlyActive());
    }
}
