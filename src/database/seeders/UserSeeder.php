<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        #DB::table('users')->truncate();
        DB::table('users')->insert([
            [
                'email' => 'bidyut.patra@codeclouds.com',
                'first_name' => 'Bidyut',
                'last_name' => 'Patra',
                'password' => Hash::make('123456'),
                'status' => true,
                'created_at' => now()
            ]
        ]);

        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => 'App\Models\User', 'model_id' => 1],
        ]);
    }
}
