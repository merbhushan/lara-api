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
    public function storeFile($strFileName, $intModuleId, $intModuleRefId, Request $request, $blnIsMultiple = 1, $strPath='test/meditab/'){
        $arrFiles = [];
        if(!is_array($request->file($strFileName))){
            $arrFiles[] = $request->file($strFileName);
        }
        else{
            $arrFiles = $request->file($strFileName);
        }

        if(!$blnIsMultiple && !empty($intModuleId) && !empty($intModuleRefId)){
            AttachmentMapping::where("module_id", $intModuleId)
                ->where("module_ref_id", $intModuleRefId)
                ->update(["is_active" => 0]);
        }
        
        foreach ($arrFiles as $key => $file) {        
            $objAttachment = $this->uploadFile($file, $request, $strPath, $key);
            $this->manageMapping($objAttachment, $intModuleId, $intModuleRefId, $request, $strFileName, $key);
            $objAttachment->view = $this->getFile($objAttachment);
            $objAttachment->download = $this->getFile($objAttachment, 1);
            $arrResponse[$key] = $objAttachment->toArray();       
        }
        return is_array($request->file($strFileName))?$arrResponse:$arrResponse[0];
    }

    public function manageMapping($objAttachment, $intModuleId, $intModuleRefId, $request, $strFileName,$intIndex=null){
        $strTitle = is_array($request->{$strFileName .'_title'})?$request->{$strFileName .'_title'}[$intIndex]:$request->{$strFileName .'_title'};
        $objMapping = new AttachmentMapping();
        $objMapping->module_id = $intModuleId;
        $objMapping->module_ref_id = $intModuleRefId;
        $objMapping->title = $strTitle;
        $objMapping->created_by = !empty($request->user())?$request->user()->id:null;
        $objMapping->updated_by = !empty($request->user())?$request->user()->id:null;

        $objAttachment->AttachmentMapping()->save($objMapping);
    }

    /**
     * It's used to upload file in AWS. 
     * @param $strFileName	string
     * @param $request 		Request Object
     * @param $strPath 		S3 Path
     * @return Attachment Object
     */
    public function uploadFile($objFile, Request $request, $strPath='test/meditab/', $intIndex = null){
    	$objAttachment = new Attachment();

	    $filenamewithextension = $objFile->getClientOriginalName();

    	// Set Attachment parameters
    	$objAttachment->mime_type = $objFile->getClientOriginalExtension();
    	$objAttachment->original_name = pathinfo($filenamewithextension, PATHINFO_FILENAME);
	 	$objAttachment->upload_path = $strPath;
	 	$objAttachment->created_by = !empty($request->user())?$request->user()->id:null;
	 	
	 	$objAttachment->save();

	 	//Upload File to s3
	    $objStorage = Storage::put($objAttachment->upload_path .$objAttachment->id .'.' .$objAttachment->mime_type, fopen($objFile, 'r+'));
	    // dd($objStorage);
	    // Initially status of attachment is pending. Once it's uploaded change status to uploaded.
	    $objAttachment->status = '2';
	    $objAttachment->save();

	    return $objAttachment;
    }

    public function getFileByAttachmentId($intAttachmentId, $intType=0, $blnReturnUrl=1, $blnHttpResponse = 1){
        $objAttachment = Attachment::find($intAttachmentId);
        return $this->getFile($objAttachment, $intType=0, $blnReturnUrl=1, $blnHttpResponse = 1);
    }
    /**
     * This function is used to view or download file.
     * @param $intAttachmentId		Attachment Id
     * @param $intType 				If true then download a file else view file
     * @param $blnReturnUrl 		If true then Url Response else redirected to s3.
     * @param $blnHttpResponse 		If true Http response else normal reponse. (If attachment is not present then it will return 404 error if this flag is set to true else 0 will be returned.)
     */
    public function getFile(Attachment $objAttachment, $intType=0, $blnReturnUrl=1, $blnHttpResponse = 1){
    	if(!empty($objAttachment)){
    		// Initialize headers
	    	$arrHeaders = [];
	    	if($intType){
	    		$arrHeaders = [
					'ResponseContentType' => 	'application/octet-stream',
		        	'ResponseContentDisposition'	=> 	'attachment; filename="'.$objAttachment->original_name ."." .$objAttachment->mime_type.'"',
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
