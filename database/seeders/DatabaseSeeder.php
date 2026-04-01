<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@movingguru.co',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $admin->detail()->create([
            'profileStatus' => 'active',
            'plan' => 'monthly',
        ]);

        $client = User::create([
            'name' => 'Bambi',
            'email' => 'bambi@movingguru.co',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);

        $client->detail()->create([
            'profileStatus' => 'active',
            'plan' => 'monthly',
        ]);
    }
}
