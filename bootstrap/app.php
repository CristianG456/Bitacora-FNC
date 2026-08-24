<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middleware de roles y contraseñas
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'force_password_change' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistory::class,
        ]);

        $middleware->redirectUsersTo('/dashboard');

        // Confiar en el proxy inverso (NPM/Cloudflare) para que Laravel
        // detecte HTTPS via X-Forwarded-Proto y genere URLs absolutas https
        // (corrige Mixed Content en formularios detras de proxy).
        $middleware->trustProxies(at: ['*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
