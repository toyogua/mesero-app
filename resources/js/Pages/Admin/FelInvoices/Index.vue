<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    invoices: { type: Object, required: true },   // paginated
    fel_enabled: { type: Boolean, default: false },
});

const STATUS_TONE = {
    pending:   'neutral',
    issued:    'ok',
    failed:    'err',
    cancelled: 'neutral',
};

const STATUS_LABEL = {
    pending:   'Pendiente',
    issued:    'Emitida',
    failed:    'Fallida',
    cancelled: 'Anulada',
};

function stats() {
    const data = props.invoices.data ?? [];
    return {
        issued:  data.filter((i) => i.status === 'issued').length,
        pending: data.filter((i) => i.status === 'pending').length,
        failed:  data.filter((i) => i.status === 'failed').length,
    };
}

function retry(inv) {
    router.post(`/admin/fel-invoices/${inv.id}/retry`);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' });
}

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}
</script>

<template>
    <Head title="Facturas FEL — Admin" />
    <AppLayout title="Facturas FEL (SAT)">
        <!-- FEL disabled banner -->
        <div
            v-if="!fel_enabled"
            class="mb-6 rounded-xl border border-[var(--color-warn)]/40 bg-[var(--color-warn)]/10 px-5 py-4 text-sm text-[var(--color-warn)]"
        >
            <strong>FEL desactivado.</strong>
            Configurá <code>FEL_ENABLED=true</code> y las credenciales en tu <code>.env</code> para activar la facturación electrónica.
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div
                v-for="({ label, count, tone }) in [
                    { label: 'Emitidas', count: stats().issued, tone: 'ok' },
                    { label: 'Pendientes', count: stats().pending, tone: 'neutral' },
                    { label: 'Fallidas', count: stats().failed, tone: 'err' },
                ]"
                :key="label"
                class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] px-5 py-4"
            >
                <div class="text-2xl font-semibold font-numeric">{{ count }}</div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-1">{{ label }}</div>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                    <tr>
                        <th class="table-th">Comanda</th>
                        <th class="table-th">Total</th>
                        <th class="table-th">Estado</th>
                        <th class="table-th">UUID / Error</th>
                        <th class="table-th">Serie · N°</th>
                        <th class="table-th">Receptor</th>
                        <th class="table-th">Emitida</th>
                        <th class="table-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border-faint)]">
                    <tr v-if="!invoices.data?.length">
                        <td colspan="8" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">Sin facturas todavía.</td>
                    </tr>
                    <tr
                        v-for="inv in invoices.data"
                        :key="inv.id"
                        class="hover:bg-[var(--color-surface)]/50"
                    >
                        <td class="table-td font-medium">{{ inv.check_number }}</td>
                        <td class="table-td font-numeric">{{ currency(inv.check_total) }}</td>
                        <td class="table-td">
                            <Badge :tone="STATUS_TONE[inv.status]" size="sm">{{ STATUS_LABEL[inv.status] }}</Badge>
                        </td>
                        <td class="table-td max-w-xs truncate">
                            <span v-if="inv.uuid" class="font-mono text-xs">{{ inv.uuid }}</span>
                            <span v-else-if="inv.error_message" class="text-[var(--color-err)] text-xs">{{ inv.error_message }}</span>
                            <span v-else class="text-[var(--color-fg-dim)]">—</span>
                        </td>
                        <td class="table-td text-[var(--color-fg-muted)]">
                            <span v-if="inv.serie">{{ inv.serie }} · {{ inv.numero }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="table-td text-[var(--color-fg-muted)]">{{ inv.receptor_nit }}</td>
                        <td class="table-td text-[var(--color-fg-muted)]">{{ formatDate(inv.issued_at) }}</td>
                        <td class="table-td text-right">
                            <Button
                                v-if="inv.status === 'failed' || inv.status === 'pending'"
                                variant="ghost"
                                size="sm"
                                @click="retry(inv)"
                            >
                                Reintentar
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="invoices.last_page > 1" class="mt-4 flex justify-center gap-2">
            <Button
                v-if="invoices.prev_page_url"
                variant="ghost"
                size="sm"
                @click="router.get(invoices.prev_page_url)"
            >← Anterior</Button>
            <span class="text-sm text-[var(--color-fg-muted)] self-center">
                Página {{ invoices.current_page }} / {{ invoices.last_page }}
            </span>
            <Button
                v-if="invoices.next_page_url"
                variant="ghost"
                size="sm"
                @click="router.get(invoices.next_page_url)"
            >Siguiente →</Button>
        </div>
    </AppLayout>
</template>

<style scoped>
.table-th {
    @apply text-left px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs;
}
.table-td {
    @apply px-4 py-3;
}
</style>
