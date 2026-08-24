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
        // Si el usuario no requiere cambio de contraseña, enviarlo al dashboard
        if (!Auth::user()->force_password_change) {
            return redirect()->route('dashboard');
        }

        return view('auth.cambiar-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => [
                'required',
                'confirmed',
                \Illuminate\Validation\Rules\Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
                function ($attribute, $value, $fail) {
                    if (\Illuminate\Support\Facades\Hash::check($value, \Illuminate\Support\Facades\Auth::user()->password)) {
                        $fail('La nueva contraseña no puede ser igual a la contraseña temporal o actual.');
                    }
                }
            ],
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = Auth::user();
        
        $user->update([
            'password' => Hash::make($request->password),
            'force_password_change' => false,
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
