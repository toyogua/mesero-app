<script setup>
import { ref, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    checks:  { type: Object, required: true },
    waiters: { type: Array,  required: true },
    filters: { type: Object, required: true },
});

const fromDate  = ref(props.filters.from);
const toDate    = ref(props.filters.to);
const waiterId  = ref(props.filters.waiter_id ?? '');
const search    = ref(props.filters.search ?? '');

function apply() {
    router.get('/admin/checks', {
        from:      fromDate.value,
        to:        toDate.value,
        waiter_id: waiterId.value || undefined,
        search:    search.value  || undefined,
    }, { preserveScroll: true });
}

function setPreset(days) {
    const to   = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days + 1);
    fromDate.value = from.toISOString().slice(0, 10);
    toDate.value   = to.toISOString().slice(0, 10);
    apply();
}

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-GT', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function duration(openedIso, closedIso) {
    if (!openedIso || !closedIso) return '—';
    const mins = Math.round((new Date(closedIso) - new Date(openedIso)) / 60000);
    const h    = Math.floor(mins / 60);
    const m    = mins % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

const FEL_LABEL = {
    pending:    { text: 'Pendiente',  cls: 'text-[var(--color-fg-muted)]' },
    authorized: { text: 'Autorizada', cls: 'text-green-600' },
    failed:     { text: 'Error',      cls: 'text-red-500' },
    cancelled:  { text: 'Anulada',    cls: 'text-[var(--color-fg-muted)] line-through' },
};

function felBadge(status) {
    return FEL_LABEL[status] ?? { text: '—', cls: 'text-[var(--color-fg-dim)]' };
}
</script>

<template>
    <Head title="Historial de comandas — Admin" />
    <AppLayout title="Historial de comandas">

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6 items-end">
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
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Mesero</label>
                <select v-model="waiterId"
                    class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm min-w-32">
                    <option value="">Todos</option>
                    <option v-for="w in waiters" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">N° comanda</label>
                <input v-model="search" type="search" placeholder="C-000001"
                    class="rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 py-2 text-sm w-36"
                    @keydown.enter="apply" />
            </div>
            <div class="flex items-end gap-2">
                <Button size="sm" @click="apply">Filtrar</Button>
                <Button variant="ghost" size="sm" @click="setPreset(1)">Hoy</Button>
                <Button variant="ghost" size="sm" @click="setPreset(7)">7 días</Button>
                <Button variant="ghost" size="sm" @click="setPreset(30)">30 días</Button>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide text-[var(--color-fg-muted)]">
                    <tr>
                        <th class="px-4 py-3 text-left">N°</th>
                        <th class="px-4 py-3 text-left">Mesa</th>
                        <th class="px-4 py-3 text-left">Mesero</th>
                        <th class="px-4 py-3 text-right">Platos</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Duración</th>
                        <th class="px-4 py-3 text-left">Cerrada</th>
                        <th class="px-4 py-3 text-left">FEL</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border-faint)]">
                    <tr v-if="!checks.data.length">
                        <td colspan="9" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">
                            Sin comandas cerradas en el período seleccionado.
                        </td>
                    </tr>
                    <tr v-for="c in checks.data" :key="c.id"
                        class="hover:bg-[var(--color-surface)]/50 transition-colors">
                        <td class="px-4 py-3 font-mono text-xs">{{ c.number }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ c.table?.name ?? '—' }}</span>
                            <span v-if="c.table?.area" class="text-[var(--color-fg-muted)] text-xs ml-1">
                                {{ c.table.area }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ c.waiter?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-numeric">{{ c.items_count }}</td>
                        <td class="px-4 py-3 text-right font-numeric font-semibold">{{ currency(c.total) }}</td>
                        <td class="px-4 py-3 text-right font-numeric text-[var(--color-fg-muted)]">
                            {{ duration(c.opened_at, c.closed_at) }}
                        </td>
                        <td class="px-4 py-3 text-xs text-[var(--color-fg-muted)]">{{ fmt(c.closed_at) }}</td>
                        <td class="px-4 py-3">
                            <span :class="felBadge(c.fel_status).cls" class="text-xs">
                                {{ felBadge(c.fel_status).text }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/checks/${c.id}`"
                                class="text-xs text-[var(--color-primary)] hover:underline">
                                Ver
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="checks.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-[var(--color-fg-muted)]">
                {{ checks.from }}–{{ checks.to }} de {{ checks.total }} comandas
            </span>
            <div class="flex gap-1">
                <Link
                    v-for="link in checks.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1 rounded-lg border border-[var(--color-border-faint)]',
                        link.active
                            ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]'
                            : 'bg-[var(--color-surface)] hover:bg-[var(--color-surface-hover)]',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>

    </AppLayout>
</template>
