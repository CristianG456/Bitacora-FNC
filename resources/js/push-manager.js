const gate = document.getElementById('push-requirement-gate');

if (gate) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const vapidKey = document.querySelector('meta[name="web-push-vapid-key"]')?.content || '';
    const storeUrl = gate.dataset.storeUrl;
    const sections = [...gate.querySelectorAll('[data-push-state]')];
    const status = gate.querySelector('[data-push-status]');
    const help = gate.querySelector('[data-browser-help]');
    const installBanner = document.getElementById('pwa-install-banner');
    let deferredInstallPrompt = null;
    let checking = false;

    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const supported = window.isSecureContext
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window;

    function state(name, message = '') {
        document.body.classList.add('push-gate-blocked');
        gate.hidden = false;
        sections.forEach(section => { section.hidden = section.dataset.pushState !== name; });
        if (status) status.textContent = message;
        installBanner?.setAttribute('hidden', '');
    }

    function unlock() {
        gate.hidden = true;
        document.body.classList.remove('push-gate-pending', 'push-gate-blocked');
        if (deferredInstallPrompt && !isStandalone) installBanner?.removeAttribute('hidden');
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

    async function ensureSubscription() {
        if (!vapidKey) throw new Error('Web Push aún no está configurado en este entorno.');
        const registration = await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        let subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey),
            });
        }
        await persist(subscription);
    }

    async function check() {
        if (checking) return;
        checking = true;
        try {
            if (!navigator.onLine) return state('offline');
            if (!supported) return state('unsupported');
            if (isIos && !isStandalone) return state('ios-install');
            if (Notification.permission === 'default') return state('default');
            if (Notification.permission === 'denied') return state('denied');
            state('working', 'Verificando la suscripción de este dispositivo…');
            await ensureSubscription();
            unlock();
        } catch (error) {
            state('error', error?.message || 'No fue posible activar las notificaciones.');
        } finally {
            checking = false;
        }
    }

    gate.querySelector('[data-enable-notifications]')?.addEventListener('click', async () => {
        if (!supported || Notification.permission !== 'default') return check();
        state('working', 'Responde al permiso nativo de tu navegador…');
        await Notification.requestPermission();
        await check();
    });
    gate.querySelectorAll('[data-recheck-notifications]').forEach(button => button.addEventListener('click', check));
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
    window.addEventListener('online', check);
    window.addEventListener('offline', () => state('offline'));
    window.addEventListener('focus', check);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') check();
    });

    check();
}
