<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Helpdesk extends Model
{
    public function createdBy(){
    	return $this->belongsTo('App\User', 'created_by');
    }
}
