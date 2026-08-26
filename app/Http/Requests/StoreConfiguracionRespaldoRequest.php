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
            'smtp_host' => 'nullable|required_with:smtp_port,smtp_username,sender_email,recipient_emails|string|max:255',
            'smtp_port' => 'nullable|required_with:smtp_host,smtp_username,sender_email,recipient_emails|integer',
            'smtp_username' => 'nullable|required_with:smtp_host,smtp_port,sender_email,recipient_emails|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|string|max:255',
            'sender_email' => 'nullable|required_with:smtp_host,smtp_port,smtp_username,recipient_emails|email|max:255',
            'sender_name' => 'nullable|required_with:smtp_host,sender_email,recipient_emails|string|max:255',
            'recipient_emails' => 'nullable|required_with:smtp_host,smtp_port,smtp_username,sender_email|string',
            'backup_frequency' => 'required|in:diario,semanal,mensual',
            'backup_time' => 'required|date_format:H:i',
            'backup_password' => 'nullable|string|max:255',
            'max_backups' => 'nullable|integer|min:0',
            'retention_days' => 'nullable|integer|min:0',
            'r2_enabled' => 'boolean',
            'r2_bucket' => 'nullable|required_if:r2_enabled,1|string|max:255',
            'r2_path' => 'nullable|string|max:255',
            'r2_retention_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
