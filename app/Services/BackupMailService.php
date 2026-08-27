<?php

namespace App\Services;

use App\Models\ConfiguracionRespaldo;
use Closure;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use InvalidArgumentException;

class BackupMailService
{
    private const MAILER_NAME = 'backup_smtp';

    public function __construct(protected MailManager $mailManager) {}

    public function isEnabled(ConfiguracionRespaldo $config): bool
    {
        return collect([
            $config->smtp_host,
            $config->smtp_port,
            $config->smtp_username,
            $config->sender_email,
            $config->recipient_emails,
        ])->contains(fn ($value) => filled($value));
    }

    /** @return list<string> */
    public function validatedRecipients(ConfiguracionRespaldo $config): array
    {
        $recipients = is_array($config->recipient_emails)
            ? $config->recipient_emails
            : explode(',', (string) $config->recipient_emails);

        $recipients = array_values(array_unique(array_filter(array_map('trim', $recipients))));

        if ($recipients === [] || collect($recipients)->contains(
            fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) === false
        )) {
            throw new InvalidArgumentException('La configuración SMTP no contiene destinatarios válidos.');
        }

        return $recipients;
    }

    public function testConnection(ConfiguracionRespaldo $config): void
    {
        $recipients = $this->validateConfiguration($config);

        $this->withBackupMailer($config, function (Mailer $mailer) use ($config, $recipients): void {
            $mailer->raw(
                'Esta es una prueba de configuración SMTP desde el Sistema de Bitácoras.',
                function (Message $message) use ($config, $recipients): void {
                    $message->from($config->sender_email, $config->sender_name)
                        ->to($recipients[0])
                        ->subject('Prueba de Conexión SMTP - Respaldo Sistema');
                }
            );
        });
    }

    public function sendBackup(ConfiguracionRespaldo $config, string $zipPath): int
    {
        $recipients = $this->validateConfiguration($config);

        return $this->withBackupMailer($config, function (Mailer $mailer) use ($config, $recipients, $zipPath): int {
            foreach ($recipients as $email) {
                $mailer->raw(
                    'Adjunto encontrarás el respaldo de la base de datos generado automáticamente por el sistema.',
                    function (Message $message) use ($config, $email, $zipPath): void {
                        $message->from($config->sender_email, $config->sender_name)
                            ->to($email)
                            ->subject('Respaldo de Base de Datos - Sistema de Bitácoras')
                            ->attach($zipPath);
                    }
                );
            }

            return count($recipients);
        });
    }

    /** @return list<string> */
    protected function validateConfiguration(ConfiguracionRespaldo $config): array
    {
        if (! filled($config->smtp_host)
            || ! filled($config->smtp_port)
            || ! filled($config->smtp_username)
            || ! filled($config->sender_email)
            || filter_var($config->sender_email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('La configuración SMTP está incompleta.');
        }

        return $this->validatedRecipients($config);
    }

    protected function withBackupMailer(ConfiguracionRespaldo $config, Closure $callback): mixed
    {
        $mailers = config('mail.mailers', []);
        $hadOriginalMailer = array_key_exists(self::MAILER_NAME, $mailers);
        $originalMailer = $mailers[self::MAILER_NAME] ?? null;

        try {
            config(['mail.mailers.'.self::MAILER_NAME => $this->smtpConfiguration($config)]);
            $this->mailManager->purge(self::MAILER_NAME);

            return $callback($this->mailManager->mailer(self::MAILER_NAME));
        } finally {
            try {
                $this->mailManager->purge(self::MAILER_NAME);
            } finally {
                $currentMailers = config('mail.mailers', []);

                if ($hadOriginalMailer) {
                    $currentMailers[self::MAILER_NAME] = $originalMailer;
                } else {
                    unset($currentMailers[self::MAILER_NAME]);
                }

                config(['mail.mailers' => $currentMailers]);
            }
        }
    }

    protected function smtpConfiguration(ConfiguracionRespaldo $config): array
    {
        $smtp = config('mail.mailers.smtp', []);
        $encryption = strtolower((string) $config->smtp_encryption);
        $scheme = in_array($encryption, ['ssl', 'smtps'], true) || (int) $config->smtp_port === 465
            ? 'smtps'
            : 'smtp';

        return array_replace(is_array($smtp) ? $smtp : [], [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'url' => null,
            'host' => $config->smtp_host,
            'port' => (int) $config->smtp_port,
            'encryption' => $config->smtp_encryption,
            'username' => $config->smtp_username,
            'password' => $config->smtp_password,
        ]);
    }
}
