<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DataGroup extends Model
{	
	// One to many relationship with DataRow
    public function dataRow(){
    	return $this->hasMany('App\Model\DataRow');
    }
}
