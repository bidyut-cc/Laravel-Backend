<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        $modules = [
            'Actions',  'Modules', 'Permissions', 'Roles', 'Users', 'Activities', 'Changelogs'
        ];

        #DB::table('modules')->truncate();
        $module_data = [];
        foreach ($modules as $module) {
            $module_data[] = [
                'name' => $module,
                'code' => Str::slug($module),
                'description' => Str::slug($module),
                'created_at' => now()
            ];
        }
        DB::table('modules')->insert($module_data);
    }
}
