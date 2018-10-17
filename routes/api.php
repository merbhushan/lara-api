<?php

use Illuminate\Http\Request;
use App\Model\Scope;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('error/{error}', 'exceptoinController@index');

// InitialiseSession middleware is being added to store global information into a session of a user once token is being validate by passport
Route::middleware(['auth:api', 'ValidateUserAccess', 'InitialiseSession'])->group(function(){
	$arrMiddleware	=	[];
	foreach (Scope::where('is_active', 1)->get() as $scope) {
		$dataType = $scope->dataType;
		
		// Fetch action controller. Default is breadController
		$apiController = $dataType->controller
	                            ? $scope->controller
	                            : 'breadController';
	    // Fetch Scope name
	    $strApiScopes = $scope->name;

	    $arrResourceAction	=	$scope->apiAction()->select('name')->get()->pluck('name')->toArray();
	    // dd($arrResourceAction);
	    // Custom validation middleware
	    if(!empty($scope->middleware)){
	        $arrMiddleware[]	=	$scope->middleware;
	    }

	    // scope validattion middleware
	    if(!empty($strApiScopes)){
        	$arrMiddleware[]	=	'scope:' .$strApiScopes;
        }
        // dd($dataType);
        // If there is any middleware for a route then add it with a route.
        // if(empty($arrMiddleware)){
        	Route::resource($dataType->slug, $apiController, ["only"=>$arrResourceAction, "as" =>$strApiScopes]);
        // }
        // else{
        // 	Route::resource($scope->slug, $apiController, ["only"=>$arrResourceAction])->middleware($arrMiddleware);
        // }
	}
});
