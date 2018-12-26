<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\FileManager;

class Attachment extends Model
{
	public $objFileManager;

	public function __construct(){
		$this->objFileManager = new FileManager();
	}
	// Attachment can map with multiple items.
	public function AttachmentMapping(){
		return $this->hasMany('App\Model\AttachmentMapping');
	}

	public function getViewAttribute($value){
		return $this->objFileManager->getFile($this, 0, 1, 0);
	}

	public function getDownloadAttribute($value){
		return $this->objFileManager->getFile($this, 1, 1, 0);
	}
}
