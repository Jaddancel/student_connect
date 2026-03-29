<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YearTerm extends Model
{
    protected $table = 'year_term_master';

    protected $primaryKey = 'year_term_code';

    public $timestamps = false;

    protected $fillable = [
        'year_term',
    ];

    public function officerOfYearTerm()
    {
        return $this->belongsTo(Officer::class, 'yearterm', 'year_term');
    }
}
