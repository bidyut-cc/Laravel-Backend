<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController {
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $model;
    public $resource;
    public $storeId;
    public $additional_rules = [];

    public function __construct(Request $request) {
        $this->model = $request->controllerName;
    }

    public function getModelNamespace() {
        $modelString = 'App\Models\\' . ucfirst(Str::singular($this->model));
        return $modelString;
    }

    public function getFields() {
        $resource = $this->getModel();
        $fields_array = $resource->processFields($resource->fieldsArray);
        return $fields_array;
    }

    public function getModel($id = null) {
        $modelString = $this->getModelNamespace();
        if ($id) {
            $connectionObject = $modelString::find($id);
            $msg = 'existing';
        } else {
            $connectionObject = new $modelString();
            $msg = 'new';
        }
        return $connectionObject;
    }


    public function getListing(Request $request) {
        try {
            if($this->model == 'timesheets'){
                return array('results' => [], 'fields' => []);
            }
             return array('results' => $this->getModel()->getListing($request), 'fields' => $this->getFields());
        }catch(Exception $e){
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function createView() {
        return array('fields' => $this->getFields());
    }

    public function save(Request $request) {
        $this->setAdditionalRules($this->getModel()->additionalRulesArray ?? []);
        $validate = $this->validateRequest($request);
        if ($validate) {
            return $validate;
        }
        try {
            $resource = $this->getModel();
            $request->merge(['resource' => $resource]);
            $result = $resource->publish($request);
            return array('results' => $result);
        } catch (\PDOException $e) {
            Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong with your data. Please check and try again.',
                'exception' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!'
            ], 422);
        }
    }

    public function view(Request $request) {
        try{
            $result = $this->getModel()->view($request);
            $fields = array();
            if ($this->getFields()) {
                $fields = $this->getFields();
                foreach ($fields as $key => $field) {
                    if (isset($result['result']->$key) && $field['type'] === 'autocomplete' && array_key_exists('related_table', $field) && array_key_exists('multiple', $field) && !$field['multiple']) {
                        $data = $result['result']->$key;
                        $query = DB::table($field['related_table'])->find($data);
                        if ($query) {
                            $column = $field['searchable'];
                            array_push($fields[$key]['options'], array('index' => $query->id, 'value' => $query->$column));
                        }
                    }
                }
            }
            return array('results' => $result, 'fields' => $fields);
        }catch(Exception $e){
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 422);
        }

    }

    public function update(Request $request, $id) {
        $this->setAdditionalRules($this->getModel()->additionalRulesArray ?? []);
        $validate = $this->validateRequest($request);
        if ($validate) {
            return $validate;
        }
        try {
             $result = $this->getModel()->revise($request, $id);
            return array('results' => $result);
        } catch (\PDOException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong with your data. Please check and try again.'
            ], 422);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!'
            ], 422);
        }
    }

    public function delete(Request $request) {
        $resourceModel = $this->getModel();
        $result = $resourceModel->remove($request);
        return array('status' => true, 'results' => $result);
    }

    public function deletePermanently(Request $request) {
        $resourceModel = $this->getModel();
        $result = $resourceModel->deletePermanently($request);
        return array('results' => $result);
    }

    public function restore(Request $request) {
        $resourceModel = $this->getModel();
        $result = $resourceModel->revert($request);
        return array('results' => $result);
    }

    public function fetchDropdownOptions(Request $request) {
        $resourceModel = $this->getModel();
        $result = $resourceModel->fetchDropdownOptions($request);
        return array('results' => $result);
    }

    /**
     * Get Validation Rules for controller
     * @param $request
     * @return array
     */
    public function getValidationRules(Request $request) {
        $rules = [];
        $fields_array = $this->getModel()->fieldsArray;
        $fields_collection = collect($fields_array);
        $required_fields = $fields_collection->filter(function ($field) {
            return !(isset($field['show_in_form']) && $field['show_in_form'] == false);
        });
        foreach ($required_fields as $required_field) {
            $field_name = $required_field['field_name'];
            if ($required_field['required'] == true) {
                $rules[$field_name][] = 'required';
            }
            if ($required_field['type'] == 'email') {
                $rules[$field_name][] = 'email';
            } elseif ($required_field['type'] == 'number') {
                $rules[$field_name][] = 'numeric';
            } elseif ($required_field['type'] == 'checkbox') {
                $rules[$field_name][] = 'array|min:1';
            }

            if (isset($required_field['server_validation'])) {
                if (!isset($rules[$field_name])) {
                    $rules[$field_name] = [$required_field['server_validation']];
                } else {
                    $rules[$field_name] = array_merge_recursive($rules[$field_name], $required_field['server_validation']);
                }
            }
        }
        return $rules;
    }

    public function validateRequest($request)
    {
        $baseRules = (array) $this->getValidationRules($request);
        $additionalRules = (array) $this->additional_rules;
    
        // merge per-field ― supports strings OR arrays
        foreach ($additionalRules as $field => $rule) {
            if (isset($baseRules[$field])) {
    
                // Convert to array & merge without duplication
                $baseRules[$field] = array_unique(array_merge(
                    (array)$baseRules[$field],
                    (array)(is_array($rule) ? $rule : explode('|', $rule))
                ));
    
            } else {
                $baseRules[$field] = $rule;
            }
        }
    
        // Make validator compatible: convert array rules to string form
        foreach ($baseRules as $key => $rule) {
            if (is_array($rule)) {
                $baseRules[$key] = implode('|', $rule);
            }
        }
    
        $validator = Validator::make($request->all(), $baseRules);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
    
        return null; // validation success
    }
    

    public function setAdditionalRules($additional_rules) {
        $this->additional_rules = $additional_rules;
    }

    public function change(Request $request) {
        $resourceModel = $this->getModel();
        return $result = $resourceModel->change($request);
    }
}
