<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorregirCasoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->esJuridica();
    }

    protected function prepareForValidation(): void
    {
        $documento = trim((string) $this->input('documento_solicitante', ''));

        $this->merge([
            'documento_solicitante' => $documento !== '' ? $documento : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo_correccion' => ['required', 'string', 'min:5', 'max:1000'],
            'tipo_solicitante' => ['required', 'in:persona,empresa'],
            'nombre_solicitante' => ['required', 'string', 'max:255'],
            'tipo_documento_solicitante_id' => [
                'nullable',
                Rule::exists('tipos_documento_solicitante', 'id')->where(function ($query) {
                    $aplicaA = $this->input('tipo_solicitante') === 'empresa' ? 'juridica' : 'natural';

                    $query->where('activo', true)->whereIn('aplica_a', [$aplicaA, 'ambos']);
                }),
            ],
            'documento_solicitante' => ['nullable', 'string', 'max:100'],
            'fecha_solicitud' => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'observacion_inicial' => ['nullable', 'string', 'max:2000'],
            'enlace_google_drive' => ['nullable', 'url'],
            'tipo_proceso_id' => ['required', 'exists:tipos_proceso,id'],
            'subtipo_proceso_id' => [
                'required',
                Rule::exists('subtipos_proceso', 'id')
                    ->where(fn ($query) => $query->where('tipo_id', $this->input('tipo_proceso_id'))),
            ],
            'confirmar_recalculo_ans' => ['nullable', 'accepted'],
        ];
    }
}
