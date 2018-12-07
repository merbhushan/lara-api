<?php

use Illuminate\Http\Request;
use App\Model\Scope;
use App\Model\DataType;
use App\Model\ApiAction;
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
// Route for returning an error object in response.
Route::get('error/{error}', 'exceptoinController@index');

// InitialiseSession middleware is being added to store global information into a session of a user once token is being validate by passport
Route::middleware(['auth:api', 'ValidateUserAccess', 'InitialiseSession'])->group(function(){
	// To Get Combo Object
	Route::get('combo/{comboName}', 'htmlController@getCombo');
	// Get DataTypes
	$dataTypes = DataType::get()->keyBy('id');
	// Get Resource route actions
	$resourceRouteActions = ApiAction::get()->keyBy('id');
	// Generate a route for each scope.
	foreach (Scope::where('is_active', 1)->where('portal_id', '!=', 3)->get() as $scope) {
		$arrMiddleware	=	[];
		// Set Data type of a scope
		if(!is_null($scope->data_type_id)){
			$dataType = $dataTypes[$scope->data_type_id];

			// Fetch action controller. Default is breadController
			$apiController = $dataType->controller
		                            ? $scope->controller
		                            : 'breadController';
		}
		// Set Scope Action
	    $arrResourceAction	=	$resourceRouteActions[$scope->api_action_id]->toArray();
	    
	    // Custom validation middleware
	    if(!empty($scope->middleware)){
	        $arrMiddleware[]	=	$scope->middleware;
	    }

	    // scope validattion middleware
	    $arrMiddleware[]	=	'scope:' .$scope->name;
        
        /**
		 * We have two types of routes. 1. Resource Route 2. Custom Route.
		 * If is_resource_routing flag is being set in scope then route to resoure routing.
		 *
         */
        if($scope->is_resource_routing){
        	// Resource routing
        	Route::resource($dataType->slug, $apiController, ["only"=>$arrResourceAction, "as" =>$scope->name])->middleware($arrMiddleware);
        }
        else{
        	// Custom Routing
        	// Generate route action and slug based on datatype
       		if(is_null($scope->data_type_id)){
       			$routeAction = $scope->route_action;
       			$strSlug = $scope->route_slug;
       		}
       		else{
       			$routeAction = $apiController .'@' .$scope->route_action;
        		$strSlug = $dataType->slug .'/' .$scope->route_slug;
        	}
        	// If api_action_id is being 1, 2, 4 or 5 then request method will be Get eles it will be post. Here method selection is based on resource routing action method.
        	switch ($scope->api_action_id) {
        		case '1':
        		case '2':
        		case '4':
        		case '5':
        			Route::get($strSlug, $routeAction)
        				->middleware($arrMiddleware)
        				->name($scope->name);
        			break;
        		
        		default:
        			Route::post($strSlug, $routeAction)
        				->middleware($arrMiddleware)
        				->name($scope->name);
        			break;
        	}
        }
	}
});