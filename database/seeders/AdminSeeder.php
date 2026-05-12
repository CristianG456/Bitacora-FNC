<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Rol;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Rol::where('nombre', 'Administrador')->first();

        if (!$adminRole) {
            throw new \Exception('Ejecuta primero RolesSeeder');
        }

        $email = env('ADMIN_EMAIL', 'admin@test.com');
        $password = env('ADMIN_PASSWORD', 'Admin123!-');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'password' => Hash::make($password),
                'rol_id' => $adminRole->id,
                'area' => 'Dirección General',
                'activo' => true
            ]
        );
    }
}