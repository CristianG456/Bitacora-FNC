<?php

use App\Support\VapidKeyStore;

$configuredKeyFile = env('VAPID_KEY_FILE');
$keyFile = is_string($configuredKeyFile) && trim($configuredKeyFile) !== ''
    ? trim($configuredKeyFile)
    : storage_path('app/private/webpush-vapid.json');
$resolved = (new VapidKeyStore)->resolve([
    'subject' => env('VAPID_SUBJECT'),
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
], env('APP_URL', 'http://localhost'), $keyFile);

return [
    'vapid' => $resolved['vapid'],
    'source' => $resolved['source'],
    'key_file' => $keyFile,
];
