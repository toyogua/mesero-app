<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    today_stats:  { type: Object, required: true },
    open_checks:  { type: Array,  required: true },
    low_stock:    { type: Array,  required: true },
    fel_failures: { type: Array,  required: true },
    week_revenue: { type: Array,  required: true },
});

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}

function minutesAgo(iso) {
    if (!iso) return '—';
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (diff < 60) return `${diff} min`;
    return `${Math.floor(diff / 60)}h ${diff % 60}min`;
}

function fmtDay(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('es-GT', { weekday: 'short', day: 'numeric' });
}

const maxRevenue = computed(() =>
    Math.max(...props.week_revenue.map((r) => r.revenue), 1),
);
</script>

<template>
    <Head title="Dashboard — Admin" />
    <AppLayout title="Dashboard">

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] px-5 py-4">
                <div class="text-2xl font-semibold font-numeric">{{ currency(today_stats.revenue) }}</div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">Ventas hoy</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] px-5 py-4">
                <div class="text-2xl font-semibold font-numeric">{{ today_stats.checks_count }}</div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">Comandas cerradas</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] px-5 py-4">
                <div class="text-2xl font-semibold font-numeric">{{ currency(today_stats.avg_ticket) }}</div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">Ticket promedio</div>
            </div>
            <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] px-5 py-4">
                <div class="text-2xl font-semibold font-numeric">{{ today_stats.covers }}</div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">Cubiertos hoy</div>
            </div>
            <div
                class="rounded-2xl border px-5 py-4"
                :class="today_stats.open_count > 0
                    ? 'bg-[var(--color-primary)]/10 border-[var(--color-primary)]/30'
                    : 'bg-[var(--color-surface)] border-[var(--color-border-faint)]'"
            >
                <div class="text-2xl font-semibold font-numeric">{{ today_stats.open_count }}</div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">Mesas abiertas</div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">

            <!-- Left: Open checks + Week chart -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Open checks -->
                <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                        <span class="text-sm font-semibold">Mesas abiertas ahora</span>
                        <Link href="/floor" class="text-xs text-[var(--color-primary)] hover:underline">Ver salón →</Link>
                    </div>
                    <div v-if="!open_checks.length" class="px-5 py-8 text-center text-sm text-[var(--color-fg-dim)]">
                        Sin mesas abiertas.
                    </div>
                    <table v-else class="w-full text-sm">
                        <tbody class="divide-y divide-[var(--color-border-faint)]">
                            <tr v-for="c in open_checks" :key="c.id" class="hover:bg-[var(--color-surface)]/50">
                                <td class="px-4 py-2.5 font-medium">
                                    <span class="text-[var(--color-fg-muted)] text-xs mr-1">Mesa</span>{{ c.table }}
                                    <span v-if="c.area" class="text-xs text-[var(--color-fg-dim)] ml-1">({{ c.area }})</span>
                                </td>
                                <td class="px-4 py-2.5 text-[var(--color-fg-muted)] text-xs">{{ c.waiter }}</td>
                                <td class="px-4 py-2.5 text-right font-numeric text-xs text-[var(--color-fg-muted)]">
                                    {{ minutesAgo(c.opened_at) }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-numeric font-medium">
                                    {{ currency(c.total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 7-day revenue chart -->
                <div class="rounded-2xl border border-[var(--color-border-faint)] p-5">
                    <div class="text-sm font-semibold mb-4">Ventas — últimos 7 días</div>
                    <div v-if="!week_revenue.length" class="text-sm text-[var(--color-fg-dim)] text-center py-4">Sin datos.</div>
                    <div v-else class="flex items-end gap-2 h-28">
                        <div
                            v-for="r in week_revenue"
                            :key="r.day"
                            class="flex-1 flex flex-col items-center gap-1"
                        >
                            <div
                                class="w-full rounded-t-md bg-[var(--color-primary)] transition-all"
                                :style="{ height: `${Math.max(4, (r.revenue / maxRevenue) * 96)}px` }"
                                :title="`${fmtDay(r.day)}: ${currency(r.revenue)} (${r.checks_count} cmd)`"
                            />
                            <span class="text-[10px] text-[var(--color-fg-dim)] truncate w-full text-center">{{ fmtDay(r.day) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Alerts -->
            <div class="space-y-6">

                <!-- Low stock -->
                <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                        <span class="text-sm font-semibold">
                            Stock bajo
                            <span v-if="low_stock.length"
                                  class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--color-warn)] text-white text-[10px] font-bold">
                                {{ low_stock.length }}
                            </span>
                        </span>
                        <Link href="/admin/ingredients" class="text-xs text-[var(--color-primary)] hover:underline">Ver →</Link>
                    </div>
                    <div v-if="!low_stock.length" class="px-5 py-8 text-center text-sm text-[var(--color-fg-dim)]">
                        Todo en orden.
                    </div>
                    <ul v-else class="divide-y divide-[var(--color-border-faint)]">
                        <li v-for="ing in low_stock" :key="ing.id" class="px-4 py-2.5 flex items-center justify-between">
                            <span class="text-sm font-medium">{{ ing.name }}</span>
                            <span class="text-xs text-[var(--color-warn)] font-numeric font-medium">
                                {{ ing.quantity_on_hand }} / {{ ing.minimum_stock }} {{ ing.unit }}
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- FEL failures -->
                <div v-if="fel_failures.length" class="rounded-2xl border border-[var(--color-err)]/30 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 bg-[var(--color-err)]/5 border-b border-[var(--color-err)]/20">
                        <span class="text-sm font-semibold text-[var(--color-err)]">
                            FEL fallidas
                            <span class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-[var(--color-err)] text-white text-[10px] font-bold">
                                {{ fel_failures.length }}
                            </span>
                        </span>
                        <Link href="/admin/fel-invoices" class="text-xs text-[var(--color-err)] hover:underline">Ver →</Link>
                    </div>
                    <ul class="divide-y divide-[var(--color-border-faint)]">
                        <li v-for="inv in fel_failures" :key="inv.id" class="px-4 py-2.5">
                            <div class="text-sm font-medium">Comanda #{{ inv.check_number }}</div>
                            <div class="text-xs text-[var(--color-fg-muted)] truncate">{{ inv.error_message }}</div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>

    </AppLayout>
</template>
