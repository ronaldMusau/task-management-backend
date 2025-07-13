<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    // database/seeders/AdminSeeder.php
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Ronald123!'), 
                'role' => 'admin'
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('Ronald123!'),
                'role' => 'user'
            ]
        );
    }
}