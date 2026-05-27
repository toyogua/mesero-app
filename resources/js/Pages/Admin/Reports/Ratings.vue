<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    from:      { type: String, required: true },
    to:        { type: String, required: true },
    summary:   { type: Object, required: true },
    recent:    { type: Array,  required: true },
    by_waiter: { type: Array,  required: true },
    daily:     { type: Array,  required: true },
});

const fromDate = ref(props.from);
const toDate   = ref(props.to);

function applyRange() {
    router.get('/admin/reports/ratings', { from: fromDate.value, to: toDate.value }, { preserveScroll: true });
}

function setPreset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days + 1);
    fromDate.value = from.toISOString().slice(0, 10);
    toDate.value   = to.toISOString().slice(0, 10);
    applyRange();
}

function stars(n) {
    return '⭐'.repeat(n) + '☆'.repeat(5 - n);
}

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' });
}

const pct = computed(() => {
    const t = props.summary.total_ratings;
    if (!t) return { 5: 0, 4: 0, 3: 0, low: 0 };
    return {
        5:   Math.round((props.summary.five_stars  / t) * 100),
        4:   Math.round((props.summary.four_stars  / t) * 100),
        3:   Math.round((props.summary.three_stars / t) * 100),
        low: Math.round((props.summary.low_stars   / t) * 100),
    };
});

function starsColor(avg) {
    if (avg >= 4.5) return 'text-emerald-400';
    if (avg >= 3.5) return 'text-amber-400';
    return 'text-red-400';
}
</script>

<template>
    <Head title="Calificaciones — Admin" />
    <AppLayout title="Calificaciones">

        <!-- Filtro -->
        <div class="flex flex-wrap gap-2 mb-6 items-end">
            <div class="flex gap-2">
                <div>
                    <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Desde</label>
                    <input v-model="fromDate" type="date" class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Hasta</label>
                    <input v-model="toDate" type="date" class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm" />
                </div>
                <div class="flex items-end">
                    <Button size="sm" @click="applyRange">Aplicar</Button>
                </div>
            </div>
            <div class="flex gap-2 items-end">
                <Button variant="ghost" size="sm" @click="setPreset(1)">Hoy</Button>
                <Button variant="ghost" size="sm" @click="setPreset(7)">7 días</Button>
                <Button variant="ghost" size="sm" @click="setPreset(30)">30 días</Button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">Calificaciones</div>
                <div class="font-numeric text-3xl font-semibold">{{ summary.total_ratings }}</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">Promedio</div>
                <div class="font-numeric text-3xl font-semibold" :class="starsColor(summary.avg_stars)">
                    {{ summary.avg_stars ? summary.avg_stars.toFixed(1) : '—' }}
                    <span class="text-lg">/ 5</span>
                </div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">5 estrellas</div>
                <div class="font-numeric text-3xl font-semibold text-emerald-400">{{ summary.five_stars }}</div>
                <div class="text-xs text-[var(--color-fg-dim)]">{{ pct[5] }}%</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">1-2 estrellas</div>
                <div class="font-numeric text-3xl font-semibold text-red-400">{{ summary.low_stars }}</div>
                <div class="text-xs text-[var(--color-fg-dim)]">{{ pct.low }}%</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- Distribución -->
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-6">
                <h2 class="text-sm font-semibold tracking-tight mb-4">Distribución</h2>
                <div class="space-y-3">
                    <template v-for="n in [5, 4, 3, 2, 1]" :key="n">
                        <div class="flex items-center gap-3">
                            <span class="text-xs w-2 shrink-0 text-[var(--color-fg-dim)]">{{ n }}</span>
                            <div class="flex-1 h-2 rounded-full bg-[var(--color-surface-down)] overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="n >= 4 ? 'bg-emerald-400' : n === 3 ? 'bg-amber-400' : 'bg-red-400'"
                                    :style="`width: ${n === 5 ? pct[5] : n === 4 ? pct[4] : n === 3 ? pct[3] : (pct.low / 2)}%`"
                                />
                            </div>
                            <span class="text-xs w-8 text-right text-[var(--color-fg-dim)] shrink-0">
                                {{ n === 5 ? pct[5] : n === 4 ? pct[4] : n === 3 ? pct[3] : '' }}%
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Por mesero -->
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[var(--color-border-faint)]">
                    <h2 class="text-sm font-semibold tracking-tight">Por mesero</h2>
                </div>
                <div v-if="by_waiter.length" class="divide-y divide-[var(--color-border-faint)]">
                    <div v-for="w in by_waiter" :key="w.waiter_name" class="flex items-center gap-4 px-6 py-3">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ w.waiter_name }}</div>
                            <div class="text-xs text-[var(--color-fg-dim)]">{{ w.total_ratings }} calificaciones</div>
                        </div>
                        <div class="font-numeric text-sm font-semibold shrink-0" :class="starsColor(w.avg_stars)">
                            {{ Number(w.avg_stars).toFixed(1) }} ⭐
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center text-sm text-[var(--color-fg-dim)]">Sin datos</div>
            </div>

            <!-- Diario -->
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[var(--color-border-faint)]">
                    <h2 class="text-sm font-semibold tracking-tight">Diario</h2>
                </div>
                <div v-if="daily.length" class="divide-y divide-[var(--color-border-faint)] max-h-64 overflow-y-auto">
                    <div v-for="d in daily" :key="d.day" class="flex items-center gap-4 px-6 py-2.5">
                        <span class="text-xs text-[var(--color-fg-muted)] flex-1">{{ d.day }}</span>
                        <span class="font-numeric text-xs">{{ d.total }}</span>
                        <span class="font-numeric text-xs font-semibold" :class="starsColor(d.avg_stars)">
                            {{ Number(d.avg_stars).toFixed(1) }} ⭐
                        </span>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center text-sm text-[var(--color-fg-dim)]">Sin datos</div>
            </div>
        </div>

        <!-- Comentarios recientes -->
        <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--color-border-faint)]">
                <h2 class="text-sm font-semibold tracking-tight">Calificaciones recientes</h2>
            </div>
            <div v-if="recent.length" class="divide-y divide-[var(--color-border-faint)]">
                <div v-for="r in recent" :key="r.id" class="px-6 py-4 flex items-start gap-4">
                    <div class="shrink-0 text-lg leading-none mt-0.5">{{ '⭐'.repeat(r.stars) }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium">{{ r.check_number }}</span>
                            <span v-if="r.order_type === 'takeout'" class="text-[10px] uppercase tracking-widest px-1.5 py-0.5 rounded-full bg-amber-400/15 text-amber-400">Para llevar</span>
                            <span class="text-xs text-[var(--color-fg-dim)]">· {{ r.waiter_name }}</span>
                        </div>
                        <p v-if="r.comment" class="text-sm text-[var(--color-fg-muted)] mt-1 italic">
                            "{{ r.comment }}"
                        </p>
                    </div>
                    <span class="text-xs text-[var(--color-fg-dim)] shrink-0">{{ fmt(r.rated_at) }}</span>
                </div>
            </div>
            <div v-else class="px-6 py-10 text-center text-sm text-[var(--color-fg-dim)]">
                Sin calificaciones en este período
            </div>
        </div>

    </AppLayout>
</template>
