import { reactive } from 'vue';

const toasts = reactive([]);
let nextId = 0;

export function useToast() {
    function add(message, tone = 'info', duration = 4000) {
        const id = ++nextId;
        toasts.push({ id, message, tone });
        setTimeout(() => remove(id), duration);
    }

    function remove(id) {
        const idx = toasts.findIndex((t) => t.id === id);
        if (idx !== -1) toasts.splice(idx, 1);
    }

    return { toasts, add, remove };
}
