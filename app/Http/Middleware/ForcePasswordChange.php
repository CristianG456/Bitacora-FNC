<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->force_password_change) {
            // Permitir que el usuario vaya a la ruta de cambio de contraseña o logout
            if (!$request->routeIs('password.change.form') && 
                !$request->routeIs('password.change.update') && 
                !$request->routeIs('logout')) {
                return redirect()->route('password.change.form');
            }
        }

        return $next($request);
    }
}
