<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class President extends Model
{
    protected $table = 'presidents';

    protected $primaryKey = 'president_id';

    protected $fillable = [
        'officer',
    ];

    public function officer(): BelongsTo
    {
        return $this->belongsTo(Officer::class, 'officer', 'org_officer_id');
    }
}
