<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    stations: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
});

const activeStation = ref('all');
const now = ref(Date.now());

// Refresh timestamps cada 15s para que los tiempos se actualicen
let timer = null;
onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 15000);
});
onUnmounted(() => clearInterval(timer));

const stationOptions = computed(() => [
    { code: 'all', name: 'Todas', count: props.items.length },
    ...props.stations.map((s) => ({
        ...s,
        count: props.items.filter((i) => i.station_code === s.code).length,
    })),
]);

const filteredItems = computed(() => {
    if (activeStation.value === 'all') return props.items;
    return props.items.filter((i) => i.station_code === activeStation.value);
});

// Agrupado por comanda
const groupedByCheck = computed(() => {
    const map = new Map();
    for (const item of filteredItems.value) {
        if (!map.has(item.check_id)) {
            map.set(item.check_id, {
                check_id: item.check_id,
                check_number: item.check_number,
                table_name: item.table_name,
                area_name: item.area_name,
                covers: item.covers,
                opened_at: item.opened_at,
                items: [],
            });
        }
        map.get(item.check_id).items.push(item);
    }
    // Orden: comandas con items más viejos primero
    return Array.from(map.values()).sort((a, b) => {
        const aMin = Math.min(...a.items.map((i) => new Date(i.sent_at || 0).getTime()));
        const bMin = Math.min(...b.items.map((i) => new Date(i.sent_at || 0).getTime()));
        return aMin - bMin;
    });
});

function elapsed(iso) {
    if (!iso) return '—';
    const m = Math.floor((now.value - new Date(iso).getTime()) / 60000);
    if (m < 60) return `${m}m`;
    return `${Math.floor(m / 60)}h ${m % 60}m`;
}

function urgencyTone(iso) {
    if (!iso) return 'neutral';
    const m = (now.value - new Date(iso).getTime()) / 60000;
    if (m < 8) return 'ok';
    if (m < 18) return 'warn';
    return 'err';
}

function take(item) {
    router.post(`/check-items/${item.id}/take`, {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

function ready(item) {
    router.post(`/check-items/${item.id}/ready`, {}, {
        preserveScroll: true,
        preserveState: false,
    });
}

const statusMeta = {
    ordered: { tone: 'primary', label: 'Pedido' },
    preparing: { tone: 'warn', label: 'Preparando' },
    ready: { tone: 'ok', label: 'Listo' },
};
</script>

<template>
    <Head title="Cocina (KDS) — mesero-app" />
    <AppLayout title="Cocina">
        <template #actions>
            <span class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">
                Auto-refresh cada 15s
            </span>
        </template>

        <!-- Filtros por estación -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
            <button
                v-for="s in stationOptions"
                :key="s.code"
                type="button"
                class="h-10 px-4 rounded-full text-xs font-medium uppercase tracking-widest transition border focus-ring whitespace-nowrap flex items-center gap-2"
                :class="
                    activeStation === s.code
                        ? 'bg-[var(--color-fg)] text-[var(--color-bg)] border-transparent'
                        : 'text-[var(--color-fg-muted)] border-[var(--color-border-faint)] hover:border-[var(--color-border)]'
                "
                @click="activeStation = s.code"
            >
                {{ s.name }}
                <span
                    class="font-numeric text-[10px] px-1.5 py-0.5 rounded-full"
                    :class="
                        activeStation === s.code
                            ? 'bg-[var(--color-bg)]/20 text-[var(--color-bg)]'
                            : 'bg-[var(--color-surface)] text-[var(--color-fg-muted)]'
                    "
                >
                    {{ s.count }}
                </span>
            </button>
        </div>

        <!-- Empty -->
        <div
            v-if="!groupedByCheck.length"
            class="rounded-2xl border border-dashed border-[var(--color-border)] py-24 text-center"
        >
            <div class="text-4xl mb-3">🍳</div>
            <h2 class="text-xl font-semibold tracking-tight">Sin pedidos en cola</h2>
            <p class="text-sm text-[var(--color-fg-muted)] mt-1">
                Todo está bajo control. Buena onda.
            </p>
        </div>

        <!-- Grid de comandas -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <article
                v-for="g in groupedByCheck"
                :key="g.check_id"
                class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden flex flex-col"
            >
                <!-- Header -->
                <header class="px-5 py-3 border-b border-[var(--color-border-faint)] flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">
                            {{ g.area_name }} · {{ g.check_number }}
                        </div>
                        <div class="text-lg font-semibold tracking-tight">{{ g.table_name }}</div>
                    </div>
                    <div class="text-right">
                        <Badge :tone="urgencyTone(g.opened_at)" size="md">
                            {{ elapsed(g.opened_at) }}
                        </Badge>
                        <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)] mt-1">
                            {{ g.covers }} pers
                        </div>
                    </div>
                </header>

                <!-- Items -->
                <ul class="flex-1 divide-y divide-[var(--color-border-faint)]">
                    <li
                        v-for="item in g.items"
                        :key="item.id"
                        class="px-5 py-3 flex items-center gap-3"
                    >
                        <div class="font-numeric text-xl font-semibold text-[var(--color-primary)] w-8">
                            {{ item.quantity }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ item.name }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <Badge :tone="statusMeta[item.status].tone" size="sm">
                                    {{ statusMeta[item.status].label }}
                                </Badge>
                                <span class="text-[10px] text-[var(--color-fg-dim)]">
                                    {{ elapsed(item.sent_at) }}
                                </span>
                                <span v-if="item.notes" class="text-[10px] italic text-[var(--color-warn)]">
                                    · {{ item.notes }}
                                </span>
                            </div>
                        </div>
                        <button
                            v-if="item.status === 'ordered'"
                            type="button"
                            class="h-10 px-4 rounded-lg text-xs uppercase tracking-widest font-semibold tap-target bg-[var(--color-warn)] text-[oklch(15%_0.02_60)] hover:opacity-90 active:scale-95 focus-ring"
                            @click="take(item)"
                        >
                            Tomar
                        </button>
                        <button
                            v-else-if="item.status === 'preparing'"
                            type="button"
                            class="h-10 px-4 rounded-lg text-xs uppercase tracking-widest font-semibold tap-target bg-[var(--color-ok)] text-[oklch(15%_0.02_60)] hover:opacity-90 active:scale-95 focus-ring"
                            @click="ready(item)"
                        >
                            Listo
                        </button>
                        <span
                            v-else
                            class="h-10 px-4 flex items-center text-xs uppercase tracking-widest text-[var(--color-fg-dim)]"
                        >
                            ✓ esperando servir
                        </span>
                    </li>
                </ul>
            </article>
        </div>
    </AppLayout>
</template>
