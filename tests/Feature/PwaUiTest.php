<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaUiTest extends TestCase
{
    public function test_manifest_and_service_worker_are_privacy_safe(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Sistema de Gestión de Casos Jurídicos', $manifest['name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('apple-mobile-web-app-capable', $layout);
        $this->assertStringContainsString('apple-mobile-web-app-status-bar-style', $layout);
        $this->assertStringContainsString('apple-mobile-web-app-title', $layout);

        $worker = file_get_contents(public_path('service-worker.js'));
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString('new Response(', $worker);
        $this->assertStringNotContainsString("caches.put('/casos", $worker);
        $this->assertStringNotContainsString("caches.put('/mensajes", $worker);
        $this->assertStringContainsString('notificationclick', $worker);
    }

    public function test_mandatory_gate_has_no_bypass_and_native_permission_is_click_driven(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $manager = file_get_contents(resource_path('js/push-manager.js'));

        $this->assertStringContainsString('Notificaciones obligatorias', $layout);
        $this->assertStringContainsString('data-enable-notifications', $layout);
        $this->assertStringContainsString('Volver a comprobar', $layout);
        $this->assertStringNotContainsString('Ahora no', $layout);
        $this->assertStringNotContainsString('Más tarde', $layout);
        $this->assertStringNotContainsString('Omitir', $layout);
        $this->assertSame(1, substr_count($manager, 'Notification.requestPermission()'));
        $this->assertStringContainsString("Notification.permission === 'denied'", $manager);
        $this->assertStringContainsString("window.addEventListener('focus'", $manager);
    }

    public function test_ready_session_avoids_gate_flash_and_is_revalidated_silently(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $manager = file_get_contents(resource_path('js/push-manager.js'));
        $logout = file_get_contents(resource_path('js/logout-push.js'));
        $css = file_get_contents(public_path('css/pwa.css'));

        $this->assertStringContainsString("sessionStorage.getItem(pushReadyKey) === 'true'", $layout);
        $this->assertStringContainsString("Notification.permission === 'granted'", $layout);
        $this->assertStringContainsString("document.documentElement.classList.add('push-ready')", $layout);
        $this->assertStringContainsString('html.push-ready #push-requirement-gate', $css);
        $this->assertStringContainsString("sessionStorage.setItem(readyKey, 'true')", $manager);
        $this->assertStringContainsString('check({ silent: true, confirmBackend: true })', $manager);
        $this->assertStringContainsString('sessionStorage.removeItem(pushReadyKey(window.userId))', $logout);
    }

    public function test_ios_install_and_unsupported_states_are_distinct(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $manager = file_get_contents(resource_path('js/push-manager.js'));

        $this->assertStringContainsString('data-push-state="ios-install"', $layout);
        $this->assertStringContainsString('Añadir a pantalla de inicio', $layout);
        $this->assertStringContainsString('data-push-state="ios-unsupported"', $layout);
        $this->assertStringContainsString('Actualiza iOS para continuar', $layout);

        $iosInstallPosition = strpos($manager, 'if (isIos && !isStandalone)');
        $unsupportedPosition = strpos($manager, 'if (!supportsPush())');
        $this->assertNotFalse($iosInstallPosition);
        $this->assertNotFalse($unsupportedPosition);
        $this->assertLessThan($unsupportedPosition, $iosInstallPosition);
    }
}
