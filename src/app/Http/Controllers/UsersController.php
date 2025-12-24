<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Models\UsersInvitation;
use App\Traits\Crud;
use Carbon\Carbon;
use Exception;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class UsersController extends Controller {


    /**
     *
     * to save or update users
     *
     * @param    Request $request
     * @return    object
     *
    */
    public function save(Request $request) {
        if($request->has('id') && $request->id!=''){
            parent::setAdditionalRules([
                'email'=>[
                    Rule::unique('users')->ignore($request->id),
                ]
            ]);
            $log='Update User:'.$request->id.' From User Edit Module';
        }else{
            parent::setAdditionalRules([
                'email'=>[
                    'unique:users'
                ]
            ]);
            $request['active_status'] = "Invitation-Sent";
            $request['password'] = Hash::make(rand(10000000, 99999999));
            $log='Save User Data.';
        }
        $validate = $this->validateRequest($request);
        if ($validate) {
            return $validate;
        }
        try {
            DB::beginTransaction();
            $user=User::userCreateOrUpdate($request);
            if(empty($request->id)){
                $code = Str::random(64);
                UsersInvitation::insert([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'code' => $code
                ]);
                if(!$user->sendUserInvitationNotification($code)){
                    throw new \ErrorException('Sending invitation mail Failed');
                }
            }

            // to save activity log
            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->withProperties($user)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log($log);
            DB::commit();
            $msg=$request->id =='' ? 'User Created Successfully.' : 'User Updated Successfully.';
            return response()->json(['status'=>true,'message'=>$msg],200);
        } catch (\PDOException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong with your data. Please check and try again.',
                'exception' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()

            ], 422);
        }
    }


    /**
     *
     * to update users
     *
     * @param    Request $request
     * @param    int $id
     * @return    object
     *
    */
    public function update(Request $request, $id) {
        if ($request->password) {
            $request->password = Hash::make($request->password);
        }
        parent::setAdditionalRules([
            'email'=>[
                Rule::unique('users')->ignore($request->id),
            ]
        ]);
        return parent::update($request, $id);
    }

    /**
     *
     * to remove profile image
     *
     * @param    Request $request
     * @param    object
     *
    */
    public function removeProfileImage(Request $request) {
        $user = $request->user();
        $profileImage = $user->profileImage;
        if (file_exists(public_path("/uploads/users/{$profileImage}"))) {
            try {
                unlink(public_path("/uploads/users/{$profileImage}"));
            } catch (\Exception $e) {
                return [
                    'status' => false,
                    'message' => 'Unable to delete profile image.'
                ];
            }
        }
        $user->profile_image = null;
        $user->save();
        return [
            'status' => true,
            'message' => 'Profile Image has been removed successfully.'
        ];
    }

    /**
     *
     * to assign user role
     *
     * @param    Request $request
     *
     * @return    object
    */
    public function assign(Request $request) {
        try{
            $user=User::find($request->id);
            $user->roles()->attach($request->role);
            return response()->json(['status'=>true,'message'=>'Role assign succesfully.'],200);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!'
            ], 422);
        }

    }

    /**
     *
     * to delete user role
     *
     * @param    Request $request
     *
     * @return    object
    */

    public function unassign(Request $request) {
        try{
            $user=User::find($request->id);
            $user->roles()->detach($request->role);
            return response()->json(['status'=>true,'message'=>'Role detach succesfully.'],200);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!'
            ], 422);
        }
    }

    /**
     * Create or update user data
     *
     * @param: Request
     * @return: array
     */
    public function saveUser(Request $request) {

		$request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'role' => 'required',
        ]);

        try{
            $name_arr = explode(" ", $request->input('name'));
            $last_name = count($name_arr) > 1 ? array_pop($name_arr) : "";

            $log_properties = [];
            $upsert_data = [
                'first_name' => Str::of(implode(" ", $name_arr))->trim(),
                'middle_name' => null,
                'last_name' => Str::of($last_name)->trim(),
                'email' => $request->input('email'),
                'updated_at' => Carbon::now(),
            ];
            $upsert_attr = [];
            if(!empty($request->id)){
                $user = User::where('id', $request->id)->with('roles')->first();
                if (!$user) {
                    return [
                        'status' => false,
                        'message' => "User not found",
                    ];
                }
                $log_properties['old_user'] = [
                    'id' => $user->id,
                    'name' => $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name,
                    'email' => $user->email,
                    'role' => $user->roles[0]->name,
                ];
                $upsert_attr['id'] = $request->id;
            }else{
                $upsert_data['password'] = Hash::make(rand(10000000, 99999999));
                $upsert_data['created_at'] = Carbon::now();
                $upsert_data['active_status'] = "Invitation-Sent";
                $upsert_attr['id'] = null;
            }

            try{
                DB::beginTransaction();
                $user = User::updateOrCreate($upsert_attr, $upsert_data);

                if(empty($request->id)){
                    $code = Str::random(64);
                    UsersInvitation::insert([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'code' => $code,
                        'created_at' => Carbon::now()
                    ]);
                    if(!$user->sendUserInvitationNotification($code)){
                        throw new \ErrorException('Sending invitation mail Failed');
                    }
                }

                DB::table('model_has_roles')->where('model_id', $user->id)->delete();
                DB::table('model_has_roles')->insert(
                    ['role_id' => $request->input('role'), 'model_type' => 'App\Models\User', 'model_id' => $user->id]
                );
                DB::commit();
            }catch(Exception $e){
                DB::rollBack();
                return [
                    'status' => true,
                    'message' => "Saving user data unsuccessfull",
                ];
            }

            $role = Role::find($request->role);

            $log_properties['new_user'] = [
                'id' => $user->id,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'role' => $role->name,
            ];

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->withProperties($log_properties)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Save User Data');

            return [
                'status' => true,
                'message' => "User Data Saved",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Get all active users info for user module
     *
     * @param: Request
     * @return: array
     */
    public function getUsers(Request $request) {
        try{
            $users_info = User::where('deleted_at', null);
            if ($request->has('search_users') && !empty($request->input('search_users'))) {
                $users_info->where('first_name','LIKE','%'.$request->search_users.'%')
                ->orWhere('middle_name','LIKE','%'.$request->search_users.'%')
                ->orWhere('last_name','LIKE','%'.$request->search_users.'%')
                ->orWhere('email','LIKE','%'.$request->search_users.'%');
            }
            $users_info = $users_info->with('roles')->get();
            $users = [];
            foreach($users_info as $user){
              //  $invite_data = UsersInvitation::where(['email' => $user->email])->latest('created_at')->first();
                $users[] = [
                    'id' => $user->id,
                    'name' => $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name,
                    'email' => $user->email,
                    'role' => count($user->roles) ? $user->roles[0]->name : "",
                    '2FA' => $user->two_factor_enabled ? 'Enabled' : 'Disabled',
                    'status' => $user->active_status ? Str::replace(["-","_"], " ", $user->active_status) : "Unknown",
                  //  'invitation_status' => $invite_data ? Str::replace(["-","_"], " ", $invite_data->status) : "",
                ];
            }

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Fetch All Users For User Module');

            return [
                'status' => true,
                'users' => $users,
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Get a user info for user edit module
     *
     * @param: Request
     * @return: array
     */
    public function getUser($id = 0) {
        try{
            $user = User::where('id', $id)->with('roles')->first();
            if (!$user) {
                return [
                    'status' => false,
                    'message' => "User not found",
                ];
            }
            $user_info = [
                'id' => $user->id,
                'name' => $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name,
                'email' => $user->email,
                'role' => $user->roles[0]->id,
            ];

            $request = request();
            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Fetch User:'.$id.' From User Edit Module');

            return [
                'status' => true,
                'user' => $user_info,
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Update a user info for user edit module
     *
     * @param: Request
     * @return: array
     */
    public function updateUser(Request $request) {
        $request->validate([
            'id' => 'required',
            'name' => 'required',
            'role' => 'required',
        ]);

        try{
            $user = User::where('id', $request->id)->with('roles')->first();
            if (!$user) {
                return [
                    'status' => false,
                    'message' => "User not found",
                ];
            }
            $old_attributes = [
                'id' => $request->input('id'),
                'name' => $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name,
                'role' => $user->roles[0]->name
            ];

            $name_arr = explode(" ", $request->input('name'));
            $last_name = count($name_arr) > 1 ? array_pop($name_arr) : "";

            $user->first_name = Str::of(implode(" ", $name_arr))->trim();
            $user->middle_name = null;
            $user->last_name = Str::of($last_name)->trim();
            $user->save();
            // DB::table('model_has_roles')->where('role_id', $request->role)->where('model_id', $request->id)->delete();
            DB::table('model_has_roles')->where('model_id', $request->id)->delete();
            DB::table('model_has_roles')->insert(
                ['role_id' => $request->role, 'model_type' => 'App\Models\User', 'model_id' => $user->id]
            );
            $role = Role::find($request->role);

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->withProperties([
                'old' => $old_attributes,
                'new' => ['id' => $request->input('id'), 'name' => $request->input('name'), 'role' => $role->name]
            ])
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Update User:'.$request->id.' From User Edit Module');

            return [
                'status' => true,
                'message' => $request->name."'s data updated",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Update user active_status info
     *
     * @param: Request
     * @return: array
     */
    public function updateUserActiveStatus(Request $request) {
        try{
            $user = User::findOrFail($request->id);
            $old_attributes = ['id' => $user->id, 'active_status' => $user->active_status];
            $user->active_status = $request->active_status;
            $user->save();
            $name = $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name;

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->withProperties([
                'old' => $old_attributes,
                'new' => ['id' => $request->id, 'active_status' => $request->active_status]
            ])
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Update User Active Status');

            return [
                'status' => true,
                'message' => $name." Status Updated",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Update user two_factor_enabled info
     *
     * @param: Request
     * @return: array
     */
    public function updateUserTFAStatus(Request $request) {
        try{
            $user = User::findOrFail($request->id);
            $old_attributes = ['id' => $user->id, 'tfa_enabled' => $user->two_factor_enabled];
            $user->two_factor_enabled = $request->tfa_enabled;
            $user->save();
            $name = $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name;

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->withProperties([
                'old' => $old_attributes,
                'new' => ['id' => $request->id, 'tfa_enabled' => $request->tfa_enabled]
            ])
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Update User TFA Status');

            return [
                'status' => true,
                'message' => $name." 2FA Status Updated",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Create new user with sending invitation link
     *
     * @param: Request
     * @return: array
     */
    public function createNewUser(Request $request) {

		$request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'role' => 'required',
        ]);

        try{
            $name_arr = explode(" ", $request->input('name'));
            $last_name = count($name_arr) > 1 ? array_pop($name_arr) : "";

            try{
                DB::beginTransaction();
                $user = new User();
                $user->first_name = Str::of(implode(" ", $name_arr))->trim();
                $user->middle_name = null;
                $user->last_name = Str::of($last_name)->trim();
                $user->password = Hash::make(rand(10000000, 99999999));
                $user->email = $request->input('email');
                $user->created_at = now();
                $user->updated_at = now();
                $user->active_status = "Invitation-Sent";
                $user->save();
                DB::table('model_has_roles')->insert(
                    ['role_id' => $request->input('role'), 'model_type' => 'App\Models\User', 'model_id' => $user->id]
                );

                $code = Str::random(64);
                UsersInvitation::insert([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'code' => $code,
                    'created_at' => Carbon::now()
                ]);
                if(!$user->sendUserInvitationNotification($code)){
                    throw new \ErrorException('Sending invitation mail Failed');
                }
                DB::commit();
            }catch(Exception $e){
                DB::rollBack();
                return [
                    'status' => true,
                    'message' => "Saving user data unsuccessfull",
                ];
            }

            $role = Role::find($request->role);

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->withProperties(['new_user' => ['id' => $user->id, 'name' => $request->input('name'), 'email' => $request->input('name'), 'role' => $role->name]])
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Create New User');

            return [
                'status' => true,
                'message' => "User Created",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Validate invitation code and Create new user with sending invitation link
     *
     * @param: Request
     * @return: array
     */
    public function acceptInvitation(Request $request) {

		$request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required|string|min:6',
            'code' => 'required'
        ]);

        try{
            $invite_code = UsersInvitation::where(['email' => $request->email, 'code' => $request->code])->latest('created_at')->first();
            if(!$invite_code){
                return [
                    'status' => false,
                    'message' => "Invalid token",
                ];
            }
            if($invite_code->status == "Closed"){
                return [
                    'status' => false,
                    'message' => "Invitation is closed",
                ];
            }

            try{
                DB::beginTransaction();
                User::where('email', $request->email)->update(['active_status' => 'Active', 'password' => Hash::make($request->password)]);
                UsersInvitation::where(['email' => $request->email, 'code' => $request->code])->update(['status' => 'Closed']);
                DB::commit();
            }catch(Exception $e){
                DB::rollBack();
                return [
                    'status' => true,
                    'message' => "Accept invitation failed",
                ];
            }

            return [
                'status' => true,
                'message' => "Password is set. You can login",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Validate invitation code and assign role to user sending invitation link
     *
     * @param: Request
     * @return: array
     */
    public function acceptNewRoleInvitation(Request $request) {

		$request->validate([
            'email' => 'required|email|exists:users',
            'code' => 'required'
        ]);

        try{
            $invite_code = UsersInvitation::where(['email' => $request->email, 'code' => $request->code])->latest('created_at')->first();
            if(!$invite_code){
                return [
                    'status' => false,
                    'message' => "Invalid token",
                ];
            }
            if($invite_code->status == "Closed"){
                return [
                    'status' => false,
                    'message' => "Invitation is closed",
                ];
            }

            try{
                DB::beginTransaction();
                $role_ids = json_decode($invite_code->assign_roles, 1);
                $user = User::where('email', $request->email)->first();
                $user_roles = $user->roles()->pluck('id')->toArray();
                foreach($role_ids as $role_id){
                    // $existing_data = DB::table('model_has_roles')->where(
                    //     ['role_id' => $role_id, 'model_type' => 'App\Models\User', 'model_id' => $user->id]
                    // )->first();
                    // if(!$existing_data){
                    //     DB::table('model_has_roles')->insert(
                    //         ['role_id' => $role_id, 'model_type' => 'App\Models\User', 'model_id' => $user->id]
                    //     );
                    // }
                    if(!in_array($role_id, $user_roles)){
                        $user->roles()->attach($role_id);
                    }
                }
                UsersInvitation::where(['email' => $request->email, 'code' => $request->code])->update(['status' => 'Closed']);
                DB::commit();
            }catch(Exception $e){
                DB::rollBack();
                return [
                    'status' => false,
                    'message' => $e->getMessage(),
                ];
            }

            $roles_data = Role::whereIn('id', json_decode($invite_code->assign_roles, 1))->get()->pluck('name')->toArray();
            return [
                'status' => true,
                'message' => implode(", ", $roles_data)." role(s) have been assigned. Please login once again for gain access.",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => "Something went wrong!",
            ];
        }
    }

    /**
     * Get invitation status
     *
     * @param: Request
     * @return: array
     */
    public function getInvitationStatus(Request $request) {
		$request->validate([
            'email' => 'required|email',
            'code' => 'required',
        ]);
        try{
            $message = "";
            $invite_code = UsersInvitation::where(['email' => $request->email, 'code' => $request->code])->latest('created_at')->first();
            if(!$invite_code){
                return [
                    'status' => false,
                    'assign_roles' => false,
                    'message' => "Invalid token",
                ];
            }
            if($invite_code && !empty($invite_code->assign_roles)){
                $roles_data = Role::whereIn('id', json_decode($invite_code->assign_roles, 1))->get()->pluck('name')->toArray();
                $message = implode(", ", $roles_data)." role(s) are assiging very soon. Please wait for update.";
            }

            return [
                'status' => true,
                'message' => $message,
                'assign_roles' => !empty($invite_code->assign_roles),
                'invitation_status' => $invite_code->status,
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Update user delete_at column data for user module
     *
     * @param: Request
     * @return: array
     */
    public function deleteUser($id = 0) {
        try{
            $user = User::where('id', $id)->first();
            $name = $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name;
            if (!$user) {
                return [
                    'status' => false,
                    'message' => "User not found",
                ];
            }
            if ($user->deleted_at != null) {
                return [
                    'status' => false,
                    'message' => $name."'s account already deleted",
                ];
            }
            // $user->deleted_at = Carbon::now();
            // $user->save();
            $user->delete();

            $request = request();
            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Delete User:'.$id.' From User Module');

            return [
                'status' => true,
                'message' => $name."'s account is deleted",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Cancel user invitation for user module
     *
     * @param: Request
     * @return: array
     */
    public function cancelUserInvitation(Request $request) {
        try{
            $user = User::where('id', $request->id)->first();
            $name = $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name;
            if (!$user) {
                return [
                    'status' => false,
                    'message' => "User not found",
                ];
            }

            try{
                DB::beginTransaction();
                UsersInvitation::where(['email' => $user->email])->update(['status' => 'Closed']);
                $user->active_status = 'Invitation-Cancelled';
                $user->save();
                DB::commit();
            }catch(Exception $e){
                DB::rollBack();
                return [
                    'status' => true,
                    'message' => "Cancel invitation failed",
                ];
            }

            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Cancel user invitation for user:'.$request->id.' From User Module');

            return [
                'status' => true,
                'message' => $name."'s account invitation is closed",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     * Resend  for user module
     *
     * @param: Request
     * @return: array
     */
    public function resendUserInvitation(Request $request) {
        try{
            $user = User::where('id', $request->id)->first();
            $name = $user->first_name." ".($user->middle_name ? $user->middle_name." " : "").$user->last_name;
            if (!$user) {
                return [
                    'status' => false,
                    'message' => "User not found",
                ];
            }

            try{
                DB::beginTransaction();
                UsersInvitation::where(['email'=> $user->email])->update(['status' => 'Closed']);;

                $code = Str::random(64);
                UsersInvitation::insert([
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'code' => $code,
                    'created_at' => Carbon::now()
                ]);
                if(!$user->sendUserInvitationNotification($code)){
                    throw new \ErrorException('Sending invitation mail Failed');
                }
                $user->active_status = 'Invitation-Sent';
                $user->save();
                DB::commit();
            }catch(Exception $e){
                DB::rollBack();
                return [
                    'status' => true,
                    'message' => "Resend invitation failed",
                ];
            }

            $request = request();
            $req_user = $request->user();
            activity()
            ->causedBy($req_user)
            ->performedOn($req_user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Resend invitation for user:'.$request->id.' From User Module');

            return [
                'status' => true,
                'message' => $name."'s account invitation mail has been sent",
            ];
        }catch(Exception $ex){
            return [
                'status' => false,
                'message' => $ex->getMessage(),
            ];
        }
    }

    /**
     *
     * to get user records
     *
     * @return      object
     *
    */

    // public function list(){
    //     try{
    //         $users=User::get();
    //         return response()->json(['status'=>true,'data'=>$users,'message'=>'Users fetch successfully.'],200);
    //     }catch(Exception $e){
    //         return response()->json(['status'=>false,'message'=>'Server error.'],500);
    //     }

    // }


        /**
     *
     * to delete single or multiple user
     *
     * @param      Request $request
     * @return      object
     *
    */
	public function delete(Request $request) {
        $validator=Validator::make($request->all(),[
            'ids'=>['required','array']
        ]);
        if($validator->passes()){
            try{
               $data= parent::delete($request);
                // to save activity log
                $req_user = $request->user();
                foreach($request->ids as $id){
                    activity()
                    ->causedBy($req_user)
                    ->performedOn($req_user)
                    ->tap(function (Activity $activity) use ($request) {
                        $activity->ip = $request->ip();
                        $activity->user_agent = $request->header('User-Agent');
                    })
                    ->log('Delete Role:'.$id.' From User Module');
                }

                return $data;
            }catch(Exception $e){
                return response()->json(['status'=>false,'message'=>$e->getMessage()],500);
            }
        }else{
            return response()->json(['status'=>false,'message'=>$validator->errors()->first()],422);
        }
	}

     /**
     *
     * to change user single records
     *
     * @param      Request $request
     * @return      object
     *
    */
    public function change(Request $request){
        $validator=Validator::make($request->all(),[
            'id'=>['required']
        ]);
        if($validator->passes()){
            try{
                $data=parent::change($request);
                return $data;
            }catch(Exception $e){
                return response()->json(['status'=>false,'message'=>'Server error'],500);
            }
        }else{
            return response()->json(['status'=>false,'message'=>$validator->errors()->first()],422);
        }
    }

    /**
     * Get timezone list
     *
     * @param: Request $request
     * @return: object
     *
     */
    public function timezoneList(Request $request){
        try{
            $zones_array = array();
            $timestamp = time();
            foreach(timezone_identifiers_list() as $zone) {
                date_default_timezone_set($zone);
                $zones_array[] = [
                    'name' => $zone.' (UTC/GMT ' . date('P', $timestamp) . ')',
                    'value' => $zone,
                ];
            }
            return response()->json(['status'=>true,'timezone'=>$zones_array],200);
        }catch(Exception $e){
            return response()->json(['status'=>false,'message'=>$e->getMessage()],500);
        }
    }

    /**
     * send member invitation to employee.
     *
     * @param: Request $request
     * @return: object
     *
     */
    public function sendMemberInvitationForEmployee(Request $request) 
    {
        $validator = Validator::make($request->all(),[
            'employee_id' => ['required'],
        ]);
        if($validator->passes()){
            try{
                $employee = Employee::with([
                    'person:id,first_name,middle_name,last_name,profile_image',
                    'person.emails:id,person_id,type,email'
                ])->find($request->employee_id);
                if(blank($employee)){
                    return response()->json(['status' => false, 'message' => 'Employee not Exists'], 200);
                }

                $role_data = Role::where('name', 'Member')->where('guard_name', 'sanctum')->first();
                $user = User::where('email', $employee->person->emails[0]->email)->first();
                if(!blank($user)){
                    if(($user->active_status !='Active')||($user->active_status !='Locked')){

                        //if(!$user->has_member_role){

                            DB::beginTransaction();
                            UsersInvitation::where(['email'=> $user->email])->update(['status' => 'Closed']);
                            $code = Str::random(64);
                            UsersInvitation::insert([
                                'user_id' => $user->id,
                                'email' => $user->email,
                                'code' => $code,
                                'assign_roles' => json_encode([$role_data->id]),
                                'created_at' => Carbon::now(),
                            ]);
                            if(!$user->sendMemberInvitationNotification($code, false)){
                                return response()->json(['status'=>false,'message'=>'Invitation sending failed. Please try again later.'],200);
                            }
                            if(empty($user->person_id)){
                                $user->update(['person_id' => $employee->person->id]);
                            }
                            DB::commit();
                            return response()->json(['status'=>true,'message'=>'Member invitation has been sent successfully.'],200);

                        //}
                    }
                    else {

                        return response()->json(['status'=>false,'message'=>'This member has already been Active/Locked.'],200);
                    }

                }else{
                    DB::beginTransaction();
                    $new_req = new Request;
                    $new_req['person_id'] = $employee->person->id;
                    $new_req['first_name'] = $employee->person->first_name;
                    $new_req['middle_name'] = $employee->person->middle_name;
                    $new_req['last_name'] = $employee->person->last_name;
                    $new_req['email'] = $employee->person->emails[0]->email;
                    $new_req['profile_image'] = $employee->person->profile_image;
                    $new_req['password'] = Hash::make(rand(10000000, 99999999));
                    $new_req['active_status'] = "Invitation-Sent";
                    $new_req['role_id'] = $role_data->id;
                    $user = User::userCreateOrUpdateByEmail($new_req);
                    $code = Str::random(64);
                    UsersInvitation::where(['email'=> $user->email])->update(['status' => 'Closed']);
                    UsersInvitation::insert([
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'code' => $code,
                        'created_at' => Carbon::now(),
                    ]);
                    if(!$user->sendMemberInvitationNotification($code, false)){
                        return response()->json(['status'=>false,'message'=>'Invitation sending failed. Please try again later.'],200);
                    }

                    // to save activity log
                    $log='Create User:'.$user->id.' From Employee Listing';
                    $req_user = $request->user();
                    activity()
                        ->causedBy($req_user)
                        ->withProperties($user)
                        ->performedOn($req_user)
                        ->tap(function (Activity $activity) use ($request) {
                            $activity->ip = $request->ip();
                            $activity->user_agent = $request->header('User-Agent');
                        })
                        ->log($log);
                    DB::commit();
                    return response()->json(['status'=>true,'message'=>'Member created and invitation has been sent successfully.'],200);
                }
            }catch(Exception $e){
                DB::rollBack();
                return response()->json(['status'=>false,'message'=>$e->getMessage()],500);
            }
        }else{
            return response()->json(['status'=>false,'message'=>$validator->errors()->first()],422);
        }
    }
}
