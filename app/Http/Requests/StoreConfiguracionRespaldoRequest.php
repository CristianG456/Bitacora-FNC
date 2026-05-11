<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConfiguracionRespaldoRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|string|max:255',
            'sender_email' => 'required|email|max:255',
            'sender_name' => 'required|string|max:255',
            'recipient_emails' => 'required|string',
            'backup_frequency' => 'required|in:diario,semanal,mensual',
            'backup_time' => 'required|date_format:H:i',
            'backup_password' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ];
    }
}
