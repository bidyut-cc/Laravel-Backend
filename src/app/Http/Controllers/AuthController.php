<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class AuthController extends Controller
{

    public function signup(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
            ]);
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
    
            $token = $user->createToken('auth_token')->plainTextToken;
    
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
    
        } catch (\Throwable $e) {
            dd("validation error", $e->getMessage());
        }
    }
    

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|email',
            'password' => 'required|string|min:6',
        ]);
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Credentials'
            ],500);
        }
        $user = User::where('email', $request['email'])->first();
        switch($user->active_status){
            case "Pending":
                return response()->json([
                    'status' => false,
                    'message' => 'Your login is not incomplete. Please check your inbox or contact admin.'
                ],500);
            break;
            case "Locked":
                return response()->json([
                    'status' => false,
                    'message' => 'Your login is Locked. Please contact admin.'
                ],500);
            break;
            case "Invitation-Sent":
                return response()->json([
                    'status' => false,
                    'message' => 'Invitation Link is already sent to your mail. Please follow that mail.'
                ],500);
            break;
            case "Invitation-Cancelled":
                return response()->json([
                    'status' => false,
                    'message' => 'Invitation has been cancelled. Please contact admin.'
                ],500);
            break;
        }
        if (!$user->status) {
            return response()->json([
                'status' => false,
                'message' => 'Your access is temporarily suspended. Please contact admin.'
            ],500);
        }
   
        $user_associated_roles = $user->roles->pluck('name')->toArray();
        if(count($user_associated_roles) <= 0){
            return response()->json([
                'status' => false,
                'message' => 'You don\'t have proper permissions to enter. Please contact admin.'
            ],500);
        }
  
     

        $token = $user->createToken('auth_token')->plainTextToken;

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Login');

        return response()->json([
            'status' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'tfa_enabled' => $user->two_factor_enabled,
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->roles->pluck('name')
        ],200);
    }

    public function profile(Request $request)
    {
        $user = $request->user();

        if($user->person_id){
            $employee = Employee::whereHas('person', function ($query) use ($user) {
                $query->where('id', '=', $user->person_id);
            })->withTrashed()->first(['id','person_id','status','deleted_at']);
            if($employee && (in_array($employee->status, ['Archive','Inactive']) || !empty($employee->deleted_at))){
                return response()->json([
                    'status' => false,
                    'message' => 'Your access is permenently suspended. Please contact admin.'
                ]);
            }
        }

        $user->associated_roles = $user->roles->pluck('name')->toArray();
        if(count($user->associated_roles) <= 0){
            return response()->json([
                'status' => false,
                'message' => 'You don\'t have proper permissions to enter. Please contact admin.'
            ], 422);
        }

        if(!empty($request->login_as)){
            $login_as = $request->login_as;
        }else{
            // $login_as = in_array("Member", $user->associated_roles) && count($user->associated_roles) == 1 ? "Member" : "Admin";
            $login_as = "Admin";
            foreach($user->associated_roles as $role_name){
                if(stripos($role_name, "member") !== false){
                    $login_as = "Member";
                }else{
                    $login_as = "Admin";
                    break;
                }
            }
        }
        $has_role = [];
        $emp_info = null;
        switch($login_as){
            case 'Member':
                $user_has_member_role = false;
                $user_has_admin_role = false;
                $associated_roles = [];
                foreach($user->associated_roles as $role_name){
                    if(stripos($role_name, "member") !== false){
                        $user_has_member_role = true;
                        $associated_roles[] = $role_name;
                    } else {
                        $user_has_admin_role = true;
                    }
                }
                if(!$user_has_member_role){
                    return response()->json([
                        'status' => false,
                        'message' => 'You do not have Member access. Please contact admin.'
                    ], 422);
                }
                $has_role = ["has_admin_role" => $user_has_admin_role];
                $user->associated_roles = $associated_roles;
                foreach($user->roles as $key => $role){
                    if(stripos($role->name, "member") === false) $user->roles->forget($key);
                }
                $find_employee = Employee::whereHas('person', function ($query) use ($user) {
                    $query->where('id', '=', $user->person_id);
                })->with([
                    'person:id,first_name,middle_name,last_name,profile_image',
                    'person.emails:id,person_id,type,email',
                    'jobInformation'
                ])->first(['id','person_id','efficiencypro','timehero']);
                if($find_employee){
                    $emp_info = [
                        "employee_id" => $find_employee->id,
                        "first_name" => $find_employee->person->first_name,
                        "middle_name" => $find_employee->person->middle_name,
                        "last_name" => $find_employee->person->last_name,
                        "profile_image_path" => $find_employee->person->profile_image_path,
                        "job_title" => $find_employee->jobInformation->job_title,
                        "efficient_pro_count" => intval($find_employee->efficiencypro),
                        "time_hero_count" => $find_employee->timehero >= 6 ? intval($find_employee->timehero/6) : 0,
                        "appreciation_count" => 0,
                    ];
                }
                break;
            case 'Admin':
                $user_has_member_role = false;
                $user_has_admin_role = false;
                $associated_roles = [];
                foreach($user->associated_roles as $role_name){
                    if(stripos($role_name, "member") !== false){
                        $user_has_member_role = true;
                    } else {
                        $user_has_admin_role = true;
                        $associated_roles[] = $role_name;
                    }
                }
                if(!$user_has_admin_role){
                    return response()->json([
                        'status' => false,
                        'message' => "You do not have Admin access. Please contact admin."
                    ], 422);
                }
                $has_role = ["has_member_role" => $user_has_member_role];
                //$associated_roles = $user->associated_roles;
                foreach($user->roles as $key => $role){
                    if(stripos($role->name, "member") !== false) $user->roles->forget($key);
                }

                $user->associated_roles = $associated_roles;
                break;
            default:
                break;
        }
        $user->permissions = $user->getPermissionsViaRoles()->pluck('name')->toArray();
        $user_info = array_merge([
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'permissions' => $user->permissions,
            'associated_roles' => $user->associated_roles,
            'roles' => $user->roles,
            'timezone' => $user->timezone,
            'profile_image' => $user->profile_image
        ], $has_role);

        return ["user" => $user_info, "employee" => $emp_info, "login_as" => $login_as,'pending_leave_request_count' => $this->getPendingLeaveRequestCount(),
        'pending_reimbursement_request_count' => $this->getPendingReimbursementRequestCount()];
    }

    public function logout(Request $request)
    {
        $user = $request->user(); // Authenticated user
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated'
            ], 401);
        }
    
        // Log activity only if user exists
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->tap(function (Activity $activity) use ($request) {
                $activity->ip = $request->ip();
                $activity->user_agent = $request->header('User-Agent');
            })
            ->log('Logout');
    
        // Revoke current token
        if ($user->currentAccessToken()) {
            $user->tokens()
                 ->where('id', $user->currentAccessToken()->id)
                 ->delete();
        }
    
        // Reset 2FA (if method exists)
        if (method_exists($user, 'resetTwoFactorCode')) {
            $user->resetTwoFactorCode();
        }
    
        return response()->json([
            'status' => true,
            'message' => 'Logged out'
        ]);
    }
    


}

