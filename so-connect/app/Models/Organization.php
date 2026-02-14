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
        'organization_initial',
        'organization_president_id'
    ];
    
public function president(){
        return $this->belongsTo(Membership::class, 'organization_president_id', 'membership_id');
    }


    
    protected $attributes = [
        'organization_registered_at' => null
    ];


public $timestamps = false;
    protected function casts(): array{
        return [
            'organization_registered_at' => 'timestamp'
        ];
    }
}
