<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Helpdesk extends Model
{
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
