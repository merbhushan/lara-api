<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public static function httpResponse($data, $code=1, $intResponseCode = 200, $type='application/json', $strAllowOrigin = '*'){
    	
    	if(!empty($data)){
        	$arrData = is_array($data)?$data:$data->toArray();    		
    	}
    	else{
    		$code = '';
    		$intResponseCode = 404;
    		$arrData = '';
    	}

        $arrResponse = [
            "code" => $code,
            "data" => $arrData
        ];
        
        return response(json_encode($arrResponse), $intResponseCode)->header('content-type', $type);
    }
}
