<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\RecyclableItemCategory;
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
        User::firstOrCreate([
            'email' => env('SEED_ADMIN_EMAIL', 'admin@recycling.com'),
        ], [
            'password' => bcrypt(env('SEED_ADMIN_PASSWORD', 'password')),
            'role' => 'admin',
        ]);

        User::firstOrCreate([
            'email' => env('SEED_USER_EMAIL', 'user@recycling.com'),
        ], [
            'password' => bcrypt(env('SEED_USER_PASSWORD', 'password')),
            'role' => 'user',
        ]);

        RecyclableItemCategory::firstOrCreate([
            'name' => 'Uncategorized',
        ], [
            'value' => 100,
        ]);

 $this->call([
            DemoSeeder::class,
        ]);

    }
}
