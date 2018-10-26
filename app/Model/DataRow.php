<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataRow extends Model
{	
	// Scope is added to exclue hidden type fields
    public function scopeSkipHidden($query){
    	return $query->where('is_hidden_in_listing', '<>', 1);
    }

    // browse scope
    public function scopeIsBrowsable($query){
    	return $query->where('browse', 1);
    }

    // storable field scope
    public function scopeIsStorable($query){
    	return $query->where(function($query){
    		$query->where('store', 1)
    			->orWhere(function($query){
    				$query->where('add', 1)
    					->whereNull('store');
    			});
    	});
    }
}
