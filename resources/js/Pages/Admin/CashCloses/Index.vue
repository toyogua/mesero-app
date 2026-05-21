<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    closes:  { type: Object, required: true },
    preview: { type: Object, required: true },
});

const showForm = ref(false);

const form = useForm({
    cash_counted: '',
    card_counted: '',
    notes:        '',
});

function submit() {
    form.post('/admin/cash-closes', {
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
}

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' });
}

function diff(counted, expected) {
    if (counted === null || counted === undefined || counted === '') return null;
    return Number(counted) - Number(expected);
}
</script>

<template>
    <Head title="Cierre de caja — Admin" />
    <AppLayout title="Cierre de caja">

        <!-- Preview + action -->
        <div class="mb-8 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-semibold text-base">Período pendiente de cierre</h2>
                    <p class="text-xs text-[var(--color-fg-muted)] mt-0.5">
                        {{ fmt(preview.period_from) }} → {{ fmt(preview.period_to) }}
                    </p>
                </div>
                <Button size="sm" :disabled="preview.checks_count === 0" @click="showForm = !showForm">
                    {{ showForm ? 'Cancelar' : 'Realizar cierre' }}
                </Button>
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                <div v-for="card in [
                    { label: 'Comandas',  value: preview.checks_count, fmt: 'num' },
                    { label: 'Subtotal',  value: preview.subtotal,     fmt: 'cur' },
                    { label: 'IVA',       value: preview.tax,          fmt: 'cur' },
                    { label: 'Propinas',  value: preview.tip,          fmt: 'cur' },
                    { label: 'Total',     value: preview.total,        fmt: 'cur' },
                ]" :key="card.label"
                    class="rounded-xl border border-[var(--color-border-faint)] px-4 py-3">
                    <div class="text-xl font-semibold font-numeric">
                        {{ card.fmt === 'cur' ? currency(card.value) : card.value }}
                    </div>
                    <div class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] mt-0.5">{{ card.label }}</div>
                </div>
            </div>

            <p v-if="preview.checks_count === 0" class="text-sm text-[var(--color-fg-dim)]">
                No hay comandas cerradas desde el último cierre.
            </p>

            <!-- Close form -->
            <form v-if="showForm" @submit.prevent="submit" class="border-t border-[var(--color-border-faint)] pt-5 mt-2">
                <p class="text-xs text-[var(--color-fg-muted)] mb-4">
                    Ingresá los montos contados físicamente para cuadrar caja (opcional).
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-[var(--color-fg-muted)] mb-1">Efectivo contado</label>
                        <input v-model="form.cash_counted" type="number" step="0.01" min="0"
                            class="w-full h-10 px-3 rounded-lg text-sm border border-[var(--color-border)] bg-[var(--color-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40"
                            placeholder="Q 0.00" />
                        <p v-if="form.cash_counted !== ''" class="text-xs mt-1"
                            :class="diff(form.cash_counted, preview.total) >= 0 ? 'text-green-600' : 'text-red-500'">
                            Diferencia: {{ currency(diff(form.cash_counted, preview.total)) }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--color-fg-muted)] mb-1">Tarjeta / transferencia</label>
                        <input v-model="form.card_counted" type="number" step="0.01" min="0"
                            class="w-full h-10 px-3 rounded-lg text-sm border border-[var(--color-border)] bg-[var(--color-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40"
                            placeholder="Q 0.00" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-[var(--color-fg-muted)] mb-1">Notas</label>
                        <input v-model="form.notes" type="text" maxlength="500"
                            class="w-full h-10 px-3 rounded-lg text-sm border border-[var(--color-border)] bg-[var(--color-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40"
                            placeholder="Observaciones…" />
                    </div>
                </div>
                <p v-if="form.errors.close" class="text-xs text-red-500 mt-2">{{ form.errors.close }}</p>
                <div class="flex justify-end mt-4">
                    <Button type="submit" :loading="form.processing">Confirmar cierre</Button>
                </div>
            </form>
        </div>

        <!-- History table -->
        <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
            <div class="px-4 py-3 border-b border-[var(--color-border-faint)] font-medium text-sm">
                Cierres anteriores
            </div>
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide text-[var(--color-fg-muted)]">
                    <tr>
                        <th class="px-4 py-3 text-left">Período</th>
                        <th class="px-4 py-3 text-right">Comandas</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Efectivo</th>
                        <th class="px-4 py-3 text-right">Tarjeta</th>
                        <th class="px-4 py-3 text-left">Usuario</th>
                        <th class="px-4 py-3 text-left">Registrado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border-faint)]">
                    <tr v-if="!closes.data.length">
                        <td colspan="7" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">
                            Sin cierres registrados aún.
                        </td>
                    </tr>
                    <tr v-for="c in closes.data" :key="c.id" class="hover:bg-[var(--color-surface)]/50">
                        <td class="px-4 py-3 text-xs text-[var(--color-fg-muted)]">
                            <span class="font-mono">{{ fmt(c.period_from) }}</span>
                            <span class="mx-1">→</span>
                            <span class="font-mono">{{ fmt(c.period_to) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right font-numeric">{{ c.checks_count }}</td>
                        <td class="px-4 py-3 text-right font-numeric font-semibold">{{ currency(c.total) }}</td>
                        <td class="px-4 py-3 text-right font-numeric text-[var(--color-fg-muted)]">
                            {{ c.cash_counted !== null ? currency(c.cash_counted) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-numeric text-[var(--color-fg-muted)]">
                            {{ c.card_counted !== null ? currency(c.card_counted) : '—' }}
                        </td>
                        <td class="px-4 py-3">{{ c.user }}</td>
                        <td class="px-4 py-3 text-xs text-[var(--color-fg-muted)]">{{ fmt(c.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="closes.last_page > 1" class="flex items-center justify-between text-sm mt-4">
            <span class="text-[var(--color-fg-muted)]">{{ closes.total }} cierres registrados</span>
            <div class="flex gap-1">
                <Link v-for="link in closes.links" :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1 rounded-lg border border-[var(--color-border-faint)]',
                        link.active ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'bg-[var(--color-surface)]',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>

    </AppLayout>
</template>
