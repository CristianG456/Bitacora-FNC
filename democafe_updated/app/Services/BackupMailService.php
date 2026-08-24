<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Mail\Message;

class BackupMailService
{
    /**
     * Configura el mailer en tiempo de ejecución usando la configuración guardada.
     */
    protected function setMailConfig(ConfiguracionRespaldo $config)
    {
        config([
            'mail.mailers.smtp.host' => $config->smtp_host,
            'mail.mailers.smtp.port' => $config->smtp_port,
            'mail.mailers.smtp.encryption' => $config->smtp_encryption,
            'mail.mailers.smtp.username' => $config->smtp_username,
            'mail.mailers.smtp.password' => $config->smtp_password,
            'mail.from.address' => $config->sender_email,
            'mail.from.name' => $config->sender_name,
        ]);
        
        // Forzar a Laravel a purgar el mailer en caché para usar la nueva config
        app('mail.manager')->purge('smtp');
    }

    /**
     * Prueba la conexión SMTP enviando un correo simple.
     */
    public function testConnection(ConfiguracionRespaldo $config)
    {
        $this->setMailConfig($config);
        
        // Enviar a la primera dirección en la lista de receptores
        $to = is_array($config->recipient_emails) ? $config->recipient_emails[0] : explode(',', $config->recipient_emails)[0];
        
        Mail::raw('Esta es una prueba de configuración SMTP desde el Sistema de Bitácoras.', function (Message $message) use ($to) {
            $message->to(trim($to))
                    ->subject('Prueba de Conexión SMTP - Respaldo Sistema');
        });
    }

    /**
     * Envía el archivo de respaldo adjunto.
     */
    public function sendBackup(ConfiguracionRespaldo $config, string $zipPath)
    {
        $this->setMailConfig($config);

        $recipients = is_array($config->recipient_emails) ? $config->recipient_emails : explode(',', $config->recipient_emails);

        foreach ($recipients as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Mail::raw('Adjunto encontrarás el respaldo de la base de datos generado automáticamente por el sistema.', function (Message $message) use ($email, $zipPath) {
                    $message->to($email)
                            ->subject('Respaldo de Base de Datos - Sistema de Bitácoras')
                            ->attach($zipPath);
                });
            }
        }
    }
}
