<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
	// Attachment can map with multiple items.
	public function AttachmentMapping(){
		return $this->hasMany('App\Model\AttachmentMapping');
	}
}
