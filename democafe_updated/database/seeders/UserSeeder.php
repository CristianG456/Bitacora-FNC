<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::create([
    'name' => 'Admin',
    'email' => 'admin@test.com',
    'password' => Hash::make('123456'),
    'rol' => 'admin',
]);

User::create([
    'name' => 'Principal',
    'email' => 'principal@test.com',
    'password' => Hash::make('123456'),
    'rol' => 'principal',
]);

User::create([
    'name' => 'Usuario',
    'email' => 'usuario@test.com',
    'password' => Hash::make('123456'),
    'rol' => 'usuario',
]);
