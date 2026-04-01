<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@rukyrentals.co.tz'],
            [
                'name'               => 'Super Admin',
                'password'           => Hash::make('admin123'),
                'role'               => 'superuser',
                'property_id'        => null,
                'email_verified_at'  => now(),
            ]
        );

        $this->command->info('Admin user seeded: admin@rukyrentals.co.tz');
    }
}
