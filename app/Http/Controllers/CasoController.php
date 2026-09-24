<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCasoRequest;
use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\Tarea;
use App\Models\TipoProceso;
use App\Models\TipoDocumentoSolicitante;
use App\Models\User;
use App\Support\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CasoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $puedeVerTodos = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']);
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica']);

        $query = $puedeVerTodos
            ? Caso::query()
            : Caso::whereHas('usuarios', fn($q) => $q
                ->where('users.id', $user->id)
                ->where('caso_usuario.activo', true));

        // Búsqueda
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('solicitante_nombre_snapshot', 'like', "%{$search}%")
                  ->orWhere('solicitante_documento_snapshot', 'like', "%{$search}%")
                  ->orWhereHas('solicitante', function ($q2) use ($search) {
                      $q2->where('nombre', 'like', "%{$search}%")
                         ->orWhere('documento', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por estado
        $estadoFiltro = $request->input('estado', 'Todos');
        if ($estadoFiltro !== 'Todos') {
            $query->where('estado', $estadoFiltro);
        }

        $casos = $query->with(['tipo', 'usuarios'])->latest()->paginate(10);

        return view('casos.index', compact('casos', 'search', 'estadoFiltro', 'esAdmin'));
    }

    public function show(Request $request, Caso $caso)
    {
        $user = Auth::user();
        $puedeVerTodos = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']);
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica']);
        $esConsultor = $user->esConsultor();

        // Obtener la asignación del usuario actual
        $usuarioAsignado = $caso->usuarios()->where('users.id', $user->id)->first();
        $esConsultor = $esConsultor
            || ($user->esAbogado() && (!$usuarioAsignado || !(bool) $usuarioAsignado->pivot->activo));
        $puedeCompletarPropias = !$esConsultor
            && $usuarioAsignado
            && (bool) $usuarioAsignado->pivot->activo;

        // Autorización
        if (!$puedeVerTodos) {
            if (!$usuarioAsignado || !$usuarioAsignado->pivot->activo) {
                abort(403, 'No tienes acceso a este caso.');
            }
        }

        // Si el usuario está asignado al caso (sea admin o no)
        if (!$esConsultor && $usuarioAsignado && $usuarioAsignado->pivot->activo) {
            
            // 1. Cambiar estado del usuario en el caso (Pivot)
            if ($usuarioAsignado->pivot->estado === 'Pendiente') {
                $caso->usuarios()->updateExistingPivot($user->id, ['estado' => 'En proceso']);
            }

            // 2. Cambio automático de estado a En proceso del CASO
            if ($caso->estado === 'Pendiente') {
                $caso->update(['estado' => 'En proceso']);
                
                Bitacora::registrar(
                    modulo: 'Casos',
                    accion: 'Cambio de Estado',
                    descripcion: "El caso pasó automáticamente a En proceso tras la revisión del usuario asignado ({$user->name}).",
                    casoId: $caso->id,
                    entidadId: $caso->id
                );
            }
        }

        $caso->load([
            'tipo', 'subtipo', 'solicitante.tipoDocumento', 'solicitanteTipoDocumento', 'creador',
            'usuarios' => fn($q) => $q->wherePivot('activo', true),
            'tareas.usuario',
            'tareas.solicitudesCorreccion' => fn($q) => $q->with(['solicitante', 'revisora'])->latest(),
            'tareas.versiones.correctora',
            'bitacoras' => fn($q) => $q->with('usuario')->latest(),
            'mensajes' => fn($q) => $q
                ->with(['autor', 'destinatario'])
                ->where(function ($mensajes) use ($user) {
                    $mensajes->whereNull('destinatario_id')
                        ->orWhere('user_id', $user->id)
                        ->orWhere('destinatario_id', $user->id);
                })
                ->oldest()
        ]);

        $destinatariosChat = $caso->usuarios
            ->concat([$caso->creador])
            ->concat($caso->mensajes->flatMap(fn($mensaje) => [$mensaje->autor, $mensaje->destinatario]))
            ->filter(fn($destinatario) => $destinatario && $destinatario->id !== $user->id)
            ->unique('id')
            ->values();

        $tipoChat = $request->query('chat') === 'directo' ? 'directo' : 'general';
        $interlocutorId = $tipoChat === 'directo' ? (int) $request->query('usuario') : null;

        if ($tipoChat === 'directo' && !$destinatariosChat->contains('id', $interlocutorId)) {
            $tipoChat = 'general';
            $interlocutorId = null;
        }

        $mensajesChat = $caso->mensajes
            ->when(
                $tipoChat === 'general',
                fn ($mensajes) => $mensajes->whereNull('destinatario_id'),
                fn ($mensajes) => $mensajes->filter(fn ($mensaje) =>
                    ($mensaje->user_id === $user->id && $mensaje->destinatario_id === $interlocutorId)
                    || ($mensaje->user_id === $interlocutorId && $mensaje->destinatario_id === $user->id)
                )
            )
            ->values();
        $conteosChat = $this->conteosMensajesNoLeidos($caso, $user);
        $tiposDocumento = TipoDocumentoSolicitante::where('activo', true)->orderBy('orden')->get();
        $tiposProceso = TipoProceso::with('subtipos')->get();

        return view('casos.show', compact(
            'caso',
            'esAdmin',
            'esConsultor',
            'destinatariosChat',
            'puedeCompletarPropias',
            'tipoChat',
            'interlocutorId',
            'mensajesChat',
            'conteosChat',
            'tiposDocumento',
            'tiposProceso'
        ));
    }

    public function crear()
    {
        $tipos = TipoProceso::with('subtipos')->get();
        $tiposDocumento = TipoDocumentoSolicitante::where('activo', true)->orderBy('orden')->get();
        $usuariosAnteriores = User::query()
            ->whereIn('id', old('usuarios', []))
            ->with('role:id,nombre')
            ->get(['id', 'name', 'email', 'rol_id']);

        return view('casos.crear', compact('tipos', 'tiposDocumento', 'usuariosAnteriores'));
    }

    public function guardar(StoreCasoRequest $request)
    {
        $data = $request->validated();

        try {
            $caso = DB::transaction(function () use ($data) {

            $tipo    = TipoProceso::findOrFail($data['tipo_proceso_id']);
            $subtipo = SubtipoProceso::findOrFail($data['subtipo_proceso_id']);

            // El documento identifica al solicitante, no al caso. Si no hay
            // documento se crea una fila separada para no mezclar personas.
            $documento = $data['documento_solicitante'] ?? null;
            $solicitante = $documento
                ? Solicitante::firstOrCreate(
                    ['documento' => $documento],
                    [
                        'nombre' => $data['nombre_solicitante'],
                        'tipo_solicitante' => $data['tipo_solicitante'],
                        'tipo_documento_solicitante_id' => $data['tipo_documento_solicitante_id'] ?? null,
                    ]
                )
                : Solicitante::create([
                    'nombre' => $data['nombre_solicitante'],
                    'documento' => null,
                    'tipo_solicitante' => $data['tipo_solicitante'],
                    'tipo_documento_solicitante_id' => $data['tipo_documento_solicitante_id'] ?? null,
                ]);

            // Generar radicado
            $radicado = Caso::generarRadicado($tipo, $subtipo);
            $ans = app('App\Services\AnsService')->snapshot($tipo);

            // Crear el caso
            $caso = Caso::create([
                'radicado'           => $radicado,
                'tipo_id'            => $tipo->id,
                'subtipo_id'         => $subtipo->id,
                'descripcion'        => $data['descripcion'],
                'observacion_inicial'=> $data['observacion_inicial'] ?? null,
                'link_drive'         => $data['enlace_google_drive'] ?? null,
                'solicitante_id'     => $solicitante->id,
                'solicitante_nombre_snapshot' => $data['nombre_solicitante'],
                'solicitante_tipo_snapshot' => $data['tipo_solicitante'],
                'solicitante_tipo_documento_id' => $data['tipo_documento_solicitante_id'] ?? null,
                'solicitante_documento_snapshot' => $documento,
                'fecha_solicitud'    => $data['fecha_solicitud'],
                ...$ans,
                'estado'             => 'Pendiente',
                'fecha_inicio'       => now()->toDateString(),
                'created_by'         => Auth::id(),
            ]);

            // Asignar Usuarios y Crear Tareas
            if (!empty($data['usuarios'])) {
                foreach ($data['usuarios'] as $userId) {
                    // Vincular el usuario al caso
                    $caso->usuarios()->attach($userId, [
                        'fecha_asignacion' => now(),
                        'estado'           => 'Pendiente',
                        'activo'           => true,
                    ]);

                    Notificacion::enviar(
                        $userId,
                        'Nuevo caso asignado',
                        "Se te ha asignado el caso radicado {$radicado}.",
                        'caso',
                        $caso->id
                    );

                    // Crear las tareas de este usuario
                    if (isset($data['tareas'][$userId])) {
                        foreach ($data['tareas'][$userId] as $indiceTarea => $descTarea) {
                            $tipoAccion = $data['tipos_tarea'][$userId][$indiceTarea] ?? 'normal';
                            $usuarioTarea = User::findOrFail($userId);
                            if ($tipoAccion === 'firma' && !$usuarioTarea->esAbogado()) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'tipos_tarea.'.$userId => 'Las tareas de firma solo pueden asignarse a usuarios con rol Abogado.',
                                ]);
                            }
                            $caso->tareas()->create([
                                'user_id'     => $userId,
                                'descripcion' => $descTarea,
                                'tipo_accion' => $tipoAccion,
                                'estado'      => 'Pendiente',
                            ]);

                            Notificacion::enviar(
                                (int) $userId,
                                'Nueva tarea asignada',
                                "Tienes una tarea pendiente en el caso {$radicado}.",
                                'tarea',
                                $caso->id
                            );
                        }
                    }
                }
            }

            // Bitácora
            Bitacora::registrar(
                modulo:      'Casos',
                accion:      'Crear',
                descripcion: "El caso con radicado {$radicado} fue creado por ".Auth::user()->name.".",
                casoId:      $caso->id,
                entidadId:   $caso->id,
                metadata:    [
                    'radicado'    => $radicado,
                    'tipo'        => $tipo->nombre,
                    'subtipo'     => $subtipo->nombre,
                    'solicitante' => $solicitante->nombre,
                ]
            );

            if ($caso->ans_fecha_limite) {
                Bitacora::registrar(
                    modulo: 'ANS',
                    accion: 'Asignar',
                    descripcion: "ANS asignado: {$caso->ans_dias} días. Fecha límite: {$caso->ans_fecha_limite->format('d/m/Y')}.",
                    casoId: $caso->id,
                    entidadId: $caso->id,
                    metadata: [
                        'dias' => $caso->ans_dias,
                        'tipo_dias' => $caso->ans_tipo_dias,
                        'fecha_inicio' => $caso->ans_fecha_inicio->toDateString(),
                        'fecha_limite' => $caso->ans_fecha_limite->toDateString(),
                    ],
                );
            }

            return $caso;
            });
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error(
                'No fue posible crear el caso; la transacción fue revertida.',
                [
                    'operation' => 'caso_create',
                    'user_id' => Auth::id(),
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No fue posible crear el caso. No se guardó información parcial.',
                ], 500);
            }

            return redirect()->route('casos.crear')
                ->withInput()
                ->with('error', 'No fue posible crear el caso. No se guardó información parcial.');
        }

        // Loop para enviar correos DESPUÉS de hacer commit a la base de datos
        if (!empty($data['usuarios'])) {
            foreach ($data['usuarios'] as $userId) {
                try {
                    $usuario = User::find($userId);
                    $tareasParaCorreo = $data['tareas'][$userId] ?? [];
                    \Illuminate\Support\Facades\Mail::to($usuario->email)->queue(new \App\Mail\CaseAssignedMail($caso, $usuario, $tareasParaCorreo));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Error enviando correo de asignación de caso a ' . $usuario->email . ': ' . $e->getMessage());
                }
            }
        }

        return redirect()->route('casos.show', $caso->id)
            ->with('success', "Caso {$caso->radicado} creado correctamente.");
    }

    public function asignarUsuario(Request $request, Caso $caso)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $userId = $request->input('user_id');

        if ($caso->usuarios()->where('users.id', $userId)->wherePivot('activo', true)->exists()) {
            return redirect()->back()->with('error', 'El usuario ya está asignado a este caso.');
        }

        // Check if user was previously assigned and deactivated
        $existente = $caso->usuarios()->where('users.id', $userId)->first();
        if ($existente) {
            $caso->usuarios()->updateExistingPivot($userId, ['activo' => true]);
        } else {
            $caso->usuarios()->attach($userId, [
                'fecha_asignacion' => now(),
                'estado'           => 'Pendiente',
                'activo'           => true,
            ]);
        }

        Notificacion::enviar(
            $userId,
            'Nuevo caso asignado',
            "Se te ha asignado el caso radicado {$caso->radicado}.",
            'caso',
            $caso->id
        );

        $usuario = User::find($userId);

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Asignacion',
            descripcion: "El usuario ".Auth::user()->name." asignó a {$usuario->name} a este caso.",
            casoId: $caso->id,
            entidadId: $usuario->id,
            usuarioAfectado: $usuario->id
        );

        try {
            \Illuminate\Support\Facades\Mail::to($usuario->email)->queue(new \App\Mail\CaseAssignedMail($caso, $usuario, []));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando correo de asignación de caso a ' . $usuario->email . ': ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Usuario asignado correctamente y notificado por correo.');
    }

    public function removerUsuario(Caso $caso, User $usuario)
    {
        // En lugar de hacer detach, cambiamos activo a false para no perder el historial de tareas
        $caso->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Remover',
            descripcion: "El usuario ".Auth::user()->name." desvinculó a {$usuario->name} de este caso.",
            casoId: $caso->id,
            entidadId: $usuario->id,
            usuarioAfectado: $usuario->id
        );

        return redirect()->back()->with('success', 'Usuario removido del caso correctamente.');
    }

    public function reemplazarUsuario(Request $request, Caso $caso, User $usuario)
    {
        $request->validate([
            'nuevo_user_id' => 'required|exists:users,id|different:'.$usuario->id
        ]);

        $nuevoUsuarioId = $request->input('nuevo_user_id');
        $nuevoUsuario = User::find($nuevoUsuarioId);

        DB::transaction(function () use ($caso, $usuario, $nuevoUsuarioId, $nuevoUsuario) {
            // 1. Asignar nuevo usuario si no existe o reactivarlo
            $existente = $caso->usuarios()->where('users.id', $nuevoUsuarioId)->first();
            if ($existente) {
                $caso->usuarios()->updateExistingPivot($nuevoUsuarioId, ['activo' => true]);
            } else {
                $caso->usuarios()->attach($nuevoUsuarioId, [
                    'fecha_asignacion' => now(),
                    'estado'           => 'Pendiente',
                    'activo'           => true,
                ]);
            }

            Notificacion::enviar(
                $nuevoUsuarioId,
                'Reasignación de caso',
                "Se te ha reasignado el caso radicado {$caso->radicado}.",
                'caso',
                $caso->id
            );

            // 2. Transferir todas las tareas del caso del usuario viejo al nuevo
            Tarea::where('caso_id', $caso->id)
                ->where('user_id', $usuario->id)
                ->update(['user_id' => $nuevoUsuarioId]);

            // 3. Desactivar el usuario viejo
            $caso->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);

            // 4. Registrar en bitácora
            Bitacora::registrar(
                modulo: 'Casos',
                accion: 'Reemplazar',
                descripcion: "El usuario ".Auth::user()->name." reemplazó a {$usuario->name} por {$nuevoUsuario->name} y le transfirió sus tareas.",
                casoId: $caso->id,
                entidadId: $nuevoUsuarioId,
                usuarioAfectado: $usuario->id
            );
        });

        return redirect()->back()->with('success', "{$usuario->name} ha sido reemplazado por {$nuevoUsuario->name} correctamente.");
    }

    public function enviarMensaje(Request $request, Caso $caso)
    {
        $this->autorizarAccesoCaso($caso, escritura: true);

        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:1000'],
            'destinatario_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $destinatarioId = isset($data['destinatario_id']) ? (int) $data['destinatario_id'] : null;

        if ($destinatarioId !== null) {
            $destinatarioValido = $caso->usuarios()
                ->where('users.id', $destinatarioId)
                ->wherePivot('activo', true)
                ->exists();

            $esCreador = $caso->created_by === $destinatarioId;
            $esParticipantePrevio = $caso->mensajes()
                ->whereNotNull('destinatario_id')
                ->where(function ($query) use ($destinatarioId) {
                    $query->where(fn($pair) => $pair
                        ->where('user_id', Auth::id())
                        ->where('destinatario_id', $destinatarioId))
                        ->orWhere(fn($pair) => $pair
                            ->where('user_id', $destinatarioId)
                            ->where('destinatario_id', Auth::id()));
                })
                ->exists();

            if (!$destinatarioValido && !$esCreador && !$esParticipantePrevio) {
                abort(403, 'El destinatario no está asignado activamente a este caso.');
            }
        }

        $mensaje = $caso->mensajes()->create([
            'user_id' => Auth::id(),
            'destinatario_id' => $destinatarioId,
            'mensaje' => $data['mensaje'],
            'created_at' => now(),
        ]);

        $mensaje->load(['autor', 'destinatario']);
        $this->notificarMensaje($caso, $mensaje);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'mensaje' => $this->serializarMensaje($mensaje, Auth::user()),
            ]);
        }

        return redirect()->route('casos.show', $caso->id)
            ->with('tab', 'mensajes') // Para abrir la tab correcta al recargar
            ->with('success', 'Mensaje enviado.');
    }

    public function getMensajesJson(Request $request, Caso $caso)
    {
        $this->autorizarAccesoCaso($caso);
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'chat' => ['nullable', 'in:general,directo'],
            'usuario' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $user = Auth::user();
        $tipoChat = $data['chat'] ?? null;
        $interlocutorId = isset($data['usuario']) ? (int) $data['usuario'] : null;

        if ($tipoChat === 'directo' && !$interlocutorId) {
            abort(422, 'Debes seleccionar un usuario para el chat directo.');
        }

        $mensajes = $caso->mensajes()
            ->with(['autor', 'destinatario'])
            ->where(fn($query) => $query->whereNull('destinatario_id')
                ->orWhere('user_id', $user->id)
                ->orWhere('destinatario_id', $user->id))
            ->when($tipoChat === 'general', fn ($query) => $query->whereNull('destinatario_id'))
            ->when($tipoChat === 'directo', fn ($query) => $query
                ->whereNotNull('destinatario_id')
                ->where(fn ($directos) => $directos
                    ->where(fn ($par) => $par
                        ->where('user_id', $user->id)
                        ->where('destinatario_id', $interlocutorId))
                    ->orWhere(fn ($par) => $par
                        ->where('user_id', $interlocutorId)
                        ->where('destinatario_id', $user->id))))
            ->when(isset($data['after_id']), fn($query) => $query->where('id', '>', $data['after_id']))
            ->oldest('id')
            ->get()
            ->map(fn($mensaje) => $this->serializarMensaje($mensaje, $user));

        return response()->json([
            'mensajes' => $mensajes,
            'conteos' => $this->conteosMensajesNoLeidos($caso, $user),
        ]);
    }

    public function marcarMensajesLeidos(Request $request, Caso $caso)
    {
        $this->autorizarAccesoCaso($caso);
        $data = $request->validate([
            'chat' => ['required', 'in:general,directo'],
            'usuario' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $user = Auth::user();
        $interlocutorId = isset($data['usuario']) ? (int) $data['usuario'] : null;

        if ($data['chat'] === 'directo' && !$interlocutorId) {
            abort(422, 'Debes seleccionar un usuario para el chat directo.');
        }

        $query = $user->notificaciones()
            ->where('tipo', 'mensaje')
            ->where('caso_id', $caso->id)
            ->where('leido', false)
            ->whereHas('mensajeRelacionado', function ($mensajes) use ($data, $user, $interlocutorId) {
                if ($data['chat'] === 'general') {
                    $mensajes->whereNull('destinatario_id');
                    return;
                }

                $mensajes->where('user_id', $interlocutorId)
                    ->where('destinatario_id', $user->id);
            });

        $query->update(['leido' => true]);

        return response()->json([
            'success' => true,
            'conteos' => $this->conteosMensajesNoLeidos($caso, $user),
            'mensajesSinLeer' => $user->notificaciones()
                ->where('tipo', 'mensaje')
                ->where('leido', false)
                ->count(),
        ]);
    }

    public function estadoJson(Caso $caso)
    {
        $this->autorizarAccesoCaso($caso);
        $user = Auth::user();
        $totalTareas = $caso->tareas()->count();
        $completadas = $caso->tareas()->where('estado', 'Completada')->count();
        $puedeAdministrar = $user->tieneAlgunRol(['Administrador', 'Juridica']);

        $tareas = $caso->tareas()
            ->with([
                'observacion',
                'solicitudesCorreccion' => fn ($query) => $query
                    ->with(['solicitante', 'revisora'])
                    ->latest(),
            ])
            ->when(!$user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']), fn($query) => $query->where('user_id', $user->id))
            ->orderBy('id')
            ->get()
            ->map(function (Tarea $tarea) {
                $solicitud = $tarea->solicitudesCorreccion->first();

                return [
                    'id' => $tarea->id,
                    'estado' => $tarea->estado,
                    'correccion_key' => $solicitud
                        ? implode(':', [$solicitud->id, $solicitud->estado, $solicitud->updated_at?->getTimestamp()])
                        : 'none',
                    'correccion_html' => view('casos.partials.correccion-tarea', compact('tarea'))->render(),
                ];
            });
        $diasRestantesAns = app('App\Services\AnsService')->diasRestantes($caso);

        return response()->json([
            'estado' => $caso->estado,
            'progreso' => $totalTareas > 0 ? (int) round(($completadas / $totalTareas) * 100) : 0,
            'tareas_completadas' => $completadas,
            'tareas_total' => $totalTareas,
            'puede_finalizar' => $puedeAdministrar
                && $caso->estado !== 'Finalizado'
                && $caso->puedeFinalizarse(),
            'tareas' => $tareas,
            'ans' => [
                'estado' => $caso->ans_estado,
                'dias_restantes' => $diasRestantesAns,
                'fecha_limite' => $caso->ans_fecha_limite?->format('d/m/Y'),
            ],
        ]);
    }

    public function finalizar(Request $request, Caso $caso)
    {
        if (!$caso->puedeFinalizarse()) {
            return redirect()->back()->with('error', 'No se puede finalizar el caso porque tiene tareas pendientes.');
        }

        if ($caso->estado === 'Finalizado') {
            return redirect()->back()->with('error', 'El caso ya se encuentra finalizado.');
        }

        $caso->update([
            'estado' => 'Finalizado',
            'fecha_fin' => now('America/Bogota')->toDateString(),
        ]);

        app('App\Services\AnsService')->cerrarSeguimiento($caso->fresh());

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Cambio de Estado',
            descripcion: "El caso fue marcado como Finalizado por ".Auth::user()->name.".",
            casoId: $caso->id,
            entidadId: $caso->id
        );

        // Notificar a todos los usuarios asignados
        foreach ($caso->usuarios as $usuario) {
            Notificacion::enviar(
                $usuario->id,
                'Caso Finalizado',
                "El caso radicado {$caso->radicado} en el que estabas asignado ha sido finalizado.",
                'success',
                $caso->id
            );
        }

        return redirect()->back()->with('success', 'El caso ha sido finalizado exitosamente.');
    }

    private function autorizarAccesoCaso(Caso $caso, bool $escritura = false): void
    {
        $user = Auth::user();

        if ($escritura && $user->esConsultor()) {
            abort(403, 'El rol Consultor es de solo lectura.');
        }

        if ($escritura && $user->esAbogado()) {
            $asignado = $caso->usuarios()
                ->where('users.id', $user->id)
                ->wherePivot('activo', true)
                ->exists();
            abort_unless($asignado, 403, 'El Abogado solo puede intervenir cuando está asignado activamente.');

            return;
        }

        if ($user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado'])) {
            return;
        }

        $asignado = $caso->usuarios()
            ->where('users.id', $user->id)
            ->wherePivot('activo', true)
            ->exists();

        abort_unless($asignado, 403, 'No tienes acceso a este caso.');
    }

    private function serializarMensaje($mensaje, User $user): array
    {
        return [
            'id' => $mensaje->id,
            'esMio' => $mensaje->user_id === $user->id,
            'autor' => $mensaje->user_id === $user->id ? 'Tú' : ($mensaje->autor?->name ?? 'Usuario'),
            'mensaje' => $mensaje->mensaje,
            'fecha' => LocalDate::inBogota($mensaje->created_at)?->locale('es')->translatedFormat('d M, H:i \h'),
            'esDirecto' => $mensaje->destinatario_id !== null,
            'destinatarioId' => $mensaje->destinatario_id,
            'destinatario' => $mensaje->destinatario?->name,
        ];
    }

    private function conteosMensajesNoLeidos(Caso $caso, User $user): array
    {
        $notificaciones = $user->notificaciones()
            ->where('tipo', 'mensaje')
            ->where('caso_id', $caso->id)
            ->where('leido', false)
            ->with('mensajeRelacionado:id,user_id,destinatario_id')
            ->get();

        $general = 0;
        $directos = [];

        foreach ($notificaciones as $notificacion) {
            $mensaje = $notificacion->mensajeRelacionado;

            if (!$mensaje) {
                continue;
            }

            if ($mensaje->destinatario_id === null) {
                $general++;
                continue;
            }

            $interlocutorId = $mensaje->user_id === $user->id
                ? $mensaje->destinatario_id
                : $mensaje->user_id;
            $directos[$interlocutorId] = ($directos[$interlocutorId] ?? 0) + 1;
        }

        return ['general' => $general, 'directos' => $directos];
    }

    private function notificarMensaje(Caso $caso, \App\Models\Mensaje $mensaje): void
    {
        $destinatarioId = $mensaje->destinatario_id;
        $ids = $destinatarioId
            ? collect([$destinatarioId])
            : $caso->usuarios()->wherePivot('activo', true)->pluck('users.id')->push($caso->created_by);

        foreach ($ids->unique() as $id) {
            if ((int) $id !== Auth::id()) {
                $esDirecto = $destinatarioId !== null;
                Notificacion::enviar(
                    (int) $id,
                    $esDirecto ? 'Nuevo mensaje directo' : 'Nuevo mensaje en un caso',
                    ($esDirecto ? 'Nuevo mensaje directo en el caso ' : 'Nuevo mensaje en el caso ').$caso->radicado.'.',
                    'mensaje',
                    $caso->id,
                    $mensaje->id
                );
            }
        }
    }
}
