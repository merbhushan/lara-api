<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataType extends Model
{
	// Relationship with ApiAction
    public function apiAction(){
    	return $this->belongsToMany('App\Model\ApiAction', 'data_type_api_action');
    }

    // Relationship with dataGroup
    public function dataGroup(){
    	return $this->hasMany('App\Model\DataGroup');
    }
}
