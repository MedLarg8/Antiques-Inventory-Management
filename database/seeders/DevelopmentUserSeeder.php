<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Development accounts may only be created in the local environment.');
        }

        foreach (Role::cases() as $role) {
            $email = $role->value.'@example.test';
            if (User::where('email', $email)->exists()) {
                $this->command->info("Skipping existing account: {$email}");

                continue;
            }
            $password = Str::password(24);
            $user = new User;
            $user->forceFill([
                'name' => $role === Role::Admin ? 'Development Administrator' : 'Development Stock Employee',
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'role' => $role,
            ])->save();
            $this->command->warn("Local account: {$email} | One-time displayed password: {$password}");
        }
    }
}
