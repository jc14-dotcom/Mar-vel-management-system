import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

window.Pusher = Pusher;

const appKey = import.meta.env.VITE_REVERB_APP_KEY;
const forceTLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https';

const echo = appKey
    ? new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: appKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    })
    : null;

export default echo;
