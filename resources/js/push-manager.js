import {
    isIosDevice,
    isStandaloneApp,
    pushReadyKey,
    supportsIosWebPushVersion,
} from './push-platform';

const gate = document.getElementById('push-requirement-gate');

if (gate) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const vapidKey = document.querySelector('meta[name="web-push-vapid-key"]')?.content || '';
    const storeUrl = gate.dataset.storeUrl;
    const sections = [...gate.querySelectorAll('[data-push-state]')];
    const help = gate.querySelector('[data-browser-help]');
    const installBanner = document.getElementById('pwa-install-banner');
    const readyKey = pushReadyKey(window.userId);
    let deferredInstallPrompt = null;
    let checking = false;

    const isIos = isIosDevice();
    const isStandalone = isStandaloneApp();

    function supportsPush() {
        return window.isSecureContext
            && 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    }

    function hasRememberedReadyState() {
        try {
            return sessionStorage.getItem(readyKey) === 'true';
        } catch {
            return false;
        }
    }

    function rememberReadyState() {
        try {
            sessionStorage.setItem(readyKey, 'true');
        } catch {
            // El gate sigue siendo seguro aunque el navegador bloquee sessionStorage.
        }
        document.documentElement.classList.add('push-ready');
    }

    function clearReadyState() {
        try {
            sessionStorage.removeItem(readyKey);
        } catch {
            // No hay estado sensible que recuperar o limpiar fuera de esta sesion.
        }
        document.documentElement.classList.remove('push-ready');
    }

    function state(name, message = '') {
        clearReadyState();
        document.body.classList.add('push-gate-blocked');
        gate.hidden = false;
        sections.forEach(section => { section.hidden = section.dataset.pushState !== name; });
        const activeStatus = sections.find(section => section.dataset.pushState === name)?.querySelector('[data-push-status]');
        if (activeStatus) activeStatus.textContent = message;
        installBanner?.setAttribute('hidden', '');
    }

    function revealApplication() {
        gate.hidden = true;
        document.body.classList.remove('push-gate-pending', 'push-gate-blocked');
        if (deferredInstallPrompt && !isStandalone) installBanner?.removeAttribute('hidden');
    }

    function unlock() {
        rememberReadyState();
        revealApplication();
        window.dispatchEvent(new CustomEvent('push:ready'));
    }

    function browserHelp() {
        if (isIos) {
            return 'En iPhone o iPad, instala primero el sistema desde Compartir → Añadir a pantalla de inicio. Abre la aplicación instalada y permite las notificaciones cuando se soliciten.';
        }
        if (/safari/i.test(navigator.userAgent) && !/chrome|chromium|edg/i.test(navigator.userAgent)) {
            return 'En Safari para macOS, abre Safari → Configuración → Sitios web → Notificaciones, busca este sitio y selecciona Permitir.';
        }
        if (/edg/i.test(navigator.userAgent)) {
            return 'En Edge, pulsa el icono junto a la dirección → Permisos para este sitio → Notificaciones → Permitir. Regresa y pulsa “Volver a comprobar”.';
        }
        return 'En Chrome o Firefox, pulsa el icono junto a la dirección → Configuración o permisos del sitio → Notificaciones → Permitir. Regresa y pulsa “Volver a comprobar”.';
    }

    function urlBase64ToUint8Array(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), character => character.charCodeAt(0));
    }

    async function persist(subscription) {
        const json = subscription.toJSON();
        const response = await fetch(storeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                ...json,
                contentEncoding: PushManager.supportedContentEncodings?.[0] || 'aes128gcm',
                device_name: navigator.userAgentData?.platform || navigator.platform || null,
            }),
        });
        if (!response.ok) throw new Error(`No se pudo registrar este dispositivo (${response.status}).`);
        const result = await response.json();
        if (!result.registered) throw new Error('El servidor no confirmó la suscripción.');
        document.querySelectorAll('[data-push-endpoint]').forEach(input => { input.value = subscription.endpoint; });
    }

    async function getRegistrationAndSubscription() {
        if (!vapidKey) throw new Error('Web Push aún no está configurado en este entorno.');
        await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        return { registration, subscription };
    }

    async function check({ silent = false, confirmBackend = true } = {}) {
        if (checking) return;
        checking = true;
        try {
            if (!navigator.onLine) return state('offline');
            if (isIos && !supportsIosWebPushVersion()) return state('ios-unsupported');
            if (isIos && !isStandalone) return state('ios-install');
            if (!supportsPush()) return state('unsupported');
            if (Notification.permission === 'default') return state('default');
            if (Notification.permission === 'denied') return state('denied');

            const { registration, subscription: currentSubscription } = await getRegistrationAndSubscription();
            let subscription = currentSubscription;
            let created = false;

            if (!subscription) {
                state('working', 'Creando la suscripción de este dispositivo…');
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey),
                });
                created = true;
            } else if (!silent) {
                state('working', 'Verificando la suscripción de este dispositivo…');
            }

            if (confirmBackend || created) await persist(subscription);
            unlock();
        } catch (error) {
            state('error', error?.message || 'No fue posible activar las notificaciones.');
        } finally {
            checking = false;
        }
    }

    gate.querySelector('[data-enable-notifications]')?.addEventListener('click', async () => {
        if (!supportsPush() || Notification.permission !== 'default') return check();
        state('working', 'Responde al permiso nativo de tu navegador…');
        await Notification.requestPermission();
        await check();
    });
    gate.querySelectorAll('[data-recheck-notifications]').forEach(button => button.addEventListener('click', () => check()));
    gate.querySelector('[data-show-help]')?.addEventListener('click', () => {
        help.textContent = browserHelp();
        help.hidden = false;
    });

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        deferredInstallPrompt = event;
        if (Notification.permission === 'granted' && gate.hidden) installBanner?.removeAttribute('hidden');
    });
    document.querySelector('[data-install-pwa]')?.addEventListener('click', async () => {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        await deferredInstallPrompt.userChoice;
        deferredInstallPrompt = null;
        installBanner?.setAttribute('hidden', '');
    });
    window.addEventListener('appinstalled', () => installBanner?.setAttribute('hidden', ''));
    window.addEventListener('online', () => check({ silent: hasRememberedReadyState(), confirmBackend: false }));
    window.addEventListener('offline', () => state('offline'));
    window.addEventListener('focus', () => check({ silent: hasRememberedReadyState(), confirmBackend: false }));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            check({ silent: hasRememberedReadyState(), confirmBackend: false });
        }
    });

    const canFastReveal = hasRememberedReadyState()
        && 'Notification' in window
        && Notification.permission === 'granted';

    if (canFastReveal) {
        revealApplication();
        check({ silent: true, confirmBackend: true });
    } else {
        clearReadyState();
        check();
    }
}
