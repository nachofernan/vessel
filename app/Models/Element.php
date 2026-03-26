<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Element extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];
    
    public function relations()
    {
        return $this->hasMany(ElementRelation::class);
    }
    
    public function sourceRelations()
    {
        return $this->hasMany(ElementRelation::class, 'source_element_id');
    }
    
    public function targetRelations()
    {
        return $this->hasMany(ElementRelation::class, 'target_element_id');
    }
}
