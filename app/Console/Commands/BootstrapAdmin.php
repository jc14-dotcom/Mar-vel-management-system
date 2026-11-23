<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('oms:bootstrap-admin {name} {email} {--password=}')]
#[Description('Create or promote an individual Super Admin account')]
class BootstrapAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (minimum 12 characters)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => strtolower((string) $this->argument('email'))],
            [
                'name' => $this->argument('name'),
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
        $user->roles()->syncWithoutDetaching([
            Role::where('name', 'super_admin')->value('id'),
        ]);

        $this->info("Super Admin account ready for {$user->email}.");

        return self::SUCCESS;
    }
}
