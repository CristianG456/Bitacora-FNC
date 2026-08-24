import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: window.location.port || (window.location.protocol === 'https:' ? 443 : 80),
    wssPort: window.location.port || (window.location.protocol === 'https:' ? 443 : 80),
    forceTLS: window.location.protocol === 'https:',
    enabledTransports: ['ws', 'wss'],
});
