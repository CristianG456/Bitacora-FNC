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
            ]);

            // Guardar solicitud (simple por ahora)
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => bcrypt(now()),
                'created_at' => now()
            ]);

            return back()->with('success', 'Solicitud enviada al administrador');
        }

        //login normal
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {

            $request->session()->regenerate();

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