<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataRow extends Model
{	
	// Scope is added to exclue hidden type fields
    public function scopeSkipHidden($query){
    	return $query->where('is_hidden_in_listing', '<>', 1);
    }

    // browse scope added
    public function scopeIsBrowsable($query){
    	return $query->where('browse', 1);
    }
}
