<?php

namespace App\Model;

trait CommonScope
{
    // Active Scope
    public function scopeActive($query){
        return $query->where('is_active', 1);
    }
    // Filter on scope name
    public function scopeName($query, $strName){
    	return $query->where('name', $strName);
    }
    // Scope on user_id
    public function scopeUser($query, $strUsers){
    	if(empty($strUsers)){
    		$strUsers = session('user_id', null);
    	}
    	else if(!is_array($strUsers)){
    		$strUsers = explode(',', $strUsers);
    	}
    	
    	return $query->whereIn('user_id', $strUsers);
    	
    }
    // hasOne Relationship for attachment.
    public function attachment(){
        return $this->hasOne('App\Model\AttachmentMapping', 'module_ref_id')
            ->with(['attachment' => function($query){
                $query->selectRaw('id, upload_path, mime_type, original_name, "" as view, "" as download')
                ->where('status', 2);
            }]);
    }
}
