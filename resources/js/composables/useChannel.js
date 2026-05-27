import { onBeforeUnmount } from 'vue';

export function useChannel(channel, event, handler, { isPrivate = true } = {}) {
    if (!window.Echo) return () => {};

    const ch = isPrivate
        ? window.Echo.private(channel)
        : window.Echo.channel(channel);

    ch.listen(event, handler);

    const cleanup = () => {
        try {
            ch.stopListening(event, handler);
            window.Echo.leave(channel);
        } catch {
            // ignore
        }
    };

    onBeforeUnmount(cleanup);
    return cleanup;
}
