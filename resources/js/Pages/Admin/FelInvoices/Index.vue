<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    invoices:    { type: Object,  required: true },
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

// Cancel inline form state: { [id]: { open: bool, reason: string } }
const cancelForms = ref({});

function openCancel(inv) {
    cancelForms.value[inv.id] = { open: true, reason: '' };
}

function closeCancel(id) {
    delete cancelForms.value[id];
}

function submitCancel(inv) {
    const form = cancelForms.value[inv.id];
    if (!form?.reason?.trim()) return;
    router.post(`/admin/fel-invoices/${inv.id}/cancel`, { reason: form.reason }, {
        preserveScroll: true,
        onSuccess: () => closeCancel(inv.id),
    });
}

function retry(inv) {
    router.post(`/admin/fel-invoices/${inv.id}/retry`);
}

function stats() {
    const data = props.invoices.data ?? [];
    return {
        issued:    data.filter((i) => i.status === 'issued').length,
        pending:   data.filter((i) => i.status === 'pending').length,
        failed:    data.filter((i) => i.status === 'failed').length,
        cancelled: data.filter((i) => i.status === 'cancelled').length,
    };
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
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div
                v-for="({ label, count }) in [
                    { label: 'Emitidas',   count: stats().issued },
                    { label: 'Pendientes', count: stats().pending },
                    { label: 'Fallidas',   count: stats().failed },
                    { label: 'Anuladas',   count: stats().cancelled },
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
                        <th class="table-th">UUID / Info</th>
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

                    <template v-for="inv in invoices.data" :key="inv.id">
                        <tr class="hover:bg-[var(--color-surface)]/50">
                            <td class="table-td font-medium">{{ inv.check_number }}</td>
                            <td class="table-td font-numeric">{{ currency(inv.check_total) }}</td>
                            <td class="table-td">
                                <Badge :tone="STATUS_TONE[inv.status]" size="sm">{{ STATUS_LABEL[inv.status] }}</Badge>
                            </td>
                            <td class="table-td max-w-xs truncate">
                                <span v-if="inv.status === 'cancelled' && inv.cancel_reason"
                                      class="text-xs text-[var(--color-fg-muted)] italic">
                                    Anulada: {{ inv.cancel_reason }}
                                </span>
                                <span v-else-if="inv.uuid" class="font-mono text-xs">{{ inv.uuid }}</span>
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
                                <div class="flex gap-2 justify-end">
                                    <Button
                                        v-if="inv.status === 'failed' || inv.status === 'pending'"
                                        variant="ghost"
                                        size="sm"
                                        @click="retry(inv)"
                                    >
                                        Reintentar
                                    </Button>
                                    <Button
                                        v-if="inv.status === 'issued' && !cancelForms[inv.id]"
                                        variant="ghost"
                                        size="sm"
                                        class="text-[var(--color-err)] hover:bg-[var(--color-err)]/10"
                                        @click="openCancel(inv)"
                                    >
                                        Anular
                                    </Button>
                                    <Button
                                        v-if="cancelForms[inv.id]"
                                        variant="ghost"
                                        size="sm"
                                        @click="closeCancel(inv.id)"
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Inline cancel form -->
                        <tr v-if="cancelForms[inv.id]" class="bg-[var(--color-err)]/5">
                            <td colspan="8" class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-medium text-[var(--color-err)] shrink-0">Motivo de anulación:</span>
                                    <input
                                        v-model="cancelForms[inv.id].reason"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Ej: Error en NIT del receptor"
                                        class="flex-1 h-8 px-3 rounded-lg border border-[var(--color-err)]/40 bg-[var(--color-surface)] text-sm focus:outline-none focus:ring-1 focus:ring-[var(--color-err)]"
                                        @keyup.enter="submitCancel(inv)"
                                        @keyup.escape="closeCancel(inv.id)"
                                    />
                                    <Button
                                        size="sm"
                                        class="bg-[var(--color-err)] text-white hover:bg-[var(--color-err)]/90 border-0"
                                        :disabled="!cancelForms[inv.id].reason?.trim()"
                                        @click="submitCancel(inv)"
                                    >
                                        Confirmar anulación
                                    </Button>
                                </div>
                                <p class="mt-1 text-xs text-[var(--color-err)]/70">
                                    Esta acción es irreversible. Se enviará la solicitud de anulación a SAT Guatemala.
                                </p>
                            </td>
                        </tr>
                    </template>
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
@reference "../../../../css/app.css";
.table-th {
    @apply text-left px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs;
}
.table-td {
    @apply px-4 py-3;
}
</style>
