<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach (['admin' => 'Admin Wiselion', 'manager' => 'Manager Wiselion', 'pelanggan' => 'Pelanggan Wiselion'] as $role => $name) {
            $user = User::query()->firstOrCreate(
                ['email' => $role.'@wiselion.test'],
                ['name' => $name, 'password' => 'password', 'role' => $role],
            );

            if ($user->name === $name && $user->role !== $role) {
                $user->update(['role' => $role]);
            }
        }
    }
}
