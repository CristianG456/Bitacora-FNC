import { pushReadyKey } from './push-platform';

document.querySelectorAll('form[action$="/logout"]').forEach(form => {
    form.addEventListener('submit', async event => {
        if (form.dataset.pushLogoutPrepared === 'true') return;

        try {
            sessionStorage.removeItem(pushReadyKey(window.userId));
        } catch {
            // No se almacena informacion sensible y el cierre de sesion debe continuar.
        }

        if (!('serviceWorker' in navigator)) return;

        event.preventDefault();
        const submitter = event.submitter;

        try {
            const registration = await navigator.serviceWorker.getRegistration('/');
            const subscription = await registration?.pushManager.getSubscription();
            if (subscription) {
                let input = form.querySelector('[data-push-endpoint]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'push_endpoint';
                    input.dataset.pushEndpoint = '';
                    form.appendChild(input);
                }
                input.value = subscription.endpoint;
            }
        } finally {
            form.dataset.pushLogoutPrepared = 'true';
            if (submitter) form.requestSubmit(submitter);
            else form.requestSubmit();
        }
    });
});
