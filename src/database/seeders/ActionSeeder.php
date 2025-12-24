<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $actions = [
            'Add', 'Edit', 'Delete', 'View', 'List', 'Export', 'Navigate'
        ];

        #DB::table('actions')->truncate();
        $action_data = [];
        foreach ($actions as $action) {
            $action_data[] = [
                'name' => $action,
                'code' => Str::slug($action),
                'description' => Str::slug($action),
                'created_at'=>now()
            ];
        }
        DB::table('actions')->insert($action_data);
    }
}
