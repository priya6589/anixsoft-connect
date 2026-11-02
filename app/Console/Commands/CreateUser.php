<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * There is no public sign-up page: CRM accounts are created from the command line.
 */
class CreateUser extends Command
{
    protected $signature = 'user:create
                            {email : Login email}
                            {--name= : Display name (defaults to the part of the email before @)}
                            {--password= : Password (a strong one is generated if omitted)}';

    protected $description = 'Create a user who can log in to the CRM';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'unique:users,email'], 'password' => [Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $this->option('name') ?: Str::of($email)->before('@')->headline(),
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("User created: {$email}");

        if (! $this->option('password')) {
            $this->line("Password: {$password}");
            $this->warn('Change it after logging in (Profile page).');
        }

        return self::SUCCESS;
    }
}
