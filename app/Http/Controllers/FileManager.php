<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Model\Attachment;
use App\Model\AttachmentMapping;


/**
 * This class is being used to manage storage.
 */
class FileManager extends Controller
{
    /**
     * 
     */
    public function stroeFile($strFileName, $intModuleId, $intModuleRefId, Request $request, $strPath='test/meditab/'){
    	$objAttachment = $this->uploadFile($strFileName, $request, $strPath);

    	$objMapping = new AttachmentMapping();
    	$objMapping->module_id = $intModuleId;
    	$objMapping->module_ref_id = $intModuleRefId;
    	$objMapping->title = $request->title;
    	$objMapping->created_by = !empty($request->user())?$request->user()->id:null;
    	$objMapping->updated_by = !empty($request->user())?$request->user()->id:null;

    	$objAttachment->AttachmentMapping = $objAttachment->AttachmentMapping()->save($objMapping);

    	return $objAttachment;
    }

    /**
     * It's used to upload file in AWS. 
     * @param $strFileName	string
     * @param $request 		Request Object
     * @param $strPath 		S3 Path
     * @return Attachment Object
     */
    public function uploadFile($strFileName, Request $request, $strPath='test/meditab/'){
    	$objAttachment = new Attachment();

    	//get filename with extension
	    $filenamewithextension = $request->file('profile_image')->getClientOriginalName();

    	// Set Attachment parameters
    	$objAttachment->mime_type = $request->file($strFileName)->getClientOriginalExtension();
    	$objAttachment->original_name = pathinfo($filenamewithextension, PATHINFO_FILENAME);
	 	$objAttachment->upload_path = $strPath;
	 	$objAttachment->created_by = !empty($request->user())?$request->user()->id:null;
	 	
	 	$objAttachment->save();

	 	//Upload File to s3
	    $objStorage = Storage::put($objAttachment->upload_path .$objAttachment->id .'.' .$objAttachment->mime_type, fopen($request->file('profile_image'), 'r+'));
	    // dd($objStorage);
	    // Initially status of attachment is pending. Once it's uploaded change status to uploaded.
	    $objAttachment->status = '2';
	    $objAttachment->save();

	    return $objAttachment;
    }

    /**
     * This function is used to view or download file.
     * @param $intAttachmentId		Attachment Id
     * @param $intType 				If true then download a file else view file
     * @param $blnReturnUrl 		If true then Url Response else redirected to s3.
     * @param $blnHttpResponse 		If true Http response else normal reponse. (If attachment is not present then it will return 404 error if this flag is set to true else 0 will be returned.)
     */
    public function getFile($intAttachmentId, $intType=0, $blnReturnUrl=1, $blnHttpResponse = 1){
    	$objAttachment = Attachment::find($intAttachmentId);
    	
    	if(!empty($objAttachment)){
    		// Initialize headers
	    	$arrHeaders = [];
	    	if($intType){
	    		$arrHeaders = [
					'ResponseContentType' => 	'application/octet-stream',
		        	'ResponseContentDisposition'	=> 	'attachment; filename="'."test.txt".'"',
		        ];
	    	}

	    	// Validate a file is being exist at uploaded path.
    		if(Storage::exists($objAttachment->upload_path .$objAttachment->id .'.' .$objAttachment->mime_type)){
    			$strUrl = Storage::temporaryUrl(
				    $objAttachment->upload_path .$objAttachment->id .'.' .$objAttachment->mime_type, now()->addMinutes(60), $arrHeaders
				);
				if($blnReturnUrl){
					return $strUrl;
				}
				return redirect($strUrl);
    		}	
    	}

    	if($blnHttpResponse){
    		abort('404');
    	}
    	return 0;
    }
}
