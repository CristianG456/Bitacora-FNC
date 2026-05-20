<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create default admin user from docker environment variables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $adminRole = Rol::where('nombre', 'Administrador')->first();

        if (!$adminRole) {
            $this->error('Admin role not found. Please run migrations and seeders first.');
            return;
        }

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->error('ADMIN_EMAIL and ADMIN_PASSWORD environment variables are required.');
            return;
        }

        $adminExists = User::where('email', $email)->orWhere('rol_id', $adminRole->id)->exists();

        if ($adminExists) {
            $this->info('An administrator already exists. Skipping creation.');
            return;
        }

        User::create([
            'email' => $email,
            'name' => 'Administrador',
            'password' => Hash::make($password),
            'rol_id' => $adminRole->id,
            'area' => 'Dirección General',
            'activo' => true,
            'force_password_change' => true,
        ]);

        $this->info("Admin user created successfully with email: {$email}");
    }
}
