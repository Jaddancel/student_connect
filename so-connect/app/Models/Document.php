<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $primaryKey = 'document_id';

    protected $table = 'documents';

    protected $fillable = [
        'document_desc_id',
        'document_link',
        'document_author',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function documentDescription()
    {
        return $this->belongsTo(Document\documentDescription::class, 'document_desc_id', 'document_desc_id');
    }

    public function author()
    {
        return $this->belongsTo(Member::class, 'document_author', 'member_id');
    }
}
