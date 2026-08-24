<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tieneRol('Administrador');
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/'],
            'email'    => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('usuario')->id ?? null),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'area'     => ['nullable', 'string', 'max:255'],
            'rol_id'   => ['required', 'exists:roles,id'],
            'activo'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'El nombre es obligatorio.',
            'name.regex'         => 'El nombre solo puede contener letras y espacios.',
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.email'        => 'El formato del correo es inválido.',
            'email.unique'       => 'Este correo ya está registrado en el sistema.',
            'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol_id.required'    => 'Debe seleccionar un rol para el usuario.',
            'rol_id.exists'      => 'El rol seleccionado no es válido.',
        ];
    }
}
