<script setup>
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    entries:     { type: Object, required: true },
    ingredients: { type: Array,  required: true },
    filters:     { type: Object, required: true },
});

const ingredientId = ref(props.filters.ingredient_id ?? '');
const fromDate     = ref(props.filters.from);
const toDate       = ref(props.filters.to);

function apply() {
    router.get('/admin/stock-entries', {
        ingredient_id: ingredientId.value || undefined,
        from:          fromDate.value,
        to:            toDate.value,
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

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' });
}

function currency(v) {
    return v !== null && v !== undefined ? `Q ${Number(v).toFixed(4)}` : '—';
}
</script>

<template>
    <Head title="Historial de entradas — Admin" />
    <AppLayout title="Historial de entradas de stock">

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6 items-end">
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Ingrediente</label>
                <select v-model="ingredientId"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm min-w-40">
                    <option value="">Todos</option>
                    <option v-for="i in ingredients" :key="i.id" :value="i.id">
                        {{ i.name }} ({{ i.unit }})
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Desde</label>
                <input v-model="fromDate" type="date"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm" />
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Hasta</label>
                <input v-model="toDate" type="date"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm" />
            </div>
            <div class="flex gap-2 items-end">
                <Button size="sm" @click="apply">Filtrar</Button>
                <Button variant="ghost" size="sm" @click="setPreset(7)">7 días</Button>
                <Button variant="ghost" size="sm" @click="setPreset(30)">30 días</Button>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide text-[var(--color-fg-muted)]">
                    <tr>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Ingrediente</th>
                        <th class="px-4 py-3 text-right">Cantidad</th>
                        <th class="px-4 py-3 text-right">Costo unitario</th>
                        <th class="px-4 py-3 text-left">Nota</th>
                        <th class="px-4 py-3 text-left">Usuario</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border-faint)]">
                    <tr v-if="!entries.data.length">
                        <td colspan="6" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">
                            Sin entradas en el período seleccionado.
                        </td>
                    </tr>
                    <tr v-for="e in entries.data" :key="e.id" class="hover:bg-[var(--color-surface)]/50">
                        <td class="px-4 py-3 text-xs text-[var(--color-fg-muted)]">{{ fmt(e.created_at) }}</td>
                        <td class="px-4 py-3 font-medium">
                            {{ e.ingredient.name }}
                            <span class="text-xs text-[var(--color-fg-dim)] ml-1">({{ e.ingredient.unit }})</span>
                        </td>
                        <td class="px-4 py-3 text-right font-numeric text-green-600">+{{ e.quantity }}</td>
                        <td class="px-4 py-3 text-right font-numeric text-[var(--color-fg-muted)]">{{ currency(e.cost_price) }}</td>
                        <td class="px-4 py-3 text-[var(--color-fg-muted)] text-xs italic">{{ e.notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs">{{ e.user }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="entries.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-[var(--color-fg-muted)]">{{ entries.from }}–{{ entries.to }} de {{ entries.total }}</span>
            <div class="flex gap-1">
                <Link v-for="link in entries.links" :key="link.label" :href="link.url ?? '#'"
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
