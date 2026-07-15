<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoringCategory extends Model
{
    protected $table = 'scoring_categories';

    protected $primaryKey = 'scoring_category_id';

    public $timestamps = false;

    protected $fillable = ['key', 'label', 'cap', 'sort_order'];
}
