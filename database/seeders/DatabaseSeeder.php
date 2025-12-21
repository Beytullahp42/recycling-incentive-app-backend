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
        User::factory()->create([
            'email' => 'admin@recycling.com',
            'password' => bcrypt('yawabicim'),
            'role' => 'admin',
        ]);
        User::factory()->create([
            'email' => 'user@recycling.com',
            'password' => bcrypt('yawabicim'),
            'role' => 'user',
        ]);
        RecyclableItemCategory::create([
            'name' => 'Uncategorized',
            'value' => 100,
        ]);
    }
}
