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

        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin123!-'),
                'role_id' => $adminRole->id
            ]
        );
    }
}