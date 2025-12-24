<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity;

trait Crud {

	public function getModelNamespace() {
		$modelString = 'App\Models\\' . ucfirst(Str::singular($this->model));
		return $modelString;
	}

	public function getModel($id = null) {
		$modelString = $this->getModelNamespace();
		if ($id) {
			$connectionObject = $modelString::find($id);
		} else {
			$connectionObject = new $modelString;
		}
		return $connectionObject;
	}


	public function getListing(Request $request) {
		$show = $request->show ? $request->show : $this->defaultPaginate;
		$query = $this->getListQuery($request);
		//$resultsTotalCount = $query->count();
		$results = $query->paginate($show);
		//return array('resultsTotalCount' => $resultsTotalCount, 'results' => $results);
		return array('results' => $results);
	}

	public function publish(Request $request) {
		$resource = $this->getModel();
		$fillableColumns = $resource->getFillable();
		foreach ($fillableColumns as $column) {
			$resource->$column = $request->$column;
		}
		if (!$resource->save()) {
			return false;
		} else {
			return array('message' => ucfirst($this->model)." added successfully.", 'id' => $resource->id, 'object' => $resource);
		}
	}

	public function view(Request $request) {
		$result = $this->getModel($request->id);
		return array('result' => $result);
	}

	public function revise(Request $request, $id) {
		$resource = $this->getModel($request->id);
		$fillableColumns = $resource->getFillable();
		if ($resource) {
			foreach ($fillableColumns as $column) {
				if (!is_null($request->$column)) {
					$resource->$column = $request->$column;
				}
			}
			if (!$resource->save()) {
				return false;
			} else {
				return array('message' => ucfirst($this->model)." updated successfully.", 'id' => $resource->id, 'object' => $resource);
			}
		}
	}


	public function remove(Request $request)
	{
		$ids = array_unique($request->input('ids', [])); 
	
		if (empty($ids)) {
			return response()->json([
				'status' => false,
				'message' => 'No IDs provided to delete.'
			], 422);
		}
	
		$deleted = [];
	
		foreach ($ids as $id) {
			$model = $this->getModelNamespace()::find($id);
			if ($model) {
				$model->delete();
				$deleted[] = $id;
			}
		}
	
		if (empty($deleted)) {
			return response()->json([
				'status' => false,
				'message' => 'No records were found to delete.'
			], 404);
		}
	
		return [
			'status' => true,
			'message' => ucfirst($this->model).' Deleted successfully.',
			'deleted_ids' => $deleted
		];
	}
	

	public function deletePermanently(Request $request) {
		$table = $this->getModel()->getTable();
		$result = $this->getModelNamespace()::whereIn('id', $request->ids)->forceDelete();
		return array('message' => count($request->ids));
	}

	public function getListQuery(Request $request) {
		$sort = $request->sort ? $request->sort : $this->getModel()->defaultSort;
		$sortOrder = $request->sortOrder ? $request->sortOrder : $this->getModel()->defaultSortOrder;
		$trash = isset($request->trash) ? $request->trash : 'false';
		$table = $this->getModel()->getTable();
		$rawQuery = DB::getTablePrefix()."$table.*";
		if (!empty($this->selectFields)) {
			$rawQuery = implode(',', $this->selectFields);
		}
		$query = DB::table($table)
			->select(DB::raw($rawQuery));
		// Filter records using where clause
		$whereClause = $request->whereClause ? $request->whereClause : '';
		if ($whereClause != '') {
			$whereClause = json_decode($whereClause, 1);
			$whereFields = $whereClause['whereFields'];
			$whereValues = $whereClause['whereValues'];
			foreach ($whereFields as $key => $where_field) {
				if ($where_field == 'id') {
					$where_field = "$table.id";
				}
				if (is_array($whereValues[$key])) {
					$query->whereIn($where_field, $whereValues[$key]);
				} else {
					$query->where($where_field, $whereValues[$key]);
				}
			}
		}

		if (!empty($this->searchable) && $request->search != '') {
			$concat_fields = 'concat(';
			foreach ($this->searchable as $field) {
				if (strpos($field, '.')) {
					$concat_fields .= 'COALESCE(lower(' . $field . "), ''),'',";
				} else {
					$concat_fields .= 'COALESCE(lower(' . DB::getTablePrefix().$table . '.' . $field . "), ''),'',";
				}
			}
			$concat_fields = rtrim($concat_fields, ",'',");
			$concat_fields .= ')';
			$query->where([[DB::raw($concat_fields), 'like', '%' . strtolower($request->search) . "%"]]);
		}

		if ($trash == 'false') {
			$query->whereNull($table . '.deleted_at');
		} else {
			$query->whereNotNull($table . '.deleted_at');
		}
		$query->orderBy($sort, $sortOrder);

		return $query;
	}


	public function revert(Request $request) {
		$restore_all = $request->restore_all;
		$table = $this->getModel()->getTable();
		$counter = 0;
		if ($request->restore_all == false) {
			DB::table($table)->whereIn('id', $request->ids)->update(['deleted_at' => null]);
			$counter = count($request->ids);
		} else {
			$ids = DB::table($table)->whereNotNull('deleted_at')->get()->pluck('id');
			DB::table($table)->whereIn('id', $ids)->update(['deleted_at' => null]);
			$counter = count($ids);
		}
		return array('message' => $counter);
	}

	public function fetchDropdownOptions(Request $request) {
		$result_array = array();
		$search = $request->search;
		$column = $request->column;
		$where = $request->has('where') ? json_decode($request->where, true) : null;
		$query = $this->getModel()::where($column, 'like', "%$search%")->whereNull('deleted_at');
		if (!empty($where)) {
			$query->where($where);
		}
		$results = $query->get();
		foreach ($results as $result) {
			array_push($result_array, array('index' => $result->id, 'value' => $result->$column));
		}
		return $result_array;
	}

	public function tapActivity(Activity $activity, string $eventName) {
		$activity->ip = request()->ip();
		$activity->user_agent = request()->header('User-Agent');
	}

	public function processFields($fields) {
		$fields_collection = collect($fields);
		$required_fields = $fields_collection->whereNotNull('optionsProvider');
		if ($required_fields->count() > 0) {
			$required_fields = $required_fields->toArray();
			foreach ($required_fields as &$required_field) {
				$class = $required_field['optionsProvider'][0];
				$params = $required_field['optionsProvider'][1];
				$required_field['options'] = app($class)->selectRaw(implode(',', $params))->get();
				unset($required_field['optionsProvider']);
			}
		} else {
			$required_fields = $required_fields->toArray();
		}
		return array_merge($fields, $required_fields);
	}

	public function change(Request $request) {
		$params = $request->except('id');
		$this->getModel()->find($request->id)->update($params);
		return ['status' => true];
	}
}
