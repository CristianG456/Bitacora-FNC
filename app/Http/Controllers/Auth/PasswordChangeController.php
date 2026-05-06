<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Bitacora;

class PasswordChangeController extends Controller
{
    public function show()
    {
        // Si el usuario no requiere cambio de contraseña o es Administrador, enviarlo al dashboard
        if (!Auth::user()->password_change_required || Auth::user()->esAdministrador()) {
            return redirect()->route('dashboard');
        }

        return view('auth.cambiar-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = Auth::user();
        
        $user->update([
            'password' => Hash::make($request->password),
            'password_change_required' => false,
        ]);

        // Registrar en bitácora
        Bitacora::create([
            'caso_id'     => null,
            'user_id'     => $user->id,
            'modulo'      => 'Seguridad',
            'accion'      => 'Cambio de contraseña obligatorio',
            'descripcion' => "El usuario {$user->name} ({$user->email}) ha cambiado su contraseña correctamente en su primer inicio de sesión.",
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'created_at'  => now(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }
}
