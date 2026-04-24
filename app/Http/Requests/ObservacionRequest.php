<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObservacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'contenido' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contenido.required' => 'El contenido de la observación es obligatorio.',
            'contenido.min'      => 'La observación debe tener al menos 10 caracteres.',
            'contenido.max'      => 'La observación no puede superar 2000 caracteres.',
        ];
    }
}
