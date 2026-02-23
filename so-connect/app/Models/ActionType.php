<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionType extends Model
{
    protected $table = 'action_types';

    protected $primaryKey = 'action_type_code';

    protected $fillable = [
        'action_type',
    ];

    public $timestamps = false;

    public function actionRequests()
    {
        return $this->hasMany(ActionRequest::class, 'request_action_type');
    }
}
