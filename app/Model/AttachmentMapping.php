<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttachmentMapping extends Model
{
    protected $table = 'attachment_mapping';

    use SoftDeletes;

    public $blnStoreUserInfo=1;
	public $blnUpdateDeleteByInfo =1;

    public function attachment(){
    	return $this->belongsTo('App\Model\Attachment');
    }

}
