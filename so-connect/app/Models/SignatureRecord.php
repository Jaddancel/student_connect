<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignatureRecord extends Model
{
    protected $table = 'signature_records';

    protected $fillable = [
        'submitter_name',
        'user_id',
        'form_submission_id',
        'signature_path',
        'perceptual_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id', 'form_submission_id');
    }
}
