<?php

namespace App\Models\Template;

use App\Models\Template;
use Illuminate\Database\Eloquent\Model;

class templateDescription extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'template_desc_id';

    protected $table = 'template_descriptions';

    protected $fillable = [
        'template_name',
        'template_desc',
        'template_created_at',
    ];

    public $casts = [
        'template_created_at' => 'datetime',
    ];

    public function templates()
    {
        return $this->hasMany(Template::class, 'template_description', 'template_desc_id');
    }

    protected static function booted()
    {
        static::creating(function ($templateDescription) {
            $templateDescription->template_created_at = now();
        });
    }
}
