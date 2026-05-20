import { onBeforeUnmount } from 'vue';

/**
 * Suscribe a un canal privado y se desuscribe automáticamente al desmontar.
 * Si Echo no está configurado (driver=null/log), no hace nada.
 *
 * @param {string} channel  ej. 'check.01ks…' o 'kitchen.hot_kitchen'
 * @param {string} event    nombre del evento (sin namespace) ej. '.CheckUpdated'
 * @param {(payload:any)=>void} handler
 */
export function useChannel(channel, event, handler) {
    if (!window.Echo) return () => {};

    const ch = window.Echo.private(channel);
    ch.listen(event, handler);

    const cleanup = () => {
        try {
            window.Echo.leave(`private-${channel}`);
        } catch {
            // ignore
        }
    };

    onBeforeUnmount(cleanup);
    return cleanup;
}
