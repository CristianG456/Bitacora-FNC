<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TareaRequest extends FormRequest
{
    /**
     * Solo usuarios autenticados pueden crear/editar tareas.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'user_id'     => ['required', 'exists:users,id'],
            'descripcion' => ['required', 'string', 'min:10', 'max:2000'],
            'estado'      => ['required', 'in:Pendiente,En proceso,Completada'],
            'orden'       => ['nullable', 'integer', 'min:1'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin'    => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            // Observación obligatoria al crear / al cambiar estado a Completada
            'observacion' => [
                $this->isMethod('POST') || $this->input('estado') === 'Completada'
                    ? 'required'
                    : 'nullable',
                'string',
                'min:10',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'      => 'Debes seleccionar un usuario para la tarea.',
            'user_id.exists'        => 'El usuario seleccionado no existe.',
            'descripcion.required'  => 'La descripción de la tarea es obligatoria.',
            'descripcion.min'       => 'La descripción debe tener al menos 10 caracteres.',
            'descripcion.max'       => 'La descripción no puede superar 2000 caracteres.',
            'estado.required'       => 'El estado de la tarea es obligatorio.',
            'estado.in'             => 'El estado debe ser: Pendiente, En proceso o Completada.',
            'orden.integer'         => 'El orden debe ser un número entero.',
            'orden.min'             => 'El orden debe ser mayor a 0.',
            'fecha_inicio.date'     => 'La fecha de inicio no es válida.',
            'fecha_fin.date'        => 'La fecha de finalización no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'observacion.required'  => 'La observación es obligatoria al crear una tarea o al marcarla como Completada.',
            'observacion.min'       => 'La observación debe tener al menos 10 caracteres.',
            'observacion.max'       => 'La observación no puede superar 2000 caracteres.',
        ];
    }
}
