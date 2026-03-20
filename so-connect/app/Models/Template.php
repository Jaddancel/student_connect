<?php

namespace App\Models;

use App\Models\Template\templateDescription;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'template_id';

    protected $table = 'templates';

    protected $fillable = [
        'template_description',
        'template_link',
    ];

    public function author()
    {
        return $this->belongsTo(Member::class, 'template_author', 'member_id');
    }

    public function description()
    {
        return $this->belongsTo(templateDescription::class, 'template_description', 'template_description_id');
    }
}
