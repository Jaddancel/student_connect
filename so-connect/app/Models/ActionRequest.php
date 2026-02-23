<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionRequest extends Model
{
    protected $table = 'action_requests';

    protected $primaryKey = 'request_id';

    protected $fillable = [
        'action',
        'request_action_type',
    ];

    public $timestamps = false;

    public function actionType()
    {
        return $this->belongsTo(ActionType::class, 'request_action_type');
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'request');
    }
}
