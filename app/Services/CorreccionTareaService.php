<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Notificacion;
use App\Models\Observacion;
use App\Models\SolicitudCorreccionTarea;
use App\Models\Tarea;
use App\Models\TareaVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorreccionTareaService
{
    public function solicitar(Tarea $tarea, User $usuario, string $motivo): SolicitudCorreccionTarea
    {
        return DB::transaction(function () use ($tarea, $usuario, $motivo) {
            $tarea = Tarea::query()->lockForUpdate()->findOrFail($tarea->id);
            $this->autorizarPropietario($tarea, $usuario);

            if ($tarea->estado !== 'Completada') {
                throw ValidationException::withMessages([
                    'motivo' => 'Solo pueden corregirse tareas ya completadas.',
                ]);
            }

            if ($tarea->solicitudesCorreccion()->where('activa', true)->exists()) {
                throw ValidationException::withMessages([
                    'motivo' => 'Ya existe una solicitud pendiente o aprobada para esta tarea.',
                ]);
            }

            $solicitud = $tarea->solicitudesCorreccion()->create([
                'solicitante_user_id' => $usuario->id,
                'motivo' => $motivo,
                'estado' => 'pendiente',
                'activa' => true,
            ]);

            User::query()
                ->where('activo', true)
                ->whereHas('role', fn ($query) => $query->where('nombre', 'Juridica'))
                ->each(function (User $juridica) use ($tarea, $usuario, $solicitud) {
                    Notificacion::enviar(
                        $juridica->id,
                        'Solicitud de corrección de tarea',
                        "{$usuario->name} solicita autorización para corregir una tarea del caso {$tarea->caso->radicado}.",
                        'correccion_tarea',
                        $tarea->caso_id,
                        null,
                        $tarea->id,
                        $solicitud->id,
                    );
                });

            Bitacora::registrar(
                modulo: 'Tareas',
                accion: 'Solicitar corrección',
                descripcion: "{$usuario->name} solicitó corregir la tarea '{$tarea->descripcion}'.",
                casoId: $tarea->caso_id,
                entidadId: $tarea->id,
                usuarioAfectado: $usuario->id,
                metadata: [
                    'solicitud_id' => $solicitud->id,
                    'tarea' => $tarea->descripcion,
                    'solicitante' => $usuario->name,
                    'motivo' => $motivo,
                    'observacion_original' => $tarea->observacion?->contenido,
                ],
            );

            return $solicitud;
        });
    }

    public function aprobar(SolicitudCorreccionTarea $solicitud, User $juridica): void
    {
        DB::transaction(function () use ($solicitud, $juridica) {
            $solicitud = SolicitudCorreccionTarea::query()->lockForUpdate()->findOrFail($solicitud->id);
            $this->autorizarJuridica($juridica);

            if ($solicitud->estado !== 'pendiente' || !$solicitud->activa) {
                throw ValidationException::withMessages(['solicitud' => 'La solicitud ya fue procesada.']);
            }

            $solicitud->update([
                'estado' => 'aprobada',
                'revisada_por' => $juridica->id,
                'aprobada_en' => now(),
            ]);

            Notificacion::enviar(
                $solicitud->solicitante_user_id,
                'Corrección de tarea aprobada',
                "Tu solicitud para corregir una tarea del caso {$solicitud->tarea->caso->radicado} fue aprobada.",
                'correccion_tarea',
                $solicitud->tarea->caso_id,
                null,
                $solicitud->tarea_id,
                $solicitud->id,
            );

            Bitacora::registrar(
                modulo: 'Tareas',
                accion: 'Aprobar corrección',
                descripcion: "{$juridica->name} autorizó la corrección de la tarea #".($solicitud->tarea->orden ?? $solicitud->tarea_id).".",
                casoId: $solicitud->tarea->caso_id,
                entidadId: $solicitud->tarea_id,
                usuarioAfectado: $solicitud->solicitante_user_id,
                metadata: [
                    'solicitud_id' => $solicitud->id,
                    'tarea' => $solicitud->tarea->descripcion,
                    'solicitante' => $solicitud->solicitante?->name,
                    'motivo' => $solicitud->motivo,
                    'autorizada_por' => $juridica->name,
                ],
            );
        });
    }

    public function rechazar(SolicitudCorreccionTarea $solicitud, User $juridica, ?string $motivo): void
    {
        DB::transaction(function () use ($solicitud, $juridica, $motivo) {
            $solicitud = SolicitudCorreccionTarea::query()->lockForUpdate()->findOrFail($solicitud->id);
            $this->autorizarJuridica($juridica);

            if ($solicitud->estado !== 'pendiente' || !$solicitud->activa) {
                throw ValidationException::withMessages(['solicitud' => 'La solicitud ya fue procesada.']);
            }

            $solicitud->update([
                'estado' => 'rechazada',
                'activa' => null,
                'revisada_por' => $juridica->id,
                'motivo_rechazo' => $motivo,
                'rechazada_en' => now(),
            ]);

            Notificacion::enviar(
                $solicitud->solicitante_user_id,
                'Corrección de tarea rechazada',
                "Tu solicitud para corregir una tarea del caso {$solicitud->tarea->caso->radicado} fue rechazada.",
                'correccion_tarea',
                $solicitud->tarea->caso_id,
                null,
                $solicitud->tarea_id,
                $solicitud->id,
            );

            Bitacora::registrar(
                modulo: 'Tareas',
                accion: 'Rechazar corrección',
                descripcion: "{$juridica->name} rechazó una solicitud de corrección de tarea.",
                casoId: $solicitud->tarea->caso_id,
                entidadId: $solicitud->tarea_id,
                usuarioAfectado: $solicitud->solicitante_user_id,
                metadata: [
                    'solicitud_id' => $solicitud->id,
                    'tarea' => $solicitud->tarea->descripcion,
                    'solicitante' => $solicitud->solicitante?->name,
                    'motivo' => $solicitud->motivo,
                    'rechazada_por' => $juridica->name,
                    'motivo_rechazo' => $motivo,
                ],
            );
        });
    }

    public function corregir(
        SolicitudCorreccionTarea $solicitud,
        Tarea $tarea,
        User $usuario,
        array $data,
    ): TareaVersion {
        return DB::transaction(function () use ($solicitud, $tarea, $usuario, $data) {
            $tarea = Tarea::query()->lockForUpdate()->findOrFail($tarea->id);
            $solicitud = SolicitudCorreccionTarea::query()->lockForUpdate()->findOrFail($solicitud->id);
            $this->autorizarPropietario($tarea, $usuario);

            if ($solicitud->tarea_id !== $tarea->id
                || $solicitud->solicitante_user_id !== $usuario->id
                || $solicitud->estado !== 'aprobada'
                || !$solicitud->activa) {
                throw new AuthorizationException('La autorización no es válida para esta tarea o ya fue utilizada.');
            }

            $observacion = $tarea->observacion;
            $antes = [
                'observacion' => $observacion?->contenido,
                'fecha_fin' => $tarea->fecha_fin?->setTimezone('America/Bogota')->format('Y-m-d H:i:s'),
            ];
            $fechaFin = CarbonImmutable::parse($data['fecha_fin'], 'America/Bogota');
            $despues = [
                'observacion' => $data['observacion'],
                'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
            ];
            $campos = collect($antes)
                ->filter(fn ($valor, $campo) => $valor !== $despues[$campo])
                ->keys()
                ->values()
                ->all();

            if ($campos === []) {
                throw ValidationException::withMessages([
                    'observacion' => 'No se detectaron cambios para aplicar.',
                ]);
            }

            $tarea->update(['fecha_fin' => $fechaFin]);
            Observacion::updateOrCreate(
                ['tarea_id' => $tarea->id],
                ['user_id' => $usuario->id, 'contenido' => $data['observacion'], 'created_at' => now()],
            );

            $version = TareaVersion::create([
                'tarea_id' => $tarea->id,
                'version' => ((int) $tarea->versiones()->max('version')) + 1,
                'datos_anteriores' => $antes,
                'datos_nuevos' => $despues,
                'campos_modificados' => $campos,
                'corregida_por' => $usuario->id,
                'solicitud_correccion_id' => $solicitud->id,
                'motivo' => $solicitud->motivo,
                'created_at' => now(),
            ]);

            $solicitud->update([
                'estado' => 'utilizada',
                'activa' => null,
                'utilizada_en' => now(),
            ]);

            Bitacora::registrar(
                modulo: 'Tareas',
                accion: 'Corregir tarea',
                descripcion: "{$usuario->name} corrigió información de la tarea '{$tarea->descripcion}'.",
                casoId: $tarea->caso_id,
                entidadId: $tarea->id,
                usuarioAfectado: $usuario->id,
                metadata: [
                    'solicitud_id' => $solicitud->id,
                    'tarea' => $tarea->descripcion,
                    'autorizada_por' => $solicitud->revisora?->name,
                    'motivo' => $solicitud->motivo,
                    'campos' => $campos,
                    'anterior' => $antes,
                    'nuevo' => $despues,
                ],
            );

            return $version;
        });
    }

    private function autorizarPropietario(Tarea $tarea, User $usuario): void
    {
        if ($usuario->esConsultor() || $tarea->user_id !== $usuario->id) {
            throw new AuthorizationException('Solo el usuario asignado puede solicitar o aplicar esta corrección.');
        }

        $activo = $tarea->caso->usuarios()
            ->where('users.id', $usuario->id)
            ->wherePivot('activo', true)
            ->exists();

        if (!$activo) {
            throw new AuthorizationException('El usuario ya no está asignado activamente al caso.');
        }
    }

    private function autorizarJuridica(User $usuario): void
    {
        if (!$usuario->esJuridica()) {
            throw new AuthorizationException('Solo Jurídica puede revisar solicitudes de corrección.');
        }
    }
}
