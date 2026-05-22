<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccomplishmentMedia extends Model
{
    protected $table = 'accomplishment_media';

    protected $fillable = [
        'form_submission_id',
        'organization_id',
        'file_path',
        'activity_title',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function formSubmission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }
}
