<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCasoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tipo_proceso_id'    => ['required', 'exists:tipos_proceso,id'],
            'subtipo_proceso_id' => ['required', 'exists:subtipos_proceso,id'],
            'descripcion'        => ['required', 'string', 'max:1000'],
            'observacion_inicial'=> ['nullable', 'string', 'max:2000'],
            'nombre_solicitante' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/'],
            'documento_solicitante' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'enlace_google_drive'   => ['nullable', 'url'],
            'usuarios'              => ['nullable', 'array'],
            'usuarios.*'            => ['exists:users,id'],
            'tareas'                => ['nullable', 'array'],
            'tareas.*'              => ['array'],
            'tareas.*.*'            => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_proceso_id.required'    => 'Debes seleccionar un tipo de caso.',
            'tipo_proceso_id.exists'      => 'El tipo de caso seleccionado no existe.',
            'subtipo_proceso_id.required' => 'Debes seleccionar un subtipo.',
            'subtipo_proceso_id.exists'   => 'El subtipo seleccionado no existe.',
            'descripcion.required'        => 'La descripción del caso es obligatoria.',
            'nombre_solicitante.required' => 'El nombre del solicitante es obligatorio.',
            'nombre_solicitante.regex'    => 'El nombre solo puede contener letras y espacios.',
            'documento_solicitante.required' => 'El documento del solicitante es obligatorio.',
            'documento_solicitante.regex'    => 'El documento solo puede contener números.',
            'enlace_google_drive.url'     => 'El enlace debe ser una URL válida.',
            'usuarios.*.exists'           => 'El usuario seleccionado no es válido.',
            'tareas.*.*.required'         => 'La descripción de la tarea es obligatoria.',
        ];
    }
}
