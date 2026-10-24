<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('super_admin.name'));
        $email = strtolower(trim((string) config('super_admin.email')));
        $password = (string) config('super_admin.password');

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                'Configurez SUPER_ADMIN_NAME et une adresse SUPER_ADMIN_EMAIL valide dans le fichier .env.',
            );
        }

        if (strlen($password) < 12) {
            throw new InvalidArgumentException(
                'SUPER_ADMIN_PASSWORD doit contenir au moins 12 caractères.',
            );
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        $user->assignRoles(['admin']);
    }
}
