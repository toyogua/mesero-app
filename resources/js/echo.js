import Echo from 'laravel-echo';
import * as Ably from 'ably';
import axios from 'axios';

const driver = import.meta.env.VITE_BROADCAST_CONNECTION || 'null';

axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

let echo = null;

if (driver === 'reverb') {
    const Pusher = (await import('pusher-js')).default;
    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
    });
} else if (driver === 'ably') {
    const { default: AblyEcho } = await import('@ably/laravel-echo');

    window.Ably = Ably;
    echo = new AblyEcho({ broadcaster: 'ably' });
}

window.Echo = echo;

export default echo;
