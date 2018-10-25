<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Scope;
use App\Model\DataType;
use App\Model\DataRow;
use App\Model\Session;
use DB;
use App\Http\Controllers\accessManagerController;

class breadController extends Controller
{
    public $objUserAccess;

    public function __construct(){
        $this->objUserAccess = new accessManagerController();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Model Created.
        $objModel = app($this->objUserAccess->dataType->model_name);

        // Get joining tables data
        $objTblJoins = $this->objUserAccess->dataType->joinTables;

        // dd($objTblJoins);
        foreach ($objTblJoins as $objTbl) {
            $objModel = $objModel->join($objTbl->table_name, function($query)use($objTbl){
                $objConditions = json_decode($objTbl->conditions);
                foreach ($objConditions as $objCondition) {
                    $query = $query->on($objCondition->param1, $objCondition->condition, $objCondition->param2);
                }
            });
        }

        // Get from groups ids
        $arrDataGroupIds = $this->objUserAccess->dataType->dataGroup->pluck('id')->toArray();
        
        if(!empty($request->getHeaders) && $request->getHeaders==1){
            $objHeaders = DataRow::select(DB::raw('alias as value, display_name as text, listing_details as details'))
                ->isBrowsable()
                ->skipHidden()
                ->whereIn('id', $this->objUserAccess->objAccessiableRow)
                ->orderBy('listing_seq_no')
                ->get()
                ->toArray();
            return $this->httpResponse($objHeaders);
        }

        // Data leve filter condition
        $objWhereClause = $this->getDataFilterCondition($this->objUserAccess->dataType->id, $this->objUserAccess->objAccessLevel->pluck('data_type_user_level_id')->unique()->toArray());


        // Get Browsable fields which user have a access.
        $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, group_concat(concat(" ", field, " as ", alias)) as fields'))
            ->isBrowsable()
            ->whereIn('id', $this->objUserAccess->objAccessiableRow)
            ->groupBy('relationship_id')
            ->orderBy('relationship_id')
            ->get();

        // Get Relationships 
        $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('id');
        $strRawFields = '';

        foreach ($objFields as $objField) {
            if($objField->relationship_id === 0){
                // Main Object Fields
                $strFields = $objField->fields;
            }
            else{
                $objRelationship = $objRelationships[$objField->relationship_id];
                $strRawFields .= $objRelationship->primary_field;
                // Generate Relationship Query
                $objModel = $objModel->with([$objRelationship->name => function($query)use($objField, $objRelationship){
                    $query->selectRaw($objRelationship->secondary_field .$objField->fields)
                        ->whereRaw(!empty($objRelationship->condition)? $objRelationship->condition : 1);
                }]);
            }
        }

        // Fetch Result set
        $objResults = $objModel->selectRaw($strRawFields .$strFields)->get();

        return $this->httpResponse($objResults);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // dd($this->objUserAccess);

        $objDataType = DataType::with(['dataGroup' => function($query){
            $query->select('id', 'data_type_id')->with(['dataRow' =>function($query){
                $query->select(DB::raw('id, data_group_id, concat(\'[\', group_concat(json_insert(details, \'$.data\', alias, \'$.type\', add_element_type_id, \'$.gridClass\', gridClass)), \']\') AS group_fields'))->whereIn('id', $this->objUserAccess->objAccessiableRow)->whereRaw('`data_rows`.`add` = 1')->groupBy('row_id')->orderByRaw('row_id, seq_no');
            }]);
        }])->find($this->objUserAccess->dataType->id)->toArray();

        return $this->httpResponse($objDataType);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // dd($this->objUserAccess);

        $objDataType = DataType::with(['dataGroup' => function($query){
            $query->select('id', 'data_type_id')->with(['dataRow' =>function($query){
                $query->select(DB::raw('id, data_group_id, concat(\'[\', group_concat(json_insert(details, \'$.data\', alias, \'$.type\', edit_element_type_id, \'$.gridClass\', gridClass)), \']\') AS group_fields'))->whereIn('id', $this->objUserAccess->objAccessiableRow)->whereRaw('`data_rows`.`edit` = 1')->groupBy('row_id')->orderByRaw('row_id, seq_no');
            }]);
        }])->find($this->objUserAccess->dataType->id)->toArray();

        return $this->httpResponse($objDataType);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Returns filter conditions at data level.
     *
     * @param   object  $dataType   Instance of App\Model\DataType
     * @param   array   $arrUserLevel   User's level for a data
     * @return  array
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
