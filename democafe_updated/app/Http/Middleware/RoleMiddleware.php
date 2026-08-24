<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Verifica que el usuario autenticado tenga alguno de los roles permitidos.
     *
     * Uso en rutas: ->middleware('role:Administrador,Juridica')
     *
     * @param  Closure(Request): (Response) 
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // 1. Verificar autenticación
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Debes iniciar sesión para continuar.');
        }

        $user = Auth::user();

        // 2. Verificar que el usuario esté activo
        if (!$user->activo) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Tu cuenta ha sido desactivada. Contacta al administrador.');
        }

        // 3. Verificar rol 
        if (!empty($roles)) {
            $rolUsuario = $user->role?->nombre;

            if (!in_array($rolUsuario, $roles)) {
                abort(403, 'No tienes permiso para acceder a esta sección.');
            }
        }

        return $next($request);
    }
}
