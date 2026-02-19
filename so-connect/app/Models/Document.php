<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;
    
    protected $primaryKey = 'document_id';
    protected $fillable = [
        'document_link',
        'document_description',
        'approval_id',
    ];
     
    public $timestamps = false;

    public function approval()
    {
        return $this->belongsTo(Approval::class, 'approval_id', 'approval_id');
    }
}
