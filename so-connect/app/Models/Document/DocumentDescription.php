<?php

namespace App\Models\Document;

use App\Models\Document;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDescription extends Model
{
    /** @use HasFactory<\Database\Factories\Document\DocumentDescriptionFactory> */
    use HasFactory;

    public $primaryKey = 'document_description_id';
    protected $fillable = [
        'document_title',
        'document_description',
        'document_author'
    ];


    public const CREATED_AT = 'document_created_at';
    public const UPDATED_AT = 'document_updated_at';

    public function documents()
    {
        return $this->hasMany(Document::class, 'document_description', 'document_description_id');
    }

    public function author()
    {
        return $this->belongsTo(Member::class, 'document_author', 'member_id');
    }
}