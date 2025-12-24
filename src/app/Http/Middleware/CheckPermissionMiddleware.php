<?php

namespace App\Http\Middleware;

use Closure;


class CheckPermissionMiddleware {
	protected $noAuthRequired = ['login', 'register', 'forgot-password'];
	protected $except = [
		'fetchDropdownOptions', 'update-profile', 'set-password',  'dashboard', 'remove-avatar', 'send-otp-code', 'verify-otp-code'
	];

	public function handle($request, Closure $next) {

		$user_valid_res = $this->isUserValid($request);
		if(!$user_valid_res['status']) {
			return response()->json($user_valid_res, 401);
		}

		$user = $request->user();
		$path = substr($request->path(), 4);
		$no_check = array_merge($this->noAuthRequired, $this->except);
		foreach ($no_check as $nar) {
			if (strpos($path, $nar) !== false) {
				return $next($request);
			}
		}
		$userPermissions = $user->getPermissionsViaRoles()->pluck('name')->toArray();

		if ($this->checkPermission($path, $userPermissions))
			return $next($request);
		else
			return response()->json(['status' => false, 'permissions' => $user,'message' => "You don't have permission to perform this action"], 403);
	}

	public function checkPermission($path, $userPermissions) {
		// echo $path;die();
		$exploded_path = explode('/', $path);
		$module = $exploded_path[0];
		$action = isset($exploded_path[1]) ? $exploded_path[1] : '';
		switch ($action) {
			case 'save':
			case 'createView':
			case 'assign':
				$action = 'add';
				break;
			case 'update':
			case 'unassign':
				$action = 'edit';
				break;
			case 'deletePermanently':
			case 'delete':
				$action = 'delete';
				break;
			case '':
			default:
				$action = 'list';
				break;
		}
		$actionsArr = ["$action-all-$module", "$action-group-$module", "$action-owner-$module"];
		if ($action != '') {
			foreach ($actionsArr as $action) :
				if (in_array(
					$action,
					$userPermissions
				)) {
					return true;
				}
			endforeach;
		} else {
			return false;
		}
	}

    /**
     * checking if user is locked or deleted
     *
     * @param: Request
     * @return: array
     */
	public function isUserValid($request)
	{
		$user = $request->user();
		if($user){
			switch($user->active_status){
				case "Pending":
					return [
						'status' => false,
						'message' => 'Your login is not incomplete. Please check your inbox or contact admin.'
					];
				break;
				case "Locked":
					return [
						'status' => false,
						'message' => 'Your login has been Locked. Please contact admin.'
					];
				break;
				case "Invitation-Sent":
					return [
						'status' => false,
						'message' => 'Invitation Link has been already sent to your mail. Please follow that mail.'
					];
				break;
				case "Invitation-Cancelled":
					return [
						'status' => false,
						'message' => 'Invitation has been cancelled. Please contact admin.'
					];
				break;
			}
			if (!$user->status) {
				return [
					'status' => false,
					'message' => 'Your access is temporarily suspended. Please contact admin.'
				];
			}
	
			return ['status' => true];
		}else{
			return [
				'status' => false,
				'message' => 'User not found. Please try again.'
			];
		}
	}
}
