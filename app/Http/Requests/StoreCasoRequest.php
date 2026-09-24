<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCasoRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('casos.crear');
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $documento = trim((string) $this->input('documento_solicitante', ''));

        $this->merge([
            'documento_solicitante' => $documento !== '' ? $documento : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tipo_proceso_id'    => ['required', 'exists:tipos_proceso,id'],
            'subtipo_proceso_id' => [
                'required',
                Rule::exists('subtipos_proceso', 'id')
                    ->where(fn ($query) => $query->where('tipo_id', $this->input('tipo_proceso_id'))),
            ],
            'descripcion'        => ['required', 'string', 'max:1000'],
            'observacion_inicial'=> ['nullable', 'string', 'max:2000'],
            'tipo_solicitante'   => ['required', 'in:persona,empresa'],
            'nombre_solicitante' => ['required', 'string', 'max:255'],
            'tipo_documento_solicitante_id' => [
                'nullable',
                Rule::exists('tipos_documento_solicitante', 'id')->where(function ($query) {
                    $aplicaA = $this->input('tipo_solicitante') === 'empresa' ? 'juridica' : 'natural';

                    $query->where('activo', true)->whereIn('aplica_a', [$aplicaA, 'ambos']);
                }),
            ],
            'documento_solicitante' => ['nullable', 'string', 'max:100'],
            'fecha_solicitud'       => ['required', 'date'],
            'enlace_google_drive'   => ['nullable', 'url'],
            'usuarios'              => ['nullable', 'array'],
            'usuarios.*'            => ['distinct', 'exists:users,id'],
            'tareas'                => ['nullable', 'array'],
            'tareas.*'              => ['array'],
            'tareas.*.*'            => ['required', 'string', 'max:500'],
            'tipos_tarea'            => ['nullable', 'array'],
            'tipos_tarea.*'          => ['array'],
            'tipos_tarea.*.*'        => ['nullable', 'in:normal,firma'],
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
            'tipo_solicitante.required'   => 'Debes seleccionar si el solicitante es persona natural o jurídica.',
            'tipo_solicitante.in'         => 'El tipo de solicitante seleccionado no es válido.',
            'nombre_solicitante.required' => 'El nombre del solicitante es obligatorio.',
            'fecha_solicitud.required' => 'El día de solicitud es obligatorio.',
            'fecha_solicitud.date'     => 'El día de solicitud debe ser una fecha válida.',
            'enlace_google_drive.url'     => 'El enlace debe ser una URL válida.',
            'usuarios.*.exists'           => 'El usuario seleccionado no es válido.',
            'usuarios.*.distinct'         => 'No puedes asignar dos veces al mismo usuario.',
            'tareas.*.*.required'         => 'La descripción de la tarea es obligatoria.',
        ];
    }
}
