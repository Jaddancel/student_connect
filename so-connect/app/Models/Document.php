<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $table = 'documents';

    protected $primaryKey = 'document_id';

    protected $fillable = [
        'link',
        'description_text',
        'author',
    ];

    protected function casts(): array
    {
        return [
            self::CREATED_AT => 'datetime',
            self::UPDATED_AT => 'datetime',
        ];
    }

    public function documentAuthor()
    {
        return $this->belongsTo(User::class, 'author', 'user_id');
    }
}
