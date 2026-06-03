<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('area', 'like', "%{$search}%");
            });
        }

        $estado = $request->input('estado', 'Todos');
        if ($estado !== 'Todos') {
            $query->where('activo', $estado === 'Activos' ? 1 : 0);
        }

        if (!auth()->user()->tieneRol('Administrador')) {
            $query->whereDoesntHave('role', function ($q) {
                $q->where('nombre', 'Administrador');
            });
        }

        $usuarios = $query->orderBy('name')->paginate(10);

        // Solicitudes de recuperación de contraseña pendientes (últimas 48h)
        $solicitudesRecuperacion = \Illuminate\Support\Facades\DB::table('password_resets')
            ->where('created_at', '>=', now()->subHours(48))
            ->orderByDesc('created_at')
            ->get();

        return view('usuarios.index', compact('usuarios', 'search', 'estado', 'solicitudesRecuperacion'));
    }

    public function crear()
    {
        $roles = \App\Models\Rol::all();
        return view('usuarios.crear', compact('roles'));
    }

    public function guardar(\App\Http\Requests\StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        $data['activo'] = $request->has('activo');
        $data['force_password_change'] = true;

        $user = User::create($data);

        // La contraseña NO se envía por correo (canal inseguro).
        // El correo solo notifica la creación; el admin entrega la contraseña
        // temporal al usuario por un canal seguro (presencial / verificado).
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->queue(new \App\Mail\UserCreatedMail($user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo de creación de usuario a ' . $user->email . ': ' . $e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado correctamente. Recuerda comunicarle su contraseña temporal por un canal seguro.');
    }

    public function editar(User $usuario)
    {
        $roles = \App\Models\Rol::all();
        return view('usuarios.editar', compact('usuario', 'roles'));
    }

    public function actualizar(\App\Http\Requests\UpdateUserRequest $request, User $usuario)
    {
        $data = $request->validated();
        
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
            $data['force_password_change'] = true;
        } else {
            unset($data['password']);
        }
        
        $data['activo'] = $request->has('activo');

        $usuario->update($data);

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function cambiarEstado(Request $request, User $usuario)
    {
        $nuevoEstado = !$usuario->activo;
        $usuario->update(['activo' => $nuevoEstado]);

        // Registrar en bitácora: cambio de estado es evento de seguridad crítico
        \App\Models\Bitacora::registrar(
            modulo:          'Usuarios',
            accion:          $nuevoEstado ? 'Activar usuario' : 'Desactivar usuario',
            descripcion:     auth()->user()->name . ' ' . ($nuevoEstado ? 'activó' : 'desactivó') . ' la cuenta del usuario ' . $usuario->name . ' (' . $usuario->email . ').',
            entidadId:       $usuario->id,
            usuarioAfectado: $usuario->id
        );

        return redirect()->back()
            ->with('success', 'Estado del usuario actualizado correctamente.');
    }

    public function eliminar(User $usuario)
    {
        // Evitar que el usuario se elimine a sí mismo
        if (auth()->id() === $usuario->id) {
            return redirect()->back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        // Registrar en bitácora ANTES de eliminar para no perder la referencia
        \App\Models\Bitacora::registrar(
            modulo:          'Usuarios',
            accion:          'Eliminar usuario',
            descripcion:     auth()->user()->name . ' eliminó (soft delete) al usuario ' . $usuario->name . ' (' . $usuario->email . ').',
            entidadId:       $usuario->id,
            usuarioAfectado: $usuario->id
        );

        $usuario->delete();

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function buscar(Request $request)
    {
        $query = $request->input('q');

        $users = User::select('id', 'name', 'email')->where('activo', true);

        // Ocultar administradores si el usuario actual no es administrador
        if (!auth()->user()->tieneRol('Administrador')) {
            $users->whereDoesntHave('role', function ($q) {
                $q->where('nombre', 'Administrador');
            });
        }

        if ($query) {
            $users->where(function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('email', 'LIKE', "%{$query}%");
            });
        }

        $usuarios = $users->limit(20)->get();

        return response()->json($usuarios);
    }
}
