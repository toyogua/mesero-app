<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    from:             { type: String,  required: true },
    to:               { type: String,  required: true },
    waiters:          { type: Array,   required: true },
    daily:            { type: Array,   default: () => [] },
    selected_waiter:  { type: Object,  default: null },
});

const fromDate       = ref(props.from);
const toDate         = ref(props.to);
const selectedId     = ref(props.selected_waiter?.id ?? null);

function applyRange() {
    router.get('/admin/reports/waiters', {
        from:       fromDate.value,
        to:         toDate.value,
        waiter_id:  selectedId.value || undefined,
    }, { preserveScroll: true });
}

function setPreset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days + 1);
    fromDate.value = from.toISOString().slice(0, 10);
    toDate.value   = to.toISOString().slice(0, 10);
    applyRange();
}

function selectWaiter(id) {
    selectedId.value = selectedId.value === id ? null : id;
    router.get('/admin/reports/waiters', {
        from:      fromDate.value,
        to:        toDate.value,
        waiter_id: selectedId.value || undefined,
    }, { preserveScroll: true });
}

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}

function minutes(m) {
    if (!m) return '—';
    const min = Math.round(m);
    if (min < 60) return `${min}m`;
    return `${Math.floor(min / 60)}h ${min % 60}m`;
}

function pct(v) {
    return `${Number(v || 0).toFixed(1)}%`;
}

const topWaiter = computed(() =>
    props.waiters.reduce((best, w) =>
        w.total_revenue > (best?.total_revenue ?? -1) ? w : best, null)
);
</script>

<template>
    <Head title="Reporte Meseros — Admin" />
    <AppLayout title="Reporte Meseros">

        <!-- Filtros -->
        <div class="flex flex-wrap gap-2 mb-6 items-end">
            <div class="flex gap-2">
                <div>
                    <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Desde</label>
                    <input v-model="fromDate" type="date"
                        class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Hasta</label>
                    <input v-model="toDate" type="date"
                        class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm" />
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

        <!-- Tabla comparativa -->
        <div class="rounded-xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] overflow-hidden mb-8">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[var(--color-border-faint)] text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                            <th class="text-left px-4 py-3 font-medium">Mesero</th>
                            <th class="text-right px-4 py-3 font-medium">Comandas</th>
                            <th class="text-right px-4 py-3 font-medium">Ventas</th>
                            <th class="text-right px-4 py-3 font-medium">Ticket prom.</th>
                            <th class="text-right px-4 py-3 font-medium">Comensales</th>
                            <th class="text-right px-4 py-3 font-medium">Venta/comensal</th>
                            <th class="text-right px-4 py-3 font-medium">Propinas</th>
                            <th class="text-right px-4 py-3 font-medium">T. servicio</th>
                            <th class="text-right px-4 py-3 font-medium">Ítems serv.</th>
                            <th class="text-right px-4 py-3 font-medium">% cancelados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!waiters.length">
                            <td colspan="10" class="text-center py-10 text-[var(--color-fg-dim)]">
                                Sin datos para el período seleccionado
                            </td>
                        </tr>
                        <tr
                            v-for="w in waiters"
                            :key="w.id"
                            class="border-b border-[var(--color-border-faint)] last:border-0 transition cursor-pointer"
                            :class="selected_waiter?.id === w.id
                                ? 'bg-[color-mix(in_oklch,var(--color-primary)_8%,var(--color-surface))]'
                                : 'hover:bg-[var(--color-surface-up)]'"
                            @click="selectWaiter(w.id)"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-[var(--color-surface-up)] flex items-center justify-center text-xs font-medium shrink-0">
                                        {{ w.name.charAt(0) }}
                                    </div>
                                    <span class="font-medium">{{ w.name }}</span>
                                    <Badge v-if="topWaiter?.id === w.id && w.total_revenue > 0" tone="ok" size="sm">top</Badge>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-numeric">{{ w.total_checks }}</td>
                            <td class="px-4 py-3 text-right font-numeric font-medium">{{ currency(w.total_revenue) }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ currency(w.avg_ticket) }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ w.total_covers }}</td>
                            <td class="px-4 py-3 text-right font-numeric">
                                {{ w.total_covers > 0 ? currency(w.total_revenue / w.total_covers) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-numeric">{{ currency(w.total_tips) }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ minutes(w.avg_service_min) }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ w.items_served }}</td>
                            <td class="px-4 py-3 text-right">
                                <Badge
                                    :tone="w.cancel_rate > 10 ? 'err' : w.cancel_rate > 5 ? 'warn' : 'neutral'"
                                    size="sm"
                                >
                                    {{ pct(w.cancel_rate) }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detalle diario del mesero seleccionado -->
        <template v-if="selected_waiter && daily.length">
            <h2 class="text-base font-semibold mb-4">
                Detalle diario — {{ selected_waiter.name }}
            </h2>
            <div class="rounded-xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[var(--color-border-faint)] text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                            <th class="text-left px-4 py-3 font-medium">Día</th>
                            <th class="text-right px-4 py-3 font-medium">Comandas</th>
                            <th class="text-right px-4 py-3 font-medium">Comensales</th>
                            <th class="text-right px-4 py-3 font-medium">Ventas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in daily"
                            :key="d.day"
                            class="border-b border-[var(--color-border-faint)] last:border-0"
                        >
                            <td class="px-4 py-3">{{ d.day }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ d.checks }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ d.covers }}</td>
                            <td class="px-4 py-3 text-right font-numeric font-medium">{{ currency(d.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div v-else-if="selected_waiter && !daily.length" class="text-center py-8 text-sm text-[var(--color-fg-dim)]">
            {{ selected_waiter.name }} no tiene comandas en este período.
        </div>

        <p v-if="!selected_waiter && waiters.length" class="text-xs text-[var(--color-fg-dim)] mt-2">
            Tocá un mesero para ver su detalle diario.
        </p>

    </AppLayout>
</template>
