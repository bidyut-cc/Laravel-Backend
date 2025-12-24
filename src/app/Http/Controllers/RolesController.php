<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Guard;
//use Spatie\Permission\Models\Role;
//use Spatie\Permission\Models\Permission;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RolesController extends Controller {
    /**
     *
     * to save or update roles
     *
     * @param    Request $request
     * @param    object
     *
     */
	// public function save(Request $request) {
    //     try {
    //         $role = Role::updateOrCreate(
    //             [ 'id' => $request->id ],
    //             [ 
    //             'name' => $request->name, 
    //             'description' => $request->description,
    //             ]
    //         );

    //         // to save activity log
    //         $insert_array = [];
    //         if(empty($request->id)){
    //             if(!empty($role->id)){
    //                 $view_permissions = Permission::where([
    //                     ['name', 'LIKE' ,'view-%'],
    //                     ['guard_name', 'LIKE', 'sanctum'],
    //                 ])->get();
    //                 foreach($view_permissions as $permission){
    //                     $insert_array[] = ['permission_id' => $permission->id, 'role_id' => $role->id];
    //                 }
    //                 $navigate_permissions = Permission::where([
    //                     ['name', 'LIKE' ,'navigate-%'],
    //                     ['guard_name', 'LIKE', 'sanctum'],
    //                 ])->get();
    //                 foreach($navigate_permissions as $permission){
    //                     $insert_array[] = ['permission_id' => $permission->id, 'role_id' => $role->id];
    //                 }
    //                 $list_permissions = Permission::where([
    //                     ['name', 'LIKE' ,'list-%'],
    //                     ['guard_name', 'LIKE', 'sanctum'],
    //                 ])->get();
    //                 foreach($list_permissions as $permission){
    //                     $insert_array[] = ['permission_id' => $permission->id, 'role_id' => $role->id];
    //                 }
    //                 DB::table('role_has_permissions')->insert($insert_array);
    //             }

    //             $log = 'Create New Role';
    //         }else{
    //             $log = 'Update Role:'.$request->id.' From Role Edit Module';
    //         }
    //         $req_user = $request->user();
    //         $msg = empty($request->id) ? 'Role Created Successfully.' : 'Role Updated Successfully.';
    //         return response()->json(['status'=>true,'message'=>$msg,'insert_array'=>$insert_array],200);
    //     } catch (\PDOException $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong with your data. Please check and try again.',
    //             'exception' => $e->getMessage()
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Something went wrong!'
    //         ], 422);
    //     }
	// }

    /**
     *
     * assign permission to role
     *
     * @param      Request $request
     * @return      object
     *
    */
	public function assign(Request $request) {
        try {
            $role = Role::with('permission')->find($request->id);
            $permission=Permission::where('name',$request->permission)->value('id');
            $role->permission()->attach($permission);
           // $role->givePermissionTo($request->permission);
            return response()->json(['status'=>true,'message'=>'Permission assign successfully.'],200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!'
            ], 422);
        }
	}

    /**
     *
     * delete assigned permission from role
     *
     * @param      Request $request
     * @return      object
     *
    */
	public function unassign(Request $request) {
        try {
        $role = Role::with('permission')->find($request->id);
        $permission=Permission::where('name',$request->permission)->value('id');
        $role->permission()->detach($permission);
	//	$role->revokePermissionTo($request->permission);
        return response()->json(['status'=>true,'message'=>'Permission deleted successfully.'],200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!'
            ], 422);
        }
	}

    /**
     *
     * assigned all permission to role
     *
     * @param      Request $request
     * @return      array
     *
    */
	public function assignAll(Request $request) {
		$roleId = $request->id;
		$roleId = 3;
		$role = Role::find($roleId);
		$permissions = Permission::all();
		foreach ($permissions as $permission) {
			$role->givePermissionTo($permission->name);
		}
		return array('results' => $role);
	}

    public function roleWithPermission(){
        try {
            $roles = Role::with('permission')->get();
            return response()->json([
                'status' => true,
                'data' => $roles
            ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Something went wrong!'
                ], 422);
            }
        }

        public function attachOrDetachPermission(Request $request)
        {
            $request->validate([
                'role_id' => 'required|exists:roles,id',
                'permission' => 'required|string|exists:permissions,name',
                'checked' => 'required|boolean', // whether to attach or detach
            ]);
        
            try {
                $role = Role::findOrFail($request->role_id);
        
                $permission = Permission::where('name', $request->permission)->firstOrFail();
        
                if ($request->checked) {
                    // Attach safely without duplicates
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                } else {
                    // Detach permission
                    $role->permissions()->detach($permission->id);
                }
        
                return response()->json([
                    'status' => true,
                    'message' => 'Permission updated successfully.',
                ], 200);
        
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Something went wrong!',
                    'error' => $e->getMessage(),
                ], 422);
            }
        }
        
        
        


}
