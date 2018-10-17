<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Scope;
use DB;

class accessManagerController extends Controller
{
    // Url's Scope object
    public $scope;

	// object of Datatype linked with url's scope 
    public $dataType;

    // Object of breadtable linked with dataType
    public $breadTable;

    // Array of User's access on Data & Fileds.
    public $objAccessLevel;

    // having a data row id which is being accessiable by User
    public $objAccessiableRow;

    public $strScope;

    public $intUserId;

    public function __construct($strScope=null, $intUserId=null){
    	$this->strScope = $strScope;
    	$this->intUserId = $intUserId;
    }

    /**
     * Generate a where condition to filter a data based on user's access on a data.
     * @param 	object 	DataType
     * @param 	array 	UserLevel
     * @return 	array
     */
    protected function getDataFilterCondition($dataType, $arrUserLevel){
        return DB::table('data_type_user_level')
            ->select('condition as where' , 'parameters')
            ->where('data_type_id', $dataType)
            ->whereIn('data_level_user_id', $arrUserLevel)
            ->where('is_deleted', 0)
            ->get()
            ->toArray();
    }

    /**
     * Returns an array of user's access at data & row level.
     * @param 	int 	ScopeId
     * @return 	array 	
     */
    protected function getUserAccessLevel($intScopeId){
        // $objAccessLevel = Session::select('data')->where('module_id', 1)->user(session('user_id', null))->where('ref_id', $intScopeId)->first();

        // if(empty($objAccessLevel)){
            $objRoleBasedAccess = DB::table('user_role')
                ->leftJoin('scope_role', 'scope_role.role_id', '=', 'user_role.role_id')
                ->select('scope_role.data_type_user_level_id', 'scope_role.data_row_user_level_id')
                ->where('user_role.user_id', $this->intUserId)
                ->where('scope_role.scope_id', $intScopeId);

            $objAccessLevel = DB::table('scope_user')
                ->select('scope_user.data_type_user_level_id', 'scope_user.data_row_user_level_id')
                ->where('scope_user.user_id', $this->intUserId)
                ->where('scope_user.scope_id', $intScopeId)
                ->union($objRoleBasedAccess)
                ->get();

            // Session::insert([
            //     'module_id'=>'1',
            //     'user_id' => session('user_id', 0),
            //     'ref_id'=>$intScopeId,
            //     'data'=> json_encode($objAccessLevel)
            // ]);
        // }
        // else{
        //     $objAccessLevel = json_decode($objAccessLevel->data);
        // }

        return ($objAccessLevel);
    }
}
