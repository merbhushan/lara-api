<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Scope;
use DB;
use Redis;

class accessManagerController extends Controller
{
    // Url's Scope object
    public $scope;

	// object of Datatype linked with url's scope 
    public $dataType;

    // Relationship
    public $relationships;

    // Object of breadtable linked with dataType
    public $breadTable;

    // Array of User's access on Data & Fileds.
    public $objAccessLevel;

    // having a data row id which is being accessiable by User
    public $objAccessiableRow;

    // having a data filter condition object.
    public $objFilterCondition;

    // Having a data filter query.
    public $strFilterQuery;

    // Having a pk of a model which is being used in data filtering.
    public $strPk;

    public $strScope;

    public $intUserId;

    public function __construct($strScope=null, $intUserId=null){
    	$this->strScope = $strScope;
    	$this->intUserId = $intUserId;
    }

    /**
     *	to updates users's access detail. 
     */
    public function updateUsersAccess(){
    	if(empty($this->strScope) || is_null($this->strScope)){
    		$this->strScope = session('scope', null);
    	}

    	if(empty($this->intUserId) || is_null($this->intUserId)){
    		$this->intUserId = session('user_id', null);
    	}

    	if(!empty($this->strScope) && !empty($this->intUserId)){
            $objUserAccessDetail = json_decode(Redis::hget('user:' .$this->intUserId, $this->strScope .'_access'));
            // generate scope object
    	    $this->scope = Scope::name($this->strScope)->with('dataType')->active()->first();

            // Set DataType linked with a scope.
            $this->dataType = $this->scope->dataType;

            //Set Relationship linked with a scope.
            $this->relationships = $this->dataType->relationships->keyBy('id');

            // Set a breadTable detail
            // $this->breadTable = $this->dataType->breadTable;

            // If User's access data is not being cached for this scope then find access and set in cache else set data from cache.
            if(is_null($objUserAccessDetail)){
                // Set User's Data & Row Level Access.
                $this->objAccessLevel = $this->getUserAccessLevel($this->scope->id);

                // Basic user's access level 
                $arrBasicAccessLevel = [
                    "data_type_user_level_id" => 1,
                    "data_row_user_level_id" => 1
                ];
                
                // Add basic access level in array group
                $this->objAccessLevel->push((object)$arrBasicAccessLevel);
                
    	        // Set User's accessiable Data Rows.
    	        $this->objAccessiableRow = $this->getAccessiableRows($this->dataType->id, $this->objAccessLevel->pluck('data_row_user_level_id')->unique()->toArray());
                
                $this->objFilterCondition = $this->getDataFilterCondition($this->dataType->id, $this->objAccessLevel->pluck('data_type_user_level_id')->unique()->toArray());

                $this->strFilterQuery = $this->getDataFilterQuery();
                // Cache user access
                $arrUserAccess = [
                    "objAccessLevel" => $this->objAccessLevel,
                    "objAccessiableRow" => $this->objAccessiableRow,
                    "objFilterCondition" => $this->objFilterCondition,
                    "strFilterQuery" => $this->strFilterQuery,
                    "strPk" => $this->strPk
                ];
                // Set in Redis
                Redis::hset('user:' .$this->intUserId, $this->strScope .'_access', json_encode($arrUserAccess));
            }
            else{
                // Set data from cache
                $this->objAccessLevel = collect($objUserAccessDetail->objAccessLevel);
                $this->objAccessiableRow = $objUserAccessDetail->objAccessiableRow;
                $this->objFilterCondition = (array)$objUserAccessDetail->objFilterCondition;
                $this->strFilterQuery = $objUserAccessDetail->strFilterQuery;
                $this->strPk = $objUserAccessDetail->strPk;
            }
    	}
    }

    /**
     * Generate a where condition to filter a data based on user's access on a data.
     * @param 	object 	DataType
     * @param 	array 	UserLevel
     * @return 	array
     */
    // protected function getDataFilterCondition($dataType, $arrUserLevel){
    //     return DB::table('data_type_user_level')
    //         ->select('condition as where' , 'parameters')
    //         ->where('data_type_id', $dataType)
    //         ->whereIn('data_level_user_id', $arrUserLevel)
    //         ->where('is_deleted', 0)
    //         ->get()
    //         ->toArray();
    // }

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

