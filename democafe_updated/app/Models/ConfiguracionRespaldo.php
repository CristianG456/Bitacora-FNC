<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'recipient_emails' => 'array',
            'smtp_password' => 'encrypted',
        ];
    }
}
