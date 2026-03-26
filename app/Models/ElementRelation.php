<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElementRelation extends Model
{
    //
    protected $fillable = [
        'source_element_id',
        'target_element_id', 
        'multiplier',
    ];
    
    public function sourceElement()
    {
        return $this->belongsTo(Element::class, 'source_element_id');
    }
    
    public function targetElement()
    {
        return $this->belongsTo(Element::class, 'target_element_id');
    }
    
}
