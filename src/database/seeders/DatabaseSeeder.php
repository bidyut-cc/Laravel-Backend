<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
			ActionSeeder::class,
			ModuleSeeder::class,
			RoleSeeder::class,
			ModuleActionPermissionSeeder::class,
			RoleHasPermissionSeeder::class,
			UserSeeder::class,
			//TeamCsvSeeder::class,
			//ServiceSeeder::class,
			//ModifierSeeder::class,
			//ClientSeeder::class,
            //UnitTypeSeeder::class,
			//LocationSeeder::class
		]);
    }
}
