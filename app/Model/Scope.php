<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Scope extends Model
{
    use \App\Model\CommonScope;

    // Relationship with Datatype
    public function dataType(){
    	return $this->belongsTo('App\Model\DataType');
    }

    // Relationship with ApiAction
    public function apiAction(){
    	return $this->belongsTo('App\Model\ApiAction');
    }
}
