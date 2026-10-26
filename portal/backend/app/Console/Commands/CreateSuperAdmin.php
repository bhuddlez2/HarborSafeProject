<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
Makes the first super admin account (team plan task 15), on a server where
nobody can sign in to make one yet. After that, super admins create, edit and
deactivate each other on Accounts (StaffAccountResource), never themselves.

  php artisan staff:create-super-admin someone@example.org

Asks for the name and the password (typed twice, hidden). The password follows
the same rule as every account screen. Creates an active account; it never
changes an existing one, so it can't be used to promote someone.

Nobody can deactivate their own account, so the last active super admin
can't be switched off in the panel.
*/
class CreateSuperAdmin extends Command
{
    protected $signature = 'staff:create-super-admin
        {email : The new account\'s email address}
        {--first-name= : First name (asked for if left out)}
        {--last-name= : Last name (asked for if left out)}';

    protected $description = 'Create a Harbor Safe super admin account (for the first one; after that, use Accounts)';

    public function handle(): int
    {
        $data = [
            'email' => (string) $this->argument('email'),
            'first_name' => $this->option('first-name') ?: $this->ask('First name'),
            'last_name' => $this->option('last-name') ?: $this->ask('Last name'),
            'password' => $this->secret('Password (at least 15 characters)'),
            'password_confirmation' => $this->secret('Password again'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255', 'unique:Portal.users,email'],
            'first_name' => ['required', 'string', 'max:'.User::NAME_PART_MAX],
            'last_name' => ['required', 'string', 'max:'.User::NAME_PART_MAX],
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);

        $this->info("Super admin {$data['email']} created. They sign in at /login.");

        return self::SUCCESS;
    }
}
