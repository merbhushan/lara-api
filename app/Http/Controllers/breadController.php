<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\Scope;
use App\Model\DataType;
use App\Model\DataRow;
use App\Model\Session;
use DB;
use App\Http\Controllers\accessManagerController;
use App\Http\Controllers\FileManager;

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
        $intRecordsPerPage = !empty($request->recordsPerPage) && ($request->recordsPerPage > 0 || strtoupper($request->recordsPerPage) === 'ALL')?$request->recordsPerPage:20;
        
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
        $objWhereClause = $this->objUserAccess->objFilterCondition;
        
        // Get Browsable fields which user have a access.
        $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, group_concat(concat(" ", field, " as ", alias)) as fields'))
            ->isBrowsable()
            ->whereIn('id', $this->objUserAccess->objAccessiableRow)
            ->groupBy('relationship_id')
            ->orderBy('relationship_id')
            ->get();

        if($objFields->count()){
            // Get Relationships 
            $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('relationship_type_id');
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

            // Get Searchable & Orderable fields
            $objSearchableFields = DataRow::select(DB::raw('field, alias, details'))
                ->isSearchable()
                ->whereIn('id', $this->objUserAccess->objAccessiableRow)
                ->get();

            foreach ($objSearchableFields as $objField) {
                if(!is_null($request->{$objField->alias})){
                    $strSearchType = empty($objField->details->search->type)?'': $objField->details->search->type;
                    switch ($strSearchType) {
                        case 'checkEmpty':
                            $objModel = $objModel->where($objField->field, '');
                            break;
                        default:
                            if(is_array($request->{$objField->alias})){
                                $arrSearchData = $request->{$objField->alias};
                            }
                            else{
                                $arrSearchData[] = $request->{$objField->alias};;
                            }
                            $objModel = $objModel->whereIn($objField->field, $arrSearchData);
                            break;
                    }
                }
            }

            // To provide all records in a response. set a big numbre in recordsPerPage Variable.
            if(strtoupper($intRecordsPerPage) === 'ALL'){
                $intRecordsPerPage = 1000000000;
            }

            // Apply orderBy
            if(!is_null($request->order)){
                $objOrders = json_decode($request->order);
                
                // Get Orderable fields
                $objOrderableFields = DataRow::select('field', 'alias', 'details')
                    ->isOrderable()
                    ->whereIn('id', $this->objUserAccess->objAccessiableRow)
                    ->get()
                    ->keyBy('alias');
                
                foreach ($objOrders as $strAlias => $strSortingType) {
                    if(isset($objOrderableFields[$strAlias])){
                        $strAlias = (isset($objOrderableFields[$strAlias]->details->search->useField) && $objOrderableFields[$strAlias]->details->search->useField == 1)?$objOrderableFields[$strAlias]->field:$strAlias;
                        switch (strtoupper($strSortingType)) {
                            case "ASC":
                                $objModel = $objModel->orderBy($strAlias, 'asc');
                                break;
                            
                            case "DESC":
                                $objModel = $objModel->orderBy($strAlias, 'desc');
                                break;
                        }
                    }
                }
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
        $objFields = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, details, field, alias, concat(field, " as ", alias) as response_field'))
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
                if(!empty($objModelField->details->store->value)){
                    eval("\$request->{\$objModelField->alias} = " .$objModelField->details->store->value);
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
            $objRelationships = $this->objUserAccess->dataType->relationships->keyBy('relationship_type_id');

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
                        case 1:
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

            // Generate a response object
            foreach ($objModelFields as $objModelField) {
                $objModel->{$objModelField->alias} = $objModel->{$objModelField->field};
                unset($objModel->{$objModelField->field});
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
        $objWhereClause = $this->objUserAccess->objFilterCondition;

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
            $arrRawFields = [];
            $strFields = '';

            foreach ($objFields as $objField) {
                if($objField->relationship_id === 0){
                    // Main Object Fields
                    $strFields = $objField->fields;
                }
                else{
                    $objRelationship = $objRelationships[$objField->relationship_id];
                    
                    $arrRawFields[] = $objRelationship->primary_field;
                    // Generate Relationship Query
                    $objModel = $objModel->with([$objRelationship->name => function($query)use($objField, $objRelationship){
                        $query->selectRaw($objRelationship->secondary_field .', ' .$objField->fields)
                            ->whereRaw(!empty($objRelationship->condition)? $objRelationship->condition : 1)
                            ->orderByRaw(!empty($objRelationship->order_by)?$objRelationship->order_by: 1);
                    }]);
                }
            }
            // $objUser = $objModel->find(1);
            // dd($objUser->resume()->toSql());
            $objModel = $objModel->selectRaw($strFields)
                ->select($arrRawFields);

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
        // update a user's Access.
        $this->objUserAccess->updateUsersAccess();

        // Get a request data is acessiable or not
        $arrAccessiableData = $this->getAccessiableIds($request, [$id]);

        if(count($arrAccessiableData)){
            // fId in request will decides a  types of operation was being performed on a user's request.
            // If it's not empty then it's indicates that some action will be performed on a Model Or relationship.
            if(!empty($request->fId) && $request->fId>0){
                return $this->performAction($request, $id);
            }

            // Model Created
            $objModel = app($this->objUserAccess->dataType->model_name)::find($id);
            
            if(!is_null($objModel)){
                // Get updatable fields which user have a access.
                $objFields = DataRow::select(DB::raw('id, IFNULL(relationship_id, 0) as relationship_id, details, field, alias, is_pk, edit_element_type_id as element_type'))
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
                    // Exclude fields of type file
                    $objModelFields = $objFields[0]->filter(function($value, $key){ return $value->element_type != '4';});
                    
                    if($objModelFields->count()){
                        $arrModelFields = [];

                        // Set Model's data
                        foreach ($objModelFields as $objModelField) {
                            // if a parameter value should being calculated then algorithm is being passed in details and using eval it's being calculated and saved in request object.
                            
                            if(!empty($objModelField->details->update->value)){
                                eval("\$request->{\$objModelField->alias} = " .$objModelField->details->update->value);
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
                        $objResponse = app($this->objUserAccess->dataType->model_name)->select($arrModelFields)->find($id);
                    }

                    // Get File fields of a Model
                    $objModelFiles = $objFields[0]->filter(function($value, $key){ return $value->element_type == '4';});

                    if($objModelFiles->count()){
                        foreach ($objModelFiles as $objFile) {
                            if(($request->hasFile($objFile->alias))){
                                $blnIsMultiple = isset($objFile->details->file->is_multiple)?$objFile->details->file->is_multiple: 0;
                                $intModuleRefId = null;
                                if(isset($objFile->details->file->module_ref_id)){
                                    eval("\$intModuleRefId = " .$objFile->details->file->module_ref_id .";");
                                }
                                $intModuleId = isset($objFile->details->file->module_id)?$objFile->details->file->module_id: null;
                                $strPath = isset($objFile->details->file->file_path)?$objFile->details->file->file_path: env('document_path', 'test/meditab/');

                                $objFileManager = new FileManager();

                                $objModel->{$objFile->alias} = $objFileManager->storeFile($objFile->alias, $intModuleId, $intModuleRefId, $request, $blnIsMultiple, $strPath);                                
                            }
                        }
                    }
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

                                // Grouping fields with pk
                                $objFields = $objField->groupBy('is_pk');
                                
                                // Generate Relationship model.
                                $objRelationshipModel = $objModel->{$objRelationship->name}();
                                
                                $blnIsNew = 1;

                                for ($i=0; $i < $intCount; $i++) {
                                    if(!empty($objFields[1]) && !empty($request->{$objFields[1]->alias}[$i])){
                                        $objRelationshipModel = $objRelationshipModel->find($request->{$objDataRow->alias}[$i]);
                                        $blnIsNew = 0;
                                    }
                                    else{
                                        $objRelationshipModel = $objRelationshipModel->create();
                                    }

                                    // Set attributes value in model
                                    foreach ($objFields[0] as $objDataRow) {
                                        if(!empty($objDataRow->details->update->value)){
                                            $request->{$objDataRow->alias} = [$i => session('user_id', null)];
                                            eval("\$attributeValue = " .$objDataRow->details->update->value .";");
                                            eval("\$request->{\$objDataRow->alias} = [ \$i =>" .$attributeValue ." ];");
                                        }
                                        if(!empty($request->{$objDataRow->alias}[$i]) || $blnIsNew){
                                            $objRelationshipModel->{$objDataRow->field} = $request->{$objDataRow->alias}[$i];
                                        }
                                    }
                                    
                                    $objRelationshipModel->save();
                                }

                                foreach ($objFields[0] as $objDataRow) {
                                    if(!empty($objRelationshipModel->{$objDataRow->field})){
                                        $objRelationshipModel->{$objDataRow->alias} = $objRelationshipModel->{$objDataRow->field};
                                        unset($objRelationshipModel->{$objDataRow->field});
                                    }
                                }
                                
                                // Update Relationship data
                                $objModel->{$objRelationship->name} = $objRelationshipModel;
                                break;
                        }
                    }
                }

                // Generate a response object
                if(isset($objModelFields )){
                    foreach ($objModelFields as $objModelField) {
                        $objModel->{$objModelField->alias} = $objModel->{$objModelField->field};
                        unset($objModel->{$objModelField->field});
                    }                    
                }

                // Return a model in response
                return $this->httpResponse($objModel);
            }
        }
        // Redirect to Access Denied error route.
        return redirect ('api/error/ACCESS_DENIED');
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

        // Get a request data is acessiable or not
        $arrAccessiableData = $this->getAccessiableIds($request, [$id]);

        if(count($arrAccessiableData)){
            // Model Created
            $objModel = app($this->objUserAccess->dataType->model_name)::find($id);

            if($objModel){
                return $this->deleteData($objModel);
            }
        }
        return redirect('api/error/NO_DATA_DELETED');
    }

    public function deleteData($objModel){
        // Update updated by
        if(isset($objModel->blnUpdateDeleteByInfo) && $objModel->blnUpdateDeleteByInfo == 1 && !empty(session('user_id')) ){
            $objModel->deleted_by = session('user_id', null);
            $objModel->save();
        }
        $objModel->delete();
        return redirect('api/error/RECORD_DELETED');
    }

    /**
     *  This function is being used to perform action using update.
     * @param   $request
     * @param   $id
     */
    protected function performAction(Request $request, $id){
        // Model Created
        $objModel = app($this->objUserAccess->dataType->model_name)::find($id);
        
        // Get actionable fields which user have a access.
        $objAction = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, field, alias, is_pk, details'))
            ->isUpdatable()
            ->whereIn('id', $this->objUserAccess->objAccessiableRow)
            ->where('id', $request->fId)
            ->whereIn('edit_element_type_id', [12])
            ->first();

        if($objAction){
            // If relationship id is not zero then action will be performed on relationship model else on a requested model
            //rId in request varaible will decides a relationship model selection so it can not be empty or zero while performing action on relationship model.
            if($objAction->relationship_id > 0 && !empty($request->rId) && $request->rId >0){
                $objRelationship = $this->objUserAccess->dataType->relationships->find($objAction->relationship_id);
                $objActionModel = $objModel->{$objRelationship->name}()->whereRaw($objRelationship->condition)->find($request->rId);
            }
            else{
                $objActionModel = $objModel;
            }
             
            if($objActionModel){
                // Base on type we will decides a what should be set in a value. if type was not being set then set provided value.
                if(empty($objAction->details->action->type) && !empty($objAction->details->action->value)){
                    $value = $objAction->details->action->value;
                }
                else{
                    switch ($objAction->details->action->type) {
                        case 'timestamp':
                            $value = date('Y-m-d H:i:s');
                            break;
                        case 'date':
                            $value = date('Y-m-d');
                            break;
                        case 'null':
                            $value = null;
                            break;
                        case 'destroy':
                            return $this->deleteData($objActionModel);
                            break;
                    }
                }
                
                // Generate error if a value is not set for an action item.
                if(isset($value)){
                    $objActionModel->{$objAction->field} = $value;
                    // Update modified_by attribute
                    if(isset($objActionModel->blnStoreUserInfo) && $objActionModel->blnStoreUserInfo == 1 && !empty(session('user_id'))){
                        $objActionModel->modified_by = session('user_id');
                    }
                    $objActionModel->save();
                    return redirect('api/error/RECORD_UPDATED_SUCCESSFULLY');
                }
                return redirect('api/error/INVALID_ACTION_ITEM');
            }
            return redirect('api/error/INVALID_ACTION_KEY');
        }
        // Return access denied for a action error
        return redirect('api/error/ACTION_ACCESS_DENED');
    }

    /**
     * This function is used to perform actions on multiple items.
     */
    public function action(Request $request){
        if(isset($request->ids) && is_array($request->ids) && count($request->ids)){
            // update a user's Access.
            $this->objUserAccess->updateUsersAccess();
            
            // Get actionable fields which user have a access.
            $objAction = DataRow::select(DB::raw('IFNULL(relationship_id, 0) as relationship_id, field, alias, is_pk, details'))
                ->isUpdatable()
                ->whereIn('id', $this->objUserAccess->objAccessiableRow)
                ->where('id', $request->fId)
                ->whereIn('edit_element_type_id', [12])
                ->first();

            if($objAction){
                // Filter Accessiable data
                $arrAccessiableData = $this->getAccessiableIds($request, $request->ids);
                
                if(count($arrAccessiableData)){
                    // Based on type we will decides a what should be set in a value. if type was not being set then set provided value.
                    if(empty($objAction->details->action->type) && !empty($objAction->details->action->value)){
                        $value = $objAction->details->action->value;
                    }
                    else{
                        switch ($objAction->details->action->type) {
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

                    // Generate error if a value is not set for an action item.
                    if(isset($value)){
                        // Get object of action model
                        $objActionModel = app($this->objUserAccess->dataType->model_name);
                        $objActionModel->whereIn($objActionModel->getKeyName(), $arrAccessiableData)
                            ->update([$objAction->field => $value]);
                        return redirect('api/error/RECORD_UPDATED_SUCCESSFULLY');
                    }

                    return redirect('api/error/INVALID_ACTION_ITEM');                
                }
                return redirect('api/error/INVALID_ACTION_KEY');
            }
            // Return access denied for a action error
            return redirect('api/error/ACTION_ACCESS_DENED');
        }
        return redirect('api/error/INVALID_INPUT_DATA');
    }

    protected function getAccessiableIds(Request $request, $arrIds=[]){
        if(!empty($arrIds)){
            // Get Filter query;
            $strQuery = $this->objUserAccess->strFilterQuery;

            // Filter Accessiable ids
            $strQuery .= implode("', '", $arrIds) ."')";

            return collect(DB::select(DB::raw($strQuery), $this->objUserAccess->objFilterCondition["params"]))->pluck($this->objUserAccess->strPk)->toArray();
        }
        else{
            return [];
        }
    }
}

    