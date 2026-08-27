<?php

namespace Tests\Unit;

use App\Models\ConfiguracionRespaldo;
use App\Services\BackupMailService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BackupMailServiceTest extends TestCase
{
    public function test_global_log_mailer_does_not_prevent_explicit_smtp_delivery(): void
    {
        config(['mail.default' => 'log']);
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->twice();
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldReceive('purge')->twice()->with('backup_smtp');
        $manager->shouldReceive('mailer')->once()->with('backup_smtp')->andReturnUsing(function () use ($mailer): Mailer {
            $this->assertSame('backup-smtp.example.test', config('mail.mailers.backup_smtp.host'));
            $this->assertSame(587, config('mail.mailers.backup_smtp.port'));
            $this->assertSame('backup@example.test', config('mail.mailers.backup_smtp.username'));
            $this->assertSame('smtp', config('mail.mailers.backup_smtp.scheme'));
            $this->assertNull(config('mail.mailers.backup_smtp.url'));

            return $mailer;
        });

        $sent = (new BackupMailService($manager))->sendBackup(
            $this->smtpConfig(['recipient_emails' => ['one@example.test', 'two@example.test']]),
            storage_path('framework/testing/not-sent.zip')
        );

        $this->assertSame(2, $sent);
        $this->assertSame('log', config('mail.default'));
    }

    public function test_sending_does_not_permanently_change_global_mail_configuration(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => 'global-smtp.example.test',
            'mail.mailers.smtp.port' => 2525,
            'mail.mailers.smtp.username' => 'global@example.test',
            'mail.mailers.smtp.password' => 'global-password',
            'mail.from.address' => 'global-from@example.test',
            'mail.from.name' => 'Global Mail',
        ]);
        $before = config('mail');

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once();
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldReceive('purge')->twice()->with('backup_smtp');
        $manager->shouldReceive('mailer')->once()->with('backup_smtp')->andReturn($mailer);

        (new BackupMailService($manager))->sendBackup($this->smtpConfig(), storage_path('framework/testing/not-sent.zip'));
        $after = config('mail');

        $this->assertSame($before, $after);
    }

    public function test_valid_recipients_are_trimmed_and_deduplicated(): void
    {
        $service = new BackupMailService(Mockery::mock(MailManager::class));

        $this->assertSame(
            ['one@example.test', 'two@example.test'],
            $service->validatedRecipients($this->smtpConfig([
                'recipient_emails' => ' one@example.test, two@example.test, one@example.test ',
            ]))
        );
    }

    public function test_invalid_and_empty_recipients_are_rejected_without_resolving_a_mailer(): void
    {
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldNotReceive('mailer');
        $service = new BackupMailService($manager);

        foreach ([['invalid-address'], [], '  ,  '] as $recipients) {
            try {
                $service->validatedRecipients($this->smtpConfig(['recipient_emails' => $recipients]));
                $this->fail('Los destinatarios inválidos o vacíos deben rechazarse.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_incomplete_smtp_configuration_is_rejected_without_real_delivery(): void
    {
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldNotReceive('mailer');

        $this->expectException(InvalidArgumentException::class);
        (new BackupMailService($manager))->sendBackup(
            new ConfiguracionRespaldo(['smtp_host' => 'smtp.example.test']),
            storage_path('framework/testing/not-sent.zip')
        );
    }

    public function test_disabled_email_does_not_resolve_or_use_a_mailer(): void
    {
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldNotReceive('purge');
        $manager->shouldNotReceive('mailer');
        $service = new BackupMailService($manager);

        $this->assertFalse($service->isEnabled(new ConfiguracionRespaldo));
    }

    public function test_transport_failure_is_propagated_and_never_reported_as_success(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => 'global-smtp.example.test',
            'mail.from.address' => 'global-from@example.test',
        ]);
        $before = config('mail');
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->andThrow(new RuntimeException('transport failed'));
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldReceive('purge')->twice()->with('backup_smtp');
        $manager->shouldReceive('mailer')->once()->with('backup_smtp')->andReturn($mailer);

        try {
            (new BackupMailService($manager))->sendBackup(
                $this->smtpConfig(),
                storage_path('framework/testing/not-sent.zip')
            );
            $this->fail('Un fallo de transporte no debe reportarse como envío exitoso.');
        } catch (RuntimeException) {
            $this->assertSame($before, config('mail'));
        }
    }

    public function test_sender_is_applied_to_the_message_without_changing_global_from(): void
    {
        config(['mail.from' => ['address' => 'global@example.test', 'name' => 'Global']]);
        $before = config('mail.from');
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->with(
            Mockery::type('string'),
            Mockery::on(function (callable $compose): bool {
                $email = new Email;
                $compose(new Message($email));

                $this->assertSame('backup@example.test', $email->getFrom()[0]->getAddress());
                $this->assertSame('Backups', $email->getFrom()[0]->getName());

                return true;
            })
        );
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldReceive('purge')->twice()->with('backup_smtp');
        $manager->shouldReceive('mailer')->once()->with('backup_smtp')->andReturn($mailer);

        (new BackupMailService($manager))->sendBackup($this->smtpConfig(), storage_path('framework/testing/not-sent.zip'));

        $this->assertSame($before, config('mail.from'));
    }

    public function test_consecutive_sends_use_isolated_smtp_configurations(): void
    {
        $before = config('mail');
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->twice();
        $manager = Mockery::mock(MailManager::class);
        $manager->shouldReceive('purge')->times(4)->with('backup_smtp');
        $seenHosts = [];
        $manager->shouldReceive('mailer')->twice()->with('backup_smtp')->andReturnUsing(
            function () use ($mailer, &$seenHosts): Mailer {
                $seenHosts[] = config('mail.mailers.backup_smtp.host');

                return $mailer;
            }
        );
        $service = new BackupMailService($manager);

        $service->sendBackup($this->smtpConfig(['smtp_host' => 'first.example.test']), storage_path('framework/testing/first.zip'));
        $service->sendBackup($this->smtpConfig(['smtp_host' => 'second.example.test']), storage_path('framework/testing/second.zip'));

        $this->assertSame(['first.example.test', 'second.example.test'], $seenHosts);
        $this->assertSame($before, config('mail'));
    }

    private function smtpConfig(array $overrides = []): ConfiguracionRespaldo
    {
        return new ConfiguracionRespaldo(array_merge([
            'smtp_host' => 'backup-smtp.example.test',
            'smtp_port' => 587,
            'smtp_username' => 'backup@example.test',
            'smtp_password' => 'not-a-real-secret',
            'smtp_encryption' => 'tls',
            'sender_email' => 'backup@example.test',
            'sender_name' => 'Backups',
            'recipient_emails' => ['recipient@example.test'],
        ], $overrides));
    }
}
