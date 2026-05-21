<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    from:           { type: String, required: true },
    to:             { type: String, required: true },
    summary:        { type: Object, required: true },
    top_items:      { type: Array,  required: true },
    daily:          { type: Array,  required: true },
    food_cost:      { type: Number, required: true },
    table_rotation: { type: Object, required: true },
});

const fromDate = ref(props.from);
const toDate   = ref(props.to);

function applyRange() {
    router.get('/admin/reports', { from: fromDate.value, to: toDate.value }, { preserveScroll: true });
}

function setPreset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days + 1);
    fromDate.value = from.toISOString().slice(0, 10);
    toDate.value   = to.toISOString().slice(0, 10);
    applyRange();
}

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}

function minutes(m) {
    if (!m) return '—';
    const h = Math.floor(m / 60);
    const min = Math.round(m % 60);
    return h > 0 ? `${h}h ${min}m` : `${min}m`;
}

const foodCostPct = computed(() => {
    const rev = props.summary.total_subtotal;
    return rev > 0 ? ((props.food_cost / rev) * 100).toFixed(1) : '0.0';
});
</script>

<template>
    <Head title="Reportes — Admin" />
    <AppLayout title="Reportes">

        <!-- Date range filter -->
        <div class="flex flex-wrap gap-2 mb-6 items-end">
            <div class="flex gap-2">
                <div>
                    <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Desde</label>
                    <input
                        v-model="fromDate"
                        type="date"
                        class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Hasta</label>
                    <input
                        v-model="toDate"
                        type="date"
                        class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm"
                    />
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

        <!-- Summary cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div
                v-for="card in [
                    { label: 'Comandas', value: summary.total_checks, fmt: 'num' },
                    { label: 'Subtotal', value: summary.total_subtotal, fmt: 'cur' },
                    { label: 'IVA',      value: summary.total_tax,      fmt: 'cur' },
                    { label: 'Propinas', value: summary.total_tip,      fmt: 'cur' },
                    { label: 'Total',    value: summary.total_revenue,  fmt: 'cur' },
                    { label: 'Ticket promedio', value: summary.avg_ticket, fmt: 'cur' },
                ]"
                :key="card.label"
                class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] px-4 py-4"
            >
                <div class="text-2xl font-semibold font-numeric">
                    {{ card.fmt === 'cur' ? currency(card.value) : card.value }}
                </div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">{{ card.label }}</div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <!-- Top items -->
            <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--color-border-faint)] font-medium text-sm">Top productos</div>
                <table class="w-full text-sm">
                    <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide text-[var(--color-fg-muted)]">
                        <tr>
                            <th class="px-4 py-2 text-left">Producto</th>
                            <th class="px-4 py-2 text-right">Cant.</th>
                            <th class="px-4 py-2 text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border-faint)]">
                        <tr v-if="!top_items.length">
                            <td colspan="3" class="px-4 py-6 text-center text-[var(--color-fg-dim)]">Sin datos</td>
                        </tr>
                        <tr v-for="item in top_items" :key="item.name_snapshot" class="hover:bg-[var(--color-surface)]/50">
                            <td class="px-4 py-2">{{ item.name_snapshot }}</td>
                            <td class="px-4 py-2 text-right font-numeric">{{ item.total_qty }}</td>
                            <td class="px-4 py-2 text-right font-numeric">{{ currency(item.total_revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Daily breakdown -->
            <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                <div class="px-4 py-3 border-b border-[var(--color-border-faint)] font-medium text-sm">Ventas por día</div>
                <table class="w-full text-sm">
                    <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide text-[var(--color-fg-muted)]">
                        <tr>
                            <th class="px-4 py-2 text-left">Día</th>
                            <th class="px-4 py-2 text-right">Comandas</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border-faint)]">
                        <tr v-if="!daily.length">
                            <td colspan="3" class="px-4 py-6 text-center text-[var(--color-fg-dim)]">Sin datos</td>
                        </tr>
                        <tr v-for="row in daily" :key="row.day" class="hover:bg-[var(--color-surface)]/50">
                            <td class="px-4 py-2">{{ row.day }}</td>
                            <td class="px-4 py-2 text-right font-numeric">{{ row.checks }}</td>
                            <td class="px-4 py-2 text-right font-numeric">{{ currency(row.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Food cost + table rotation -->
        <div class="grid md:grid-cols-2 gap-6">
            <div class="rounded-2xl border border-[var(--color-border-faint)] px-5 py-5">
                <div class="text-sm font-medium mb-3">Costo de alimentos estimado</div>
                <div class="text-3xl font-semibold font-numeric">{{ currency(food_cost) }}</div>
                <div class="text-sm text-[var(--color-fg-muted)] mt-1">
                    Food cost %: <span class="font-numeric font-semibold">{{ foodCostPct }}%</span>
                    del subtotal
                </div>
                <p class="text-xs text-[var(--color-fg-dim)] mt-2">
                    Solo incluye platillos con receta y costo de ingrediente configurado.
                </p>
            </div>

            <div class="rounded-2xl border border-[var(--color-border-faint)] px-5 py-5">
                <div class="text-sm font-medium mb-3">Rotación de mesas</div>
                <div class="text-3xl font-semibold font-numeric">{{ minutes(table_rotation.avg_minutes) }}</div>
                <div class="text-sm text-[var(--color-fg-muted)] mt-1">Tiempo promedio por comanda</div>
                <div class="text-xs text-[var(--color-fg-dim)] mt-2">
                    Basado en {{ table_rotation.total_checks }} comandas cerradas en el período.
                </div>
            </div>
        </div>

    </AppLayout>
</template>
