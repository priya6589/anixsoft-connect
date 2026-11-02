<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or updates) the admin login from ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD in .env,
 * so the password never has to be written in a file that goes into git.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('outreach.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env first.');
        }

        User::updateOrCreate(
            ['email' => strtolower($admin['email'])],
            ['name' => $admin['name'], 'password' => $admin['password'], 'email_verified_at' => now()],
        );

        $this->command?->info("Admin login ready: {$admin['email']}");
    }
}
