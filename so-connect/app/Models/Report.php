<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $primaryKey = 'report_id';

    protected $table = 'reports';

    protected $fillable = [
        'generated_at',
        'generated_for',
        'report_link',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'generated_for', 'user_id');
    }
}
