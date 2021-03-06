<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataRow extends Model
{	
    use SoftDeletes;

	// Scope is added to exclue hidden type fields
    public function scopeSkipHidden($query){
    	return $query->where('is_hidden_in_listing', '<>', 1);
    }

    // browse scope
    public function scopeIsBrowsable($query){
        return $query->where('browse', 1);
    }

    // updatable field scope
    public function scopeIsUpdatable($query){
        return $query->where('update', 1);
    }

    // Searchable field scope
    public function scopeIsSearchable($query){
        return $query->where('search', '1');
    }

    // Orderable field scope
    public function scopeIsOrderable($query){
        return $query->where('order', '1');
    }

    // View scope
    public function scopeIsViewable($query){
    	return $query->where('read', 1);
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

    public function getDetailsAttribute($value){
        return json_decode($value);
    }
}
