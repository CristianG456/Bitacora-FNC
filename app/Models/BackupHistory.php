<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupHistory extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'file_size',
        'backup_type',
        'status',
        'storage_provider',
        'storage_path',
        'r2_uploaded_at',
        'checksum_sha256',
        'sent_to',
        'execution_time',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'r2_uploaded_at' => 'datetime',
            'file_size' => 'integer',
            'execution_time' => 'float',
        ];
    }
}
