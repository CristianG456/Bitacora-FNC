<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'recipient_emails' => [
                'nullable',
                'required_with:smtp_host,smtp_port,smtp_username,sender_email',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filled($value)) {
                        return;
                    }

                    $recipients = array_values(array_filter(array_map('trim', explode(',', (string) $value))));
                    if ($recipients === [] || collect($recipients)->contains(
                        fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) === false
                    )) {
                        $fail('Todos los correos destino deben ser direcciones válidas.');
                    }
                },
            ],
            'backup_frequency' => 'required|in:diario,semanal,mensual',
            'backup_time' => 'required|date_format:H:i',
            'backup_password' => 'nullable|string|max:255',
            'max_backups' => 'nullable|integer|min:0',
            'retention_days' => 'nullable|integer|min:0',
            'r2_enabled' => 'boolean',
            'r2_bucket' => 'nullable|required_if:r2_enabled,1|string|max:255',
            'r2_path' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filled($value)) {
                        return;
                    }

                    $normalized = trim(str_replace('\\', '/', (string) $value), '/');
                    if (collect(explode('/', $normalized))->contains(
                        fn (string $segment) => $segment === '' || $segment === '.' || $segment === '..'
                    )) {
                        $fail('La ruta R2 no puede contener segmentos vacíos, punto o retroceso.');
                    }
                },
            ],
            'r2_retention_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $smtpFields = [
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'sender_email',
                'sender_name',
                'recipient_emails',
            ];

            $emailEnabled = collect($smtpFields)->contains(fn (string $field) => filled($this->input($field)));
            if (! $emailEnabled) {
                return;
            }

            foreach (['smtp_host', 'smtp_port', 'smtp_username', 'sender_email', 'sender_name', 'recipient_emails'] as $required) {
                if (! filled($this->input($required))) {
                    $validator->errors()->add($required, 'Este campo es obligatorio cuando se configura el envío SMTP.');
                }
            }
        });
    }
}
