<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    preparing:     { type: Array, default: () => [] },
    ready:         { type: Array, default: () => [] },
    station_codes: { type: Array, default: () => [] },
});

const now = ref(Date.now());
let clockTimer = null;
let pollTimer = null;
const subscriptions = [];

function refresh() {
    router.reload({ only: ['preparing', 'ready'], preserveScroll: true });
}

onMounted(() => {
    clockTimer = setInterval(() => (now.value = Date.now()), 1000);

    if (window.Echo) {
        const ch = window.Echo.channel('display');
        ch.listen('.KitchenQueueChanged', refresh);
        ch.listen('.CheckUpdated', refresh);
        subscriptions.push(ch);
    }

    pollTimer = setInterval(refresh, 20000);
});

onUnmounted(() => {
    clearInterval(clockTimer);
    clearInterval(pollTimer);
    for (const ch of subscriptions) {
        try {
            ch.stopListening('.KitchenQueueChanged');
            ch.stopListening('.CheckUpdated');
            window.Echo.leave('display');
        } catch {}
    }
});

function clock() {
    return new Date(now.value).toLocaleTimeString('es-GT', {
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Display — Órdenes" />

    <div class="min-h-screen bg-[#080808] text-white flex flex-col select-none overflow-hidden font-sans">

        <!-- Header -->
        <header class="flex items-center justify-between px-8 py-4 border-b border-white/8 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-2 h-8 rounded-full bg-white/20"></div>
                <span class="text-xl font-bold tracking-tight uppercase text-white/90">Tus órdenes</span>
            </div>
            <div class="font-numeric text-2xl font-light tabular-nums text-white/30 tracking-wider">
                {{ clock() }}
            </div>
        </header>

        <!-- Columns -->
        <div class="flex flex-1 min-h-0">

            <!-- EN PREPARACIÓN -->
            <section class="flex-1 flex flex-col min-w-0 border-r border-white/8">
                <div class="flex items-center justify-between px-8 py-4 border-b border-white/8 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse shadow-[0_0_8px_2px_rgba(251,191,36,0.5)]"></span>
                        <h2 class="text-xs font-bold uppercase tracking-[0.25em] text-amber-400">
                            En preparación
                        </h2>
                    </div>
                    <span
                        v-if="preparing.length"
                        class="font-numeric text-xs font-semibold tabular-nums px-2 py-0.5 rounded-full bg-amber-400/15 text-amber-400"
                    >
                        {{ preparing.length }}
                    </span>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <transition-group
                        name="order"
                        tag="div"
                        class="grid grid-cols-2 lg:grid-cols-3 gap-3 content-start"
                    >
                        <div
                            v-for="o in preparing"
                            :key="o.id"
                            class="order-card rounded-2xl border border-amber-400/20 bg-gradient-to-b from-amber-400/8 to-amber-400/3 flex flex-col items-center justify-center py-8 px-3 gap-3 min-h-[120px]"
                        >
                            <div class="text-[10px] uppercase tracking-[0.2em] text-amber-400/60 font-semibold text-center truncate w-full px-1">
                                {{ o.display_name || o.table_name || 'Mesa' }}
                            </div>
                            <span
                                v-if="o.order_type === 'takeout'"
                                class="text-[9px] uppercase tracking-widest font-bold px-2 py-0.5 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30"
                            >
                                Para llevar
                            </span>
                            <div class="font-numeric font-extrabold text-amber-300 leading-none text-center order-number">
                                {{ o.number }}
                            </div>
                        </div>
                    </transition-group>

                    <div
                        v-if="!preparing.length"
                        class="h-full min-h-[200px] flex flex-col items-center justify-center gap-3 text-white/15"
                    >
                        <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <span class="text-sm">Sin órdenes en preparación</span>
                    </div>
                </div>
            </section>

            <!-- LISTO PARA SERVIR -->
            <section class="flex-1 flex flex-col min-w-0">
                <div class="flex items-center justify-between px-8 py-4 border-b border-white/8 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_2px_rgba(52,211,153,0.5)]"></span>
                        <h2 class="text-xs font-bold uppercase tracking-[0.25em] text-emerald-400">
                            Listo para servir
                        </h2>
                    </div>
                    <span
                        v-if="ready.length"
                        class="font-numeric text-xs font-semibold tabular-nums px-2 py-0.5 rounded-full bg-emerald-400/15 text-emerald-400"
                    >
                        {{ ready.length }}
                    </span>
                </div>

                <div class="flex-1 overflow-y-auto p-6">
                    <transition-group
                        name="order"
                        tag="div"
                        class="grid grid-cols-2 lg:grid-cols-3 gap-3 content-start"
                    >
                        <div
                            v-for="o in ready"
                            :key="o.id"
                            class="order-card rounded-2xl border border-emerald-400/25 bg-gradient-to-b from-emerald-400/10 to-emerald-400/4 flex flex-col items-center justify-center py-8 px-3 gap-3 min-h-[120px] shadow-[inset_0_1px_0_rgba(52,211,153,0.1)]"
                        >
                            <div class="text-[10px] uppercase tracking-[0.2em] text-emerald-400/60 font-semibold text-center truncate w-full px-1">
                                {{ o.display_name || o.table_name || 'Mesa' }}
                            </div>
                            <span
                                v-if="o.order_type === 'takeout'"
                                class="text-[9px] uppercase tracking-widest font-bold px-2 py-0.5 rounded-full bg-emerald-400/20 text-emerald-300 border border-emerald-400/30"
                            >
                                Para llevar
                            </span>
                            <div class="font-numeric font-extrabold text-emerald-300 leading-none text-center order-number">
                                {{ o.number }}
                            </div>
                            <div class="text-[10px] uppercase tracking-widest text-emerald-400/50 font-medium">
                                ¡Pasar a buscar!
                            </div>
                        </div>
                    </transition-group>

                    <div
                        v-if="!ready.length"
                        class="h-full min-h-[200px] flex flex-col items-center justify-center gap-3 text-white/15"
                    >
                        <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-sm">Sin órdenes listas</span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.order-number {
    font-size: clamp(2rem, 5vw, 3.5rem);
    word-break: break-all;
    line-height: 1;
}

.order-enter-active,
.order-leave-active {
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.order-enter-from {
    opacity: 0;
    transform: scale(0.75) translateY(16px);
}
.order-leave-to {
    opacity: 0;
    transform: scale(0.85) translateY(-8px);
    transition-timing-function: ease-in;
}
.order-leave-active {
    position: absolute;
}
</style>
