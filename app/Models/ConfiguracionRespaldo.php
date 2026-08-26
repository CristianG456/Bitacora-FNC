<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ConfiguracionRespaldo extends Model
{
    protected $table = 'backup_settings';

    protected $fillable = [
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'sender_email',
        'sender_name',
        'recipient_emails',
        'backup_frequency',
        'backup_time',
        'backup_password',
        'max_backups',
        'retention_days',
        'r2_enabled',
        'r2_bucket',
        'r2_path',
        'r2_retention_days',
        'is_active',
    ];

    protected $hidden = [
        'smtp_password',
        'backup_password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'recipient_emails' => 'array',
            'smtp_password' => 'encrypted',
        ];
    }

    /**
     * Cifra las nuevas contraseñas ZIP y permite leer registros históricos
     * que todavía estén en texto plano sin reescribirlos automáticamente.
     */
    protected function backupPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: fn (?string $value): ?string => filled($value)
                ? Crypt::encryptString($value)
                : $value,
        );
    }
}
