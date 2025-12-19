<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['phone' => '22211111111'],
            [
                'role' => 'ADMIN',
                'full_name' => 'Alassan Admin',
                'email' => null,
                'status' => 'ACTIVE',
                'password' => Hash::make('admin123'),
            ]
        );
    }
}
