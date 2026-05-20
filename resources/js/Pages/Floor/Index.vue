<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    areas: { type: Array, default: () => [] },
});

const filter = ref('all');

const summary = computed(() => {
    const tables = props.areas.flatMap((a) => a.tables);
    const occupied = tables.filter((t) => t.occupied).length;
    const total = tables.length;
    return {
        occupied,
        free: total - occupied,
        total,
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

function elapsed(iso) {
    if (!iso) return '';
    const ms = Date.now() - new Date(iso).getTime();
    const m = Math.floor(ms / 60000);
    if (m < 60) return `${m}m`;
    return `${Math.floor(m / 60)}h ${m % 60}m`;
}

function elapsedTone(iso) {
    if (!iso) return 'neutral';
    const m = (Date.now() - new Date(iso).getTime()) / 60000;
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
        <template #actions>
            <Button as="button" variant="primary" size="md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Nueva cuenta
            </Button>
        </template>

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
                    <Link
                        v-for="t in area.tables"
                        :key="t.id"
                        :href="t.occupied ? `/checks/${t.check.id}` : `/floor/tables/${t.id}/open`"
                        class="group block relative rounded-2xl p-4 lg:p-5 border tap-target transition-all duration-200 hover:-translate-y-0.5 active:scale-[0.98] focus-ring"
                        :class="
                            t.occupied
                                ? 'border-[color-mix(in_oklch,var(--color-primary)_30%,transparent)] bg-[color-mix(in_oklch,var(--color-primary)_8%,var(--color-surface))]'
                                : 'border-[var(--color-border-faint)] bg-[var(--color-surface)] hover:bg-[var(--color-surface-up)]'
                        "
                    >
                        <!-- Estado dot -->
                        <div
                            class="absolute top-3 right-3 w-2 h-2 rounded-full"
                            :class="t.occupied ? 'bg-[var(--color-primary)]' : 'bg-[var(--color-fg-dim)]'"
                        />

                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">
                                {{ t.capacity }}p
                            </span>
                        </div>

                        <div class="text-2xl font-semibold tracking-tight mb-3">{{ t.name }}</div>

                        <div v-if="t.occupied" class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <Badge :tone="elapsedTone(t.check.opened_at)" size="sm">
                                    {{ elapsed(t.check.opened_at) }}
                                </Badge>
                                <span class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                                    {{ t.check.covers }} pers
                                </span>
                            </div>
                            <div class="font-numeric text-sm text-[var(--color-fg)]">
                                {{ currency(t.check.subtotal) }}
                            </div>
                            <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                                {{ t.check.number }}
                            </div>
                        </div>

                        <div v-else class="text-xs text-[var(--color-fg-muted)]">
                            Libre · tocá para abrir
                        </div>
                    </Link>
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
