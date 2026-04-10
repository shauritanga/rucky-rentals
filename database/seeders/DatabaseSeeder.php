<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Safe for fresh installs — creates the superuser without wiping any data.
        // For a full dev reset use: php artisan db:seed --class=CleanSlateSeeder
        $this->call(AdminSeeder::class);
    }
}
