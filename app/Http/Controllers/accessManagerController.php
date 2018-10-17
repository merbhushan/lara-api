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
}
