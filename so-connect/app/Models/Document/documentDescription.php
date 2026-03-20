<?php

namespace App\Models\Document;

use App\Models\Document;
use Database\Factories\Document\documentDescriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class documentDescription extends Model
{
    /** @use HasFactory<documentDescriptionFactory> */
    use HasFactory;

    const CREATED_AT = 'document_created_at';

    const UPDATED_AT = 'document_modified_at';

    protected $primaryKey = 'document_desc_id';

    protected $fillable = [
        'document_title',
        'document_desc_text',
    ];

    protected $table = 'document_descriptions';

    public function documents()
    {
        return $this->hasMany(Document::class, 'document_desc_id', 'document_desc_id');
    }
}
