<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $table = 'posts';

    protected $primaryKey = 'post_id';

    protected $fillable = [
        'organization',
        'title',
        'excerpt',
        'body',
        'tag',
        'image_path',
        'is_featured',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'bool',
        'published_at' => 'datetime',
    ];

    public function organizationOfPost()
    {
        return $this->belongsTo(Organization::class, 'organization', 'organization_id');
    }
}