    /**
     *	Returns an array of fields which can be accessiable by a user.
     * 	@param 	int 	DataTypeId
     * 	@param 	array 	User's FieldLeve access
     * 	@return array
     */
    public function getAccessiableRows($intDataType, $arrFieldLevelAccess){
        // dd($arrFieldLevelAccess);
        return DB::table('data_rows')
            ->leftJoin('data_groups', function($join){
                $join->on('data_rows.data_group_id', '=', 'data_groups.id')
                    ->on('data_groups.is_active', '=', DB::raw(1));
            })
            ->leftJoin('data_row_user_level', function($join){
                $join->on('data_rows.id', '=', 'data_row_user_level.data_row_id')
                    ->on('data_row_user_level.is_deleted', '=', DB::raw(0));
            })
            ->select('data_rows.id')
            ->where('data_groups.data_type_id', $intDataType)
            ->where(function($query)use($arrFieldLevelAccess){
                return $query->whereNull('data_row_user_level.data_row_id')
                    ->orWhere(function($query)use($arrFieldLevelAccess){
                        return $query->where('is_strict', 0)
                            ->where('data_row_user_level.row_level_user_id', '<=', max($arrFieldLevelAccess));
                    })
                    ->orWhere(function($query)use($arrFieldLevelAccess){
                        return $query->where('data_row_user_level.is_strict', 1)
                            ->whereIn('data_row_user_level.row_level_user_id', $arrFieldLevelAccess);
                    });
            })
            ->get()->pluck('id')->toArray();
    }

    /**
     * Returns filter conditions at data level.
     *
     * @param   object  $dataType   Instance of App\Model\DataType
     * @param   array   $arrUserLevel   User's level for a data
     * @return  array
     */
    protected function getDataFilterCondition($dataType, $arrUserLevel){
        $strWhere = '(';
        $params = [];

        // Get data level where clause.
        $objWheres = DB::table('data_type_user_level')
            ->select('condition' , 'parameters as params')
            ->where('data_type_id', $dataType)
            ->whereIn('data_level_user_id', $arrUserLevel)
            ->where('is_deleted', 0)
            ->get();
        foreach ($objWheres as $objWhere) {
            $arrParams = collect(json_decode($objWhere->params));
            foreach ($arrParams as $param) {
                $params[]=session($param, null);
            }
            // dd($params);
            $strWhere .= '( ' .$objWhere->condition .') OR';
        }
        if(strlen($strWhere) > 1){
            $strWhere = substr($strWhere, 0, -3) .')';
        }
        else{
            $strWhere = '';
        }

        return [
            'condition' => $strWhere,
            'params' => $params
        ];
    }

    protected function getDataFilterQuery(){
        // Model Created.
        $objModel = app($this->dataType->model_name);
        
        // Get joining tables data
        $objTblJoins = $this->dataType->joinTables;

        foreach ($objTblJoins as $objTbl) {
            $strJoinType = 'join';
            switch ($objTbl->join_type_id) {
                case 2:
                    $strJoinType = 'leftJoin';
                    break;
            }
            $objModel = $objModel->{$strJoinType}($objTbl->table_name, function($query)use($objTbl){
                $objConditions = json_decode($objTbl->conditions);
                foreach ($objConditions as $objCondition) {
                    $query = $query->on($objCondition->param1, $objCondition->condition, $objCondition->param2);
                }
            });
        }
        
        // Get from groups ids
        $arrDataGroupIds = $this->dataType->dataGroup->pluck('id')->toArray();
        
        // Data level filter condition
        $objWhereClause = $this->objFilterCondition;
        $this->strPk = $objModel->getKeyName();  

        $objModel = $objModel->selectRaw($this->strPk);
        if(!empty($objWhereClause["condition"])){
            $objModel = $objModel->whereRaw($objWhereClause["condition"], $objWhereClause["params"]);
            return $objModel->toSql() . " AND " .$this->strPk ." IN ('";
        }
        return $objModel->toSql() .(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(app($this->dataType->model_name)))? ' AND ' : ' ') .' WHERE ' .$this->strPk ." IN ('";
    }
}

