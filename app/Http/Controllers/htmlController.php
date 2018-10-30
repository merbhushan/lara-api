<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\ComboMaster;
use DB;

class htmlController extends Controller
{
    /**
     * This function will returns a combo data.
     * @param   $strComboName
     * @param   $blnIsHttpRequest
     */
    public static function getCombo(Request $request, $strComboName, $blnIsHttpRequest = 1){
        $replacementCollection = [];
        // Get combo Detail from ComboMaster.
        $objCombo = ComboMaster::where('name', $strComboName)
            ->active()
            ->first();
        // If not exist then nothing in a response.
        if(is_null($objCombo) || empty($objCombo)){
	        return $this->httpResponse('');        	
        }
        else{
            // Generate Combo Query.
        	if(!empty($objCombo->replacement_elements)){
		        $replacementCollection = collect(json_decode($objCombo->replacement_elements));
		        $replacementCollection = $replacementCollection->map(function($item, $key)use($request){
		        	return session($item, null);
		        })->toArray();
	        }

            $objComboDetail = DB::select($objCombo->query, $replacementCollection);
	        if(!$blnIsHttpRequest){
        		return $objComboDetail;
	        }
            // Generate Response object.
            $arrComboDetail = [
                "key" => $objCombo->key,
                "name" => $objCombo->name,
                "index" =>$objCombo->index,
                "data" => $objComboDetail
            ];
            
	        return self::httpResponse($arrComboDetail, $blnIsHttpRequest);        	
        }
        
    }
}
