<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataRow extends Model
{	
	// Scope is added to exclue hidden type fields
    public function scopeSkipHidden($query){
    	return $query->where('element_type_id', '<>', 0);
    }
}
