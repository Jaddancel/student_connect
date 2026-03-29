<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = [
        'generated_for',
        'report_link,',
    ];

    public $timestamps = false;

    public function userWhomThisReportIsFor()
    {
        return $this->belongsTo(User::class, 'generated_for', 'user_id');
    }

    public function casts()
    {
        return [
            'generated_at' => 'dateTime',
        ];
    }
    //
}
