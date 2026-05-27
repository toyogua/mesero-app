<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    from:              { type: String, required: true },
    to:                { type: String, required: true },
    summary:           { type: Object, required: true },
    top_items:         { type: Array,  required: true },
    daily:             { type: Array,  required: true },
    top_customers:     { type: Array,  required: true },
    avg_prep_minutes:  { type: Number, required: true },
});

const fromDate = ref(props.from);
const toDate   = ref(props.to);

function applyRange() {
    router.get('/admin/reports/takeout', { from: fromDate.value, to: toDate.value }, { preserveScroll: true });
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
    const h   = Math.floor(m / 60);
    const min = Math.round(m % 60);
    return h > 0 ? `${h}h ${min}m` : `${min}m`;
}
</script>

<template>
    <Head title="Reporte Para llevar — Admin" />
    <AppLayout title="Para llevar — Reporte">

        <!-- Filtro de fechas -->
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

        <!-- KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">Órdenes</div>
                <div class="font-numeric text-3xl font-semibold">{{ summary.total_checks }}</div>
                <div class="mt-2 flex items-center gap-3 text-xs text-[var(--color-fg-dim)]">
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-[var(--color-primary)]"></span>
                        Web: <span class="font-numeric font-semibold text-[var(--color-fg)]">{{ summary.web_orders }}</span>
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-[var(--color-fg-dim)]"></span>
                        POS: <span class="font-numeric font-semibold text-[var(--color-fg)]">{{ summary.pos_orders }}</span>
                    </span>
                </div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">Ingresos</div>
                <div class="font-numeric text-3xl font-semibold">{{ currency(summary.total_revenue) }}</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">Ticket promedio</div>
                <div class="font-numeric text-3xl font-semibold">{{ currency(summary.avg_ticket) }}</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">Propinas</div>
                <div class="font-numeric text-3xl font-semibold">{{ currency(summary.total_tip) }}</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] p-5">
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">T. promedio</div>
                <div class="font-numeric text-3xl font-semibold">{{ minutes(avg_prep_minutes) }}</div>
                <div class="text-xs text-[var(--color-fg-dim)] mt-0.5">desde apertura</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Clientes frecuentes -->
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[var(--color-border-faint)]">
                    <h2 class="text-sm font-semibold tracking-tight">Clientes frecuentes</h2>
                </div>
                <div v-if="top_customers.length" class="divide-y divide-[var(--color-border-faint)]">
                    <div
                        v-for="(c, i) in top_customers"
                        :key="c.customer_phone"
                        class="flex items-center gap-4 px-6 py-3"
                    >
                        <span class="font-numeric text-xs text-[var(--color-fg-dim)] w-5 shrink-0">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ c.customer_name }}</div>
                            <div class="text-xs text-[var(--color-fg-dim)]">{{ c.customer_phone }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-numeric text-sm font-semibold">{{ currency(c.total_spent) }}</div>
                            <div class="text-xs text-[var(--color-fg-dim)]">{{ c.total_orders }} orden{{ c.total_orders !== 1 ? 'es' : '' }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center text-sm text-[var(--color-fg-dim)]">
                    Sin datos en este período
                </div>
            </div>

            <!-- Ítems más pedidos -->
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
                <div class="px-6 py-4 border-b border-[var(--color-border-faint)]">
                    <h2 class="text-sm font-semibold tracking-tight">Ítems más pedidos</h2>
                </div>
                <div v-if="top_items.length" class="divide-y divide-[var(--color-border-faint)]">
                    <div
                        v-for="(item, i) in top_items"
                        :key="item.name_snapshot"
                        class="flex items-center gap-4 px-6 py-3"
                    >
                        <span class="font-numeric text-xs text-[var(--color-fg-dim)] w-5 shrink-0">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0 text-sm truncate">{{ item.name_snapshot }}</div>
                        <div class="text-right shrink-0">
                            <div class="font-numeric text-sm font-semibold">{{ currency(item.total_revenue) }}</div>
                            <div class="text-xs text-[var(--color-fg-dim)]">× {{ item.total_qty }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center text-sm text-[var(--color-fg-dim)]">
                    Sin datos en este período
                </div>
            </div>
        </div>

        <!-- Ventas diarias -->
        <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--color-border-faint)]">
                <h2 class="text-sm font-semibold tracking-tight">Ventas diarias</h2>
            </div>
            <div v-if="daily.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[var(--color-border-faint)]">
                            <th class="px-6 py-3 text-left text-xs uppercase tracking-widest text-[var(--color-fg-dim)] font-medium">Fecha</th>
                            <th class="px-6 py-3 text-right text-xs uppercase tracking-widest text-[var(--color-fg-dim)] font-medium">Órdenes</th>
                            <th class="px-6 py-3 text-right text-xs uppercase tracking-widest text-[var(--color-primary)] font-medium">Web</th>
                            <th class="px-6 py-3 text-right text-xs uppercase tracking-widest text-[var(--color-fg-dim)] font-medium">POS</th>
                            <th class="px-6 py-3 text-right text-xs uppercase tracking-widest text-[var(--color-fg-dim)] font-medium">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border-faint)]">
                        <tr v-for="row in daily" :key="row.day">
                            <td class="px-6 py-3 text-[var(--color-fg-muted)]">{{ row.day }}</td>
                            <td class="px-6 py-3 text-right font-numeric">{{ row.checks }}</td>
                            <td class="px-6 py-3 text-right font-numeric text-[var(--color-primary)]">{{ row.web_checks }}</td>
                            <td class="px-6 py-3 text-right font-numeric text-[var(--color-fg-dim)]">{{ row.pos_checks }}</td>
                            <td class="px-6 py-3 text-right font-numeric font-semibold">{{ currency(row.revenue) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-[var(--color-border)]">
                        <tr>
                            <td class="px-6 py-3 text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">Total</td>
                            <td class="px-6 py-3 text-right font-numeric font-semibold">{{ summary.total_checks }}</td>
                            <td class="px-6 py-3 text-right font-numeric font-semibold text-[var(--color-primary)]">{{ summary.web_orders }}</td>
                            <td class="px-6 py-3 text-right font-numeric font-semibold text-[var(--color-fg-dim)]">{{ summary.pos_orders }}</td>
                            <td class="px-6 py-3 text-right font-numeric font-semibold">{{ currency(summary.total_revenue) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div v-else class="px-6 py-10 text-center text-sm text-[var(--color-fg-dim)]">
                Sin datos en este período
            </div>
        </div>

    </AppLayout>
</template>
