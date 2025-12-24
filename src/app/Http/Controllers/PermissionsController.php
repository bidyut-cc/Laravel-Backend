<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Action;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    public function generate(Request $request)
    {
        $module = $request->module;

        if (!$module) {
            return response()->json([
                'status' => false,
                'message' => 'Module is required'
            ], 422);
        }

        $scopes = ['all', 'owner', 'group'];
        $actions = Action::all();

        // Get existing permissions for this module
        $existingPermissions = Permission::where('name', 'LIKE', "%{$module}%")
            ->pluck('name')
            ->toArray();

        $newPermissions = [];

        foreach ($actions as $action) {
            foreach ($scopes as $scope) {

                $permissionName = "{$action->code}-{$scope}-{$module}";

                if (!in_array($permissionName, $existingPermissions)) {

                    $newPermissions[] = [
                        'name'       => $permissionName,
                        'guard_name' => 'sanctum',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($newPermissions)) {
            Permission::insert($newPermissions);
        }

        return response()->json([
            'status'  => true,
            'created' => $newPermissions,
        ]);
    }
}
