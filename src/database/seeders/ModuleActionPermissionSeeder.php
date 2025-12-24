<?php

namespace Database\Seeders;

use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Action;
use App\Models\Module;
use App\Models\Permission;

class ModuleActionPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        #Permission::truncate();
        $all_modules = Module::all();

        foreach ($all_modules as $module_val) {
            $permissions = array();
            $permission_arr = array();
            $data = array();
            $scopes = array('all', 'owner', 'group');
            $actions = Action::All();
            $module = $module_val->code;
            $permissions = Permission::where('name', 'LIKE', "%$module%")->get();
            foreach ($permissions as $perm) {
                $permission_arr[] = $perm->name;
            }
            foreach ($actions as $action) {
                foreach ($scopes as $scope) {
                    $permission = $action->code . '-' . $scope . '-' . $module;
                    if (!in_array($permission, $permission_arr)) {
                        $data[] = array('name' => $permission, 'guard_name' => 'sanctum');
                    }
                }
            }
            Permission::insert($data);
        }
    }
}
