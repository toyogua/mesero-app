<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { useChannel } from '@/composables/useChannel.js';

useChannel('floor.all', '.FloorChanged', () => {
    router.reload({ only: ['areas'], preserveScroll: true });
});

// Refrescar tiempos cada minuto sin recargar todo
const now = ref(Date.now());
let tick = null;
onMounted(() => (tick = setInterval(() => (now.value = Date.now()), 60000)));
onUnmounted(() => clearInterval(tick));

const props = defineProps({
    areas: { type: Array, default: () => [] },
});

const filter = ref('all');
const opening = ref(null);

const summary = computed(() => {
    const tables = props.areas.flatMap((a) => a.tables);
    const occupied = tables.filter((t) => t.occupied).length;
    return {
        occupied,
        free: tables.length - occupied,
        total: tables.length,
        revenue: tables
            .filter((t) => t.check)
            .reduce((s, t) => s + (t.check.subtotal || 0), 0),
    };
});

const filteredAreas = computed(() => {
    if (filter.value === 'all') return props.areas;
    return props.areas.map((a) => ({
        ...a,
        tables: a.tables.filter((t) =>
            filter.value === 'occupied' ? t.occupied : !t.occupied
        ),
    }));
});

function openTable(table) {
    opening.value = table.id;
    router.post(
        `/floor/tables/${table.id}/open`,
        { covers: 1 },
        { onFinish: () => (opening.value = null) }
    );
}

function elapsed(iso) {
    if (!iso) return '';
    const m = Math.floor((now.value - new Date(iso).getTime()) / 60000);
    if (m < 60) return `${m}m`;
    return `${Math.floor(m / 60)}h ${m % 60}m`;
}

function elapsedTone(iso) {
    if (!iso) return 'neutral';
    const m = (now.value - new Date(iso).getTime()) / 60000;
    if (m < 30) return 'ok';
    if (m < 75) return 'warn';
    return 'err';
}

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}
</script>

<template>
    <Head title="Salón — mesero-app" />
    <AppLayout title="Salón">
        <!-- Resumen -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-6">
            <div class="rounded-xl p-4 bg-[var(--color-surface)] border border-[var(--color-border-faint)]">
                <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">Mesas ocupadas</div>
                <div class="text-3xl font-numeric font-semibold mt-1">
                    {{ summary.occupied }}<span class="text-[var(--color-fg-dim)] text-base">/{{ summary.total }}</span>
                </div>
            </div>
            <div class="rounded-xl p-4 bg-[var(--color-surface)] border border-[var(--color-border-faint)]">
                <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">Libres</div>
                <div class="text-3xl font-numeric font-semibold mt-1 text-[var(--color-ok)]">{{ summary.free }}</div>
            </div>
            <div class="rounded-xl p-4 bg-[var(--color-surface)] border border-[var(--color-border-faint)]">
                <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">Áreas</div>
                <div class="text-3xl font-numeric font-semibold mt-1">{{ areas.length }}</div>
            </div>
            <div class="rounded-xl p-4 liquid-glass">
                <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">En curso</div>
                <div class="text-3xl font-numeric font-semibold mt-1 text-[var(--color-primary)]">
                    {{ currency(summary.revenue) }}
                </div>
            </div>
        </section>

        <!-- Filtros -->
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
            <button
                v-for="opt in [
                    { id: 'all', label: 'Todas' },
                    { id: 'occupied', label: 'Ocupadas' },
                    { id: 'free', label: 'Libres' },
                ]"
                :key="opt.id"
                type="button"
                class="h-9 px-4 rounded-full text-xs font-medium uppercase tracking-widest transition border focus-ring whitespace-nowrap"
                :class="
                    filter === opt.id
                        ? 'bg-[var(--color-fg)] text-[var(--color-bg)] border-transparent'
                        : 'text-[var(--color-fg-muted)] border-[var(--color-border-faint)] hover:border-[var(--color-border)]'
                "
                @click="filter = opt.id"
            >
                {{ opt.label }}
            </button>
        </div>

        <!-- Áreas -->
        <div class="flex flex-col gap-10">
            <section v-for="area in filteredAreas" :key="area.id">
                <header class="flex items-end justify-between mb-4">
                    <h2 class="text-lg font-semibold tracking-tight">{{ area.name }}</h2>
                    <span class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">
                        {{ area.tables.filter((t) => t.occupied).length }}/{{ area.tables.length }} ocupadas
                    </span>
                </header>

                <div
                    v-if="area.tables.length"
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3 lg:gap-4"
                >
                    <!-- Mesa ocupada → enlace a comanda -->
                    <Link
                        v-for="t in area.tables.filter((t) => t.occupied)"
                        :key="t.id"
                        :href="`/checks/${t.check.id}`"
                        class="group relative rounded-2xl p-4 lg:p-5 border tap-target transition-all duration-200 hover:-translate-y-0.5 active:scale-[0.98] focus-ring border-[color-mix(in_oklch,var(--color-primary)_30%,transparent)] bg-[color-mix(in_oklch,var(--color-primary)_8%,var(--color-surface))]"
                    >
                        <div class="absolute top-3 right-3 w-2 h-2 rounded-full bg-[var(--color-primary)]" />
                        <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)] mb-2">
                            {{ t.capacity }}p
                        </div>
                        <div class="text-2xl font-semibold tracking-tight mb-3">{{ t.name }}</div>
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Badge :tone="elapsedTone(t.check.opened_at)" size="sm">
                                    {{ elapsed(t.check.opened_at) }}
                                </Badge>
                                <span class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                                    {{ t.check.covers }} pers
                                </span>
                            </div>
                            <div class="font-numeric text-sm">{{ currency(t.check.subtotal) }}</div>
                            <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                                {{ t.check.number }}
                            </div>
                        </div>
                    </Link>

                    <!-- Mesa libre → abrir comanda -->
                    <button
                        v-for="t in area.tables.filter((t) => !t.occupied)"
                        :key="t.id"
                        type="button"
                        :disabled="opening === t.id"
                        class="group relative text-left rounded-2xl p-4 lg:p-5 border tap-target transition-all duration-200 hover:-translate-y-0.5 active:scale-[0.98] focus-ring border-[var(--color-border-faint)] bg-[var(--color-surface)] hover:bg-[var(--color-surface-up)] disabled:opacity-50"
                        @click="openTable(t)"
                    >
                        <div class="absolute top-3 right-3 w-2 h-2 rounded-full bg-[var(--color-fg-dim)]" />
                        <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)] mb-2">
                            {{ t.capacity }}p
                        </div>
                        <div class="text-2xl font-semibold tracking-tight mb-3">{{ t.name }}</div>
                        <div class="text-xs text-[var(--color-fg-muted)]">
                            {{ opening === t.id ? 'Abriendo…' : 'Tocá para abrir' }}
                        </div>
                    </button>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-[var(--color-border-faint)] py-10 text-center text-sm text-[var(--color-fg-dim)]"
                >
                    Sin mesas en este filtro
                </div>
            </section>
        </div>
    </AppLayout>
</template>
