<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates (or resets) the first superuser. No credentials live in the code:
 * set ADMIN_EMAIL / ADMIN_PASSWORD (and optionally ADMIN_NAME) in the environment,
 * or run the seeder interactively and answer the prompts.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        $interactive = $this->command !== null && !$this->command->option('no-interaction');
        if (!$email && $interactive) {
            $email = $this->command->ask('Superuser email');
        }
        if (!$password && $interactive) {
            $password = $this->command->secret('Superuser password (at least 12 characters)');
        }

        if (!$email || !$password) {
            $this->command?->warn('No superuser created: set ADMIN_EMAIL and ADMIN_PASSWORD, or run `php artisan db:seed` interactively.');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $this->command?->error('Superuser not created: use a valid email and a password of at least 12 characters.');
            return;
        }

        User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name'                 => env('ADMIN_NAME', 'Super Admin'),
                'password'             => Hash::make($password),
                'role'                 => 'superuser',
                'property_id'          => null,
                'email_verified_at'    => now(),
                'status'               => 'active',
                'must_change_password' => false,
                'deleted_at'           => null,
            ]
        );

        $this->command?->info("Superuser ready: {$email}");
    }
}
