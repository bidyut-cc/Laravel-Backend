<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        $roles = [
            'Admin', 'Developer', 'Staff'
        ];

        #DB::table('roles')->truncate();
        $role_data = [];
        foreach ($roles as $role) {
            $role_data[] = [
                'name' => $role,
                'guard_name' => 'sanctum',
                'created_at' => now()
            ];
        }
        DB::table('roles')->insert($role_data);
    }
}
