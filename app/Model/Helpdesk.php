<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Helpdesk extends Model
{
	use SoftDeletes;
    public function createdBy(){
    	return $this->belongsTo('App\User', 'created_by');
    }

    public function assignTo(){
    	return $this->belongsToMany('App\User', 'helpdesk_assign_to', 'helpdesk_id', 'user_id');
    }

    public function tasks(){
    	return $this->hasMany('App\Model\Task');
    }
}
