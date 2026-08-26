<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ProgressiveLoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    // 🔹 Vista login
    public function showLogin()
    {
        if (Auth::check()) {
            if (Auth::user()->force_password_change) {
                return redirect()->route('password.change.form');
            }

            return app(\App\Http\Controllers\DashboardController::class)->index();
        }

        return view('auth.login');
    }

    // 🔹 Login o solicitud recuperación
    public function showTabLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, ProgressiveLoginThrottle $loginThrottle)
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
            'password' => 'required',
        ]);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));

        $secondsRemaining = $loginThrottle->secondsRemaining($credentials['email']);
        if ($secondsRemaining > 0) {
            return back()->withErrors([
                'email' => $this->lockoutMessage($secondsRemaining),
            ])->onlyInput('email');
        }

        if (Auth::attempt([...$credentials, 'activo' => true])) {

            $loginThrottle->clear($credentials['email']);
            $request->session()->regenerate();

            if (Auth::user()->force_password_change) {
                return redirect()->route('password.change.form');
            }

            return redirect()->route('login');
        }

        // Registrar intento de login fallido en bitácora para trazabilidad
        $userFallido = \App\Models\User::where('email', $request->email)->first();
        if ($userFallido) {
            \App\Models\Bitacora::create([
                'caso_id'    => null,
                'user_id'    => $userFallido->id,
                'modulo'     => 'Seguridad',
                'accion'     => 'Login fallido',
                'descripcion'=> "Intento de acceso fallido para el usuario {$userFallido->email}.",
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        $lockMinutes = $loginThrottle->recordFailure($credentials['email']);

        return back()->withErrors([
            'email' => $lockMinutes === null
                ? 'Credenciales incorrectas'
                : "Demasiados intentos fallidos. Inténtalo nuevamente en {$lockMinutes} " . ($lockMinutes === 1 ? 'minuto.' : 'minutos.'),
        ])->onlyInput('email');
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function lockoutMessage(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return "Este correo está bloqueado temporalmente. Inténtalo nuevamente en {$minutes} " . ($minutes === 1 ? 'minuto.' : 'minutos.');
    }
}
