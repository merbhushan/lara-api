<?php

namespace App\Model;

trait CommonScope
{
    public function scopeActive($query){
        return $query->where('is_active', 1);
    }

    public function scopeName($query, $strName){
    	return $query->where('name', $strName);
    }

    public function scopeUser($query, $strUsers){
    	if(empty($strUsers)){
    		$strUsers = session('user_id', null);
    	}
    	else if(!is_array($strUsers)){
    		$strUsers = explode(',', $strUsers);
    	}
    	
    	return $query->whereIn('user_id', $strUsers);
    	
    }
}
