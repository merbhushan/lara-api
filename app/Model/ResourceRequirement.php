<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceRequirement extends Model
{
    use SoftDeletes;

	public $blnStoreUserInfo=1;
	public $blnUpdateDeleteByInfo =1;

	// Has Many relationship with Skills.
	public function skills(){
		return $this->hasMany('App\Model\Skill');
	}

	public function getSkillsAttribute($value)
    {
        return json_decode($value);
    }
}
