import Echo from 'laravel-echo';

const driver = import.meta.env.VITE_BROADCAST_CONNECTION || 'null';

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
    });
} else if (driver === 'ably') {
    const Ably = (await import('ably')).default;
    window.Ably = Ably;

    echo = new Echo({
        broadcaster: 'ably',
        key: import.meta.env.VITE_ABLY_PUBLIC_KEY,
    });
}

window.Echo = echo;

export default echo;
