<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleHasPermissionSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        #DB::table('role_has_permissions')->truncate();
        $developer_role = Role::find(2);
        $permissions = Permission::all();
        $permission_data = [];
        $admin_permission_data = [];
        $staff_permission_data = [];
        foreach ($permissions as $permission) {
            $permission_data[] = [
                'permission_id' => $permission->id,
                'role_id' => $developer_role->id,
            ];
            $admin_permission_data[] = [
                'permission_id' => $permission->id,
                'role_id' => 1,
            ];
        }

        $staff_permissions = Permission::whereIn('name', [
            "edit-owner-clients",
            "edit-group-clients",
            "view-owner-clients",
            "view-group-clients",
            "list-owner-clients",
            "list-group-clients",
            "navigate-all-clients",
            "navigate-owner-clients",
            "navigate-group-clients",
            "list-all-modifiers",
            "list-all-services",
        ])->get()->pluck('id')->toArray();
        foreach ($staff_permissions as $key => $sf) {
            $staff_permission_data[] = [
                'permission_id' => $sf,
                'role_id' => 3,
            ];
        }

        DB::table('role_has_permissions')->insert($permission_data);
        DB::table('role_has_permissions')->insert($admin_permission_data);
        DB::table('role_has_permissions')->insert($staff_permission_data);
    }
}
