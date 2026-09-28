<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-vapid {--force : Reemplazar claves existentes}';

    protected $description = 'Genera claves VAPID locales y las guarda en .env sin mostrarlas';

    public function handle(): int
    {
        $path = base_path('.env');
        $contents = is_file($path) ? file_get_contents($path) : '';
        if (! $this->option('force') && preg_match('/^VAPID_PRIVATE_KEY=.+$/m', $contents)) {
            $this->info('VAPID ya está configurado. No se realizaron cambios.');
            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        $values = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
            'VAPID_SUBJECT' => 'https://juridica.comitetolima.com',
        ];

        foreach ($values as $name => $value) {
            $line = $name.'='.$value;
            if (preg_match('/^'.preg_quote($name, '/').'=.*$/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $line, $contents);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $contents);
        $this->info('Claves VAPID guardadas en .env. La clave privada no se mostró.');

        return self::SUCCESS;
    }
}
