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
        $intRecordsPerPage = empty($request->recordsPerPage)?20:$request->recordsPerPage;
        
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Model Created.
        $objModel = app($this->objUserAccess->dataType->model_name);

        // Get joining tables data
        $objTblJoins = $this->objUserAccess->dataType->joinTables;

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

        // Data level filter condition
        $objWhereClause = $this->getDataFilterCondition($this->objUserAccess->dataType->id, $this->objUserAccess->objAccessLevel->pluck('data_type_user_level_id')->unique()->toArray());
        // dd($objWhereClause);
        // Get Browsable fields which user have a access.
        $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, group_concat(concat(" ", field, " as ", alias)) as fields'))
            ->isBrowsable()
            ->whereIn('id', $this->objUserAccess->objAccessiableRow)
            ->groupBy('relationship_id')
            ->orderBy('relationship_id')
            ->get();

        if($objFields->count()){
            // Get Relationships 
            $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('id');
            // Initialize variables for fields.
            $strRawFields = '';
            $strFields = '';

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
            $objModel = $objModel->selectRaw($strRawFields .$strFields);

            if(!empty($objWhereClause["condition"])){
                $objModel = $objModel->whereRaw($objWhereClause["condition"], $objWhereClause["params"]);
            }

            // Fetch Result set
            $objResults = $objModel->paginate($intRecordsPerPage);
            
            // Model Created.
            return $this->httpResponse($objResults);
        }
        // Redirect to No data found.
        return redirect('api/error/BROWSABLE_FIELDS_ACCESS_DENIED');
        
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
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Get storable fields which user have a access.
        $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, details, field, alias'))
            ->isStorable()
            ->whereIn('id', $this->objUserAccess->objAccessiableRow)
            ->orderBy('relationship_id')
            ->get()
            ->groupBy('relationship_id');

        // Get Model's fields
        $objModelFields = $objFields[0];

        if($objModelFields->count()){
            // Model Created
            $objModel = app($this->objUserAccess->dataType->model_name);
            
            // Set Model's data
            foreach ($objModelFields as $objModelField) {
                // if a parameter value should being calculated then algorithm is being passed in details and using eval it's being calculated and saved in request object.
                $objDetails = json_decode($objModelField->details);
                if(!empty($objDetails->store->value)){
                    eval("\$request->{\$objModelField->alias} = " .$objDetails->store->value);
                }
                
                // if a parameter is not in request object then set it to null
                $objModel->{$objModelField->field} = isset($request->{$objModelField->alias})? $request->{$objModelField->alias} : null;
            }

            // Update created by & updated by info
            if(isset($objModel->blnStoreUserInfo) && $objModel->blnStoreUserInfo == 1){
                $objModel->created_by = session('user_id', null);
                $objModel->updated_by = session('user_id', null);
            }

            // Update Model
            $objModel->save();

            // Get Relationships 
            $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('id');

            foreach ($objFields as $key => $objField) {
                // Key 0 is for Primary model fields && if relationship fields have many counts 
                if($key !== 0 && $objField->count()){
                    $objRelationship = $objRelationships[$key];
                    // Perform action based on relationship type
                    switch ($objRelationship->relationship_type_id) {
                        case 4:
                            // Sync Pivot table for Many to Many relationship
                            $objModel->{$objRelationship->name}()->sync($request->{$objField[0]->alias});
                            break;
                        
                        case 2:
                            // Set Count
                            $intCount = !empty($request->{$objField[0]->alias})?count($request->{$objField[0]->alias}):0;
                            $arrData = [];

                            for ($i=0; $i < $intCount; $i++) {
                                foreach ($objField as $objDataRow) {
                                    if(!$objDataRow->is_pk){
                                        $arrData[$i][$objDataRow->field] = $request->{$objDataRow->alias}[$i];                                        
                                    }
                                }

                                // Inserte data in hasMnay relationship
                                    $objRelationshipModel = $objModel->{$objRelationship->name}()->create($arrData[$i]);
                            }

                            // Update hasMany Relationship data in Model
                            $objModel->{$objRelationship->name};
                            break;
                    }
                }
            }

            // Return a model in response
            return $this->httpResponse($objModel);
        }
        else{
            return redirect('api/error/STORABLE_FIELDS_ACCESS_DENIED');
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Model Created.
        $objModel = app($this->objUserAccess->dataType->model_name);

        // Get joining tables data
        $objTblJoins = $this->objUserAccess->dataType->joinTables;

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
        $arrDataGroupIds = $this->objUserAccess->dataType->dataGroup->pluck('id')->toArray();
        
        // Data level filter condition
        $objWhereClause = $this->getDataFilterCondition($this->objUserAccess->dataType->id, $this->objUserAccess->objAccessLevel->pluck('data_type_user_level_id')->unique()->toArray());

        // Get Browsable fields which user have a access.
        $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, group_concat(concat(" ", field, " as ", alias)) as fields'))
            ->isViewable()
            ->whereIn('id', $this->objUserAccess->objAccessiableRow)
            ->groupBy('relationship_id')
            ->orderBy('relationship_id')
            ->get();

        if($objFields->count()){
            // Get Relationships 
            $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('id');
            // Initialize variables for fields.
            $strRawFields = '';
            $strFields = '';

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
            $objModel = $objModel->selectRaw($strRawFields .$strFields);

            if(!empty($objWhereClause["condition"])){
                $objModel = $objModel->whereRaw($objWhereClause["condition"], $objWhereClause["params"]);
            }

            // Fetch Result set
            $objResults = $objModel->find($id);
            
            if(!empty($objResults)){
                return $this->httpResponse($objResults);
            }
            // Redirect to No data found.
            return redirect('api/error/NO_DATA_FOUND');
        }
        // Redirect to No data found.
        return redirect('api/error/VIEWABLE_FIELDS_ACCESS_DENIED');
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
        // fId in request will decides a  types of operation was being performed on a user's request.
        // If it's not empty then it's indicates that some action will be performed on a Model Or relationship.
        if(!empty($request->fId) && $request->fId>0){
            return $this->performAction($request, $id);
        }

        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Model Created
        $objModel = app($this->objUserAccess->dataType->model_name)::find($id);

        if(!is_null($objModel)){
            // Get updatable fields which user have a access.
            $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, field, alias, is_pk'))
                ->isUpdatable()
                ->whereIn('id', $this->objUserAccess->objAccessiableRow)
                ->whereNotIn('edit_element_type_id', [12])
                ->orderBy('relationship_id')
                ->get()
                ->groupBy('relationship_id');

            if(!count($objFields)){
                return redirect('api/error/UPDATABLE_FIELDS_ACCESS_DENIED');
            }

            // Get Model's fields
            if(!empty($objFields[0])){
                $objModelFields = $objFields[0];
                
                $arrModelFields = [];

                // Set Model's data
                foreach ($objModelFields as $objModelField) {
                    // if a parameter value should being calculated then algorithm is being passed in details and using eval it's being calculated and saved in request object.
                    $objDetails = json_decode($objModelField->details);
                    if(!empty($objDetails->update->value)){
                        eval("\$request->{\$objModelField->alias} = " .$objDetails->update->value);
                    }
                    // if parameters are present in request then it's being updated else remain as it is.
                    if(isset($request->{$objModelField->alias})){
                        $objModel->{$objModelField->field} = $request->{$objModelField->alias};
                    }                    
                    $arrModelFields[]=$objModelField->field .' as ' .$objModelField->alias;
                }

                // Update updated by
                if(isset($objModel->blnStoreUserInfo) && $objModel->blnStoreUserInfo == 1){
                    $objModel->updated_by = session('user_id', null);
                }

                // Update Model
                $objModel->save();

                // get updated fields of model to provide in a response.
                $objResponse = app($this->objUserAccess->dataType->model_name)::selectRaw(implode(", ", $arrModelFields))->find($id);
            }
            else{
                $objResponse = app($this->objUserAccess->dataType->model_name);
                $objResponse = $objResponse->selectRaw($objResponse->getKeyName())->find($id);
            }

            // Get Relationships 
            $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('id');

            foreach ($objFields as $key => $objField) {
                if($key !== 0){
                    // dd($key);
                    $objRelationship = $objRelationships[$key];
                    // Perform action based on relationship type
                    switch ($objRelationship->relationship_type_id) {
                        case 4:
                            // Sync Pivot table for Many to Many relationship
                            $objPivot = $objModel->{$objRelationship->name}()->sync($request->{$objField[0]->alias});

                            // Update relationship data in a primary model
                                $objModel->{$objRelationship->name} = $objPivot;
                            break;
                        
                        case 2:
                            // Set Count
                            $intCount = !empty($request->{$objField[0]->alias})?count($request->{$objField[0]->alias}):0;

                            $arrData = [];

                            for ($i=0; $i < $intCount; $i++) {
                                foreach ($objField as $objDataRow) {
                                    if($objDataRow->is_pk){
                                        // Set PK id to 0 if empty
                                        $intObjPkId = !empty($request->{$objDataRow->alias}[$i])? $request->{$objDataRow->alias}[$i] : 0;
                                    }
                                    else{
                                        $arrData[$i][$objDataRow->field] = $request->{$objDataRow->alias}[$i];                                        
                                    }
                                }

                                // If PK is not empty then update a data else create
                                // Here updateOrCreate method was not working so we need to do separately
                                if($intObjPkId > 0){
                                    // Relationship model object
                                    $objRelationshipModel = $objModel->{$objRelationship->name}()->find($intObjPkId);
                                    // If not empty or null then update
                                    if($objRelationshipModel){
                                        $objRelationshipModel->update($arrData[$i]);
                                    }
                                }
                                else{
                                    $objRelationshipModel = $objModel->{$objRelationship->name}()->create($arrData[$i]);
                                }
                            }

                            // Update Relationship data
                            $objModel->{$objRelationship->name};
                            
                            break;
                    }
                }
            }

            // Return a model in response
            return $this->httpResponse($objResponse);
        }
        else{
            // Redirect to invalid model error route.
            return redirect ('api/error/INVALID_MODEL');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Model Created
        $objModel = app($this->objUserAccess->dataType->model_name)::find($id);

        if($objModel){
            // Update updated by
            if(isset($objModel->blnUpdateDeleteByInfo) && $objModel->blnUpdateDeleteByInfo == 1){
                $objModel->deleted_by = session('user_id', null);
            }
            $objModel->delete();
            return redirect('api/error/RECORD_DELETED');
        }
        return redirect('api/error/NO_DATA_DELETED');
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

    /**
     *  This function is being used to perform action using update.
     * @param   $request
     * @param   $id
     */
    protected function performAction(Request $request, $id){
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Model Created
        $objModel = app($this->objUserAccess->dataType->model_name)::find($id);
        // dd($this->objUserAccess->objAccessiableRow);
        if(!is_null($objModel)){
            // Get updatable fields which user have a access.
            $objAction = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, field, alias, is_pk, details'))
                ->isUpdatable()
                ->whereIn('id', $this->objUserAccess->objAccessiableRow)
                ->where('id', $request->fId)
                ->whereIn('edit_element_type_id', [12])
                ->first();

            if($objAction){
                // If relationship id is not zero then action will be performed on relationship model else on a requested model
                // rId in request varaible will decides a relationship model selection so it can no be zero empty or a zero.
                if($objAction->relationship_id > 0 && !empty($request->rId) && $request->rId >0){
                    $objRelationship = $this->objUserAccess->dataType->relationships->find($objAction->relationship_id);
                    $objActionModel = $objModel->{$objRelationship->name}()->find($request->rId);
                }
                else{
                    $objActionModel = $objModel;
                }
                
                if($objActionModel){
                    $objDetails = json_decode($objAction->details);

                    // Base on type we will decides a what should be set in a value. if type was not being set then set provided value.
                    if(empty($objDetails->action->type)){
                        $value = empty($objDetails->action->value)?null:$objDetails->action->value;
                    }
                    else{
                        switch ($objDetails->action->type) {
                            case 'timestamp':
                                $value = date('Y-m-d H:i:s');
                                break;
                            case 'date':
                                $value = date('Y-m-d');
                                break;
                            case 'null':
                                $value = null;
                                break;
                        }
                    }
                    $objActionModel->{$objAction->field} = $value;
                    $objActionModel->save();
                    return $this->httpResponse($objActionModel);
                }
                return redirect('api/error/INVALID_ACTION_KEY');
            }
            // Return access denied for a action error
            return redirect('api/error/ACTION_ACCESS_DENED');
        }
    }
}
