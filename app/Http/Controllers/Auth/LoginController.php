<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    // 🔹 Vista login
    public function showLogin()
    {
        return view('auth.login');
    }

    // 🔹 Login o solicitud recuperación
    public function login(Request $request)
    {
        // Si viene en modo recuperación
        if ($request->has('recuperar')) {

            $request->validate([
                'email' => 'required|email|exists:users,email'
            ], [
                'email.exists' => 'No se encontró ningún usuario con ese correo electrónico.',
            ]);

            $user = \App\Models\User::where('email', $request->email)->first();

            if (!$user) {
                return back()->withErrors(['email' => 'No se encontró el usuario.']);
            }

            if (!$user->activo) {
                return back()->withErrors(['email' => 'Esta cuenta se encuentra inactiva. Contacta al administrador directamente.']);
            }

            // Verificar si ya tiene una solicitud pendiente reciente (últimas 24h)
            $solicitudReciente = DB::table('password_resets')
                ->where('email', $request->email)
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            if ($solicitudReciente) {
                return back()->with('success', 'Ya se ha enviado una solicitud recientemente. El administrador ha sido notificado.');
            }

            // Guardar solicitud
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => bcrypt(now()),
                'created_at' => now()
            ]);

            // Notificar a TODOS los administradores
            $admins = \App\Models\User::whereHas('role', function($q) {
                $q->where('nombre', 'Administrador');
            })->where('activo', true)->get();

            foreach ($admins as $admin) {
                \App\Models\Notificacion::enviar(
                    $admin->id,
                    'Solicitud de recuperación de contraseña',
                    "El usuario {$user->name} ({$user->email}) ha solicitado un cambio de contraseña. Por favor, ingresa al módulo de Usuarios para asignarle una nueva contraseña.",
                    'warning'
                );
            }

            // Registrar en bitácora
            \App\Models\Bitacora::create([
                'caso_id'     => null,
                'user_id'     => $user->id,
                'modulo'      => 'Seguridad',
                'accion'      => 'Solicitud de recuperación',
                'descripcion' => "El usuario {$user->name} ({$user->email}) solicitó recuperación de contraseña.",
                'ip'          => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'created_at'  => now(),
            ]);

            return back()->with('success', 'Tu solicitud ha sido enviada al administrador. Serás contactado para restablecer tu contraseña.');
        }

        //login normal
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {

            $request->session()->regenerate();

            if (Auth::user()->password_change_required && !Auth::user()->esAdministrador()) {
                return redirect()->route('password.change.form');
            }

            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'email' => 'Credenciales incorrectas'
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}