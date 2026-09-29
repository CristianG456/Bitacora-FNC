<?php

namespace App\Support;

use Base64Url\Base64Url;
use ErrorException;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\ECKey;
use Minishlink\WebPush\VAPID;
use RuntimeException;
use Throwable;

class VapidKeyStore
{
    /** @return array{subject: string, public_key: string, private_key: string} */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('No existe una identidad VAPID persistida.');
        }

        $contents = file_get_contents($path);
        $values = is_string($contents) ? json_decode($contents, true) : null;
        if (! is_array($values)) {
            throw new RuntimeException('El archivo VAPID persistido no contiene JSON válido.');
        }

        $identity = [
            'subject' => $values['subject'] ?? null,
            'public_key' => $values['public_key'] ?? null,
            'private_key' => $values['private_key'] ?? null,
        ];
        $this->validate($identity);

        return $identity;
    }

    /**
     * @param  array{subject: mixed, public_key: mixed, private_key: mixed}  $environment
     * @return array{vapid: array{subject: ?string, public_key: ?string, private_key: ?string}, source: string}
     */
    public function resolve(array $environment, string $defaultSubject, string $path): array
    {
        $public = $this->filledString($environment['public_key'] ?? null);
        $private = $this->filledString($environment['private_key'] ?? null);
        $subject = $this->filledString($environment['subject'] ?? null) ?: $defaultSubject;

        if (($public !== null) xor ($private !== null)) {
            return [
                'vapid' => ['subject' => $subject, 'public_key' => $public, 'private_key' => $private],
                'source' => 'environment_incomplete',
            ];
        }

        if ($public !== null && $private !== null) {
            return [
                'vapid' => ['subject' => $subject, 'public_key' => $public, 'private_key' => $private],
                'source' => 'environment',
            ];
        }

        if (is_file($path)) {
            try {
                return ['vapid' => $this->load($path), 'source' => 'persistent_file'];
            } catch (Throwable) {
                return [
                    'vapid' => ['subject' => $subject, 'public_key' => null, 'private_key' => null],
                    'source' => 'persistent_file_invalid',
                ];
            }
        }

        return [
            'vapid' => ['subject' => $subject, 'public_key' => null, 'private_key' => null],
            'source' => 'unconfigured',
        ];
    }

    /**
     * Persist an identity exactly once. Returns false when another process already created it.
     *
     * @param  array{subject: string, public_key: string, private_key: string}  $identity
     */
    public function create(string $path, array $identity): bool
    {
        $this->validate($identity);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio privado para VAPID.');
        }

        @chmod($directory, 0700);
        $lockPath = $path.'.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('No fue posible bloquear la inicialización VAPID.');
        }

        @chmod($lockPath, 0600);
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('No fue posible adquirir el bloqueo VAPID.');
            }
            if (is_file($path)) {
                $this->load($path);

                return false;
            }

            $temporary = $path.'.tmp.'.bin2hex(random_bytes(8));
            $payload = json_encode(['version' => 1, ...$identity], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            if (file_put_contents($temporary, $payload, LOCK_EX) === false) {
                throw new RuntimeException('No fue posible persistir la identidad VAPID.');
            }
            @chmod($temporary, 0600);
            if (! rename($temporary, $path)) {
                @unlink($temporary);
                throw new RuntimeException('No fue posible publicar la identidad VAPID persistida.');
            }
            @chmod($path, 0600);

            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param array{subject: mixed, public_key: mixed, private_key: mixed} $identity */
    public function validate(array $identity): void
    {
        $subject = $this->filledString($identity['subject'] ?? null);
        $public = $this->filledString($identity['public_key'] ?? null);
        $private = $this->filledString($identity['private_key'] ?? null);
        if ($subject === null || ! $this->validSubject($subject)) {
            throw new RuntimeException('VAPID_SUBJECT debe ser una URL válida o un correo con prefijo mailto:.');
        }
        if ($public === null || $private === null) {
            throw new RuntimeException('La identidad VAPID debe contener el par público y privado completo.');
        }

        try {
            VAPID::validate(['subject' => $subject, 'publicKey' => $public, 'privateKey' => $private]);
        } catch (ErrorException $exception) {
            throw new RuntimeException('La identidad VAPID no tiene un formato válido.', previous: $exception);
        }

        if (! $this->isMatchingPair($public, $private)) {
            throw new RuntimeException('VAPID_PUBLIC_KEY y VAPID_PRIVATE_KEY no pertenecen al mismo par.');
        }
    }

    private function filledString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function validSubject(string $subject): bool
    {
        if (str_starts_with($subject, 'mailto:')) {
            return filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL) !== false;
        }

        return filter_var($subject, FILTER_VALIDATE_URL) !== false;
    }

    private function isMatchingPair(string $public, string $private): bool
    {
        try {
            $publicBytes = Base64Url::decode($public);
            $privateBytes = Base64Url::decode($private);
            if (strlen($publicBytes) !== 65 || $publicBytes[0] !== chr(4)) {
                return false;
            }

            $values = [
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => Base64Url::encode(substr($publicBytes, 1, 32)),
                'y' => Base64Url::encode(substr($publicBytes, 33, 32)),
            ];
            $publicPem = ECKey::convertToPEM(new JWK($values));
            $privatePem = ECKey::convertToPEM(new JWK([
                ...$values,
                'd' => Base64Url::encode($privateBytes),
            ]));
            $signature = '';
            if (! openssl_sign('vapid-pair-check', $signature, $privatePem, OPENSSL_ALGO_SHA256)) {
                return false;
            }

            return openssl_verify('vapid-pair-check', $signature, $publicPem, OPENSSL_ALGO_SHA256) === 1;
        } catch (Throwable) {
            return false;
        }
    }
}
