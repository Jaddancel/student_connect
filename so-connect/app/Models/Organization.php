<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{

    use HasFactory;

    protected $primaryKey = 'organization_id';
    protected $keyType = 'int';
    public $incrementing = true;

protected $fillable = [
        'organization_name',
        'organization_initial'
    ];
    

public $timestamps = false;
    protected function casts(): array{
        return [
            'organization_registered_at' => 'timestamp'
        ];
    }
}
