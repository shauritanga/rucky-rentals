<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::withTrashed()->updateOrCreate(
            ['email' => 'admin@mwambaproperties.co.tz'],
            [
                'name'               => 'Super Admin',
                'password'           => Hash::make('Admin123'),
                'role'               => 'superuser',
                'property_id'        => null,
                'email_verified_at'  => now(),
                'status'             => 'active',
                'must_change_password' => false,
                'deleted_at'         => null,
            ]
        );

        User::withTrashed()->updateOrCreate(
            ['email' => 'shauritangaathanas@gmail.com'],
            [
                'name'               => 'Athanas Shauritanga',
                'password'           => Hash::make('Athanas@2015'),
                'role'               => 'superuser',
                'property_id'        => null,
                'email_verified_at'  => now(),
                'status'             => 'active',
                'must_change_password' => false,
                'deleted_at'         => null,
            ]
        );

        $this->command->info('Admin user seeded: admin@mwambaproperties.co.tz');
        $this->command->info('Superuser seeded: shauritangaathanas@gmail.com');
    }
}
