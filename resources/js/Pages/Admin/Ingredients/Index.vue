<script setup>
import { ref } from 'vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    ingredients:     { type: Object, required: true },
    low_stock_count: { type: Number, default: 0 },
    filters:         { type: Object, required: true },
});

const UNITS = ['unit', 'kg', 'g', 'L', 'mL', 'portion'];
const UNIT_LABELS = { unit: 'Unidad', kg: 'kg', g: 'g', L: 'L', mL: 'mL', portion: 'Porción' };

// ── New ingredient form ──────────────────────────────────────────────────────
const showForm = ref(false);
const form = useForm({
    name: '',
    unit: 'unit',
    quantity_on_hand: '',
    minimum_stock: '',
});

function submitNew() {
    form.post('/admin/ingredients', {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

// ── Inline edit ──────────────────────────────────────────────────────────────
const editing = ref(null);
const editForm = useForm({
    name: '',
    unit: 'unit',
    quantity_on_hand: '',
    minimum_stock: '',
    active: true,
});

function startEdit(ing) {
    editing.value = ing.id;
    editForm.name = ing.name;
    editForm.unit = ing.unit;
    editForm.quantity_on_hand = ing.quantity_on_hand;
    editForm.minimum_stock = ing.minimum_stock;
    editForm.active = ing.active;
}

function submitEdit(ing) {
    editForm.patch(`/admin/ingredients/${ing.id}`, {
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function cancelEdit() {
    editing.value = null;
}

function destroy(ing) {
    if (confirm(`¿Eliminar "${ing.name}"?`)) {
        router.delete(`/admin/ingredients/${ing.id}`);
    }
}

// ── Restock ──────────────────────────────────────────────────────────────────
const restocking = ref(null);
const restockForm = useForm({
    quantity:   '',
    cost_price: '',
    notes:      '',
});

function startRestock(ing) {
    restocking.value = ing.id;
    restockForm.reset();
}

function submitRestock(ing) {
    restockForm.post(`/admin/ingredients/${ing.id}/restock`, {
        onSuccess: () => {
            restocking.value = null;
        },
    });
}

function cancelRestock() {
    restocking.value = null;
}

// ── Search ───────────────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get('/admin/ingredients', { search: search.value || undefined }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Ingredientes — Admin" />
    <AppLayout title="Ingredientes">
        <template #actions>
            <Button @click="showForm = !showForm" size="sm">
                {{ showForm ? 'Cancelar' : '+ Nuevo ingrediente' }}
            </Button>
        </template>

        <!-- Low stock banner -->
        <div
            v-if="low_stock_count > 0"
            class="mb-4 rounded-xl border border-[var(--color-warn)]/40 bg-[var(--color-warn)]/10 px-5 py-3 text-sm text-[var(--color-warn)] flex items-center gap-2"
        >
            <span>{{ low_stock_count }} ingrediente{{ low_stock_count > 1 ? 's' : '' }} con stock bajo mínimo</span>
        </div>

        <!-- Search bar -->
        <div class="flex gap-2 mb-6">
            <input
                v-model="search"
                type="search"
                placeholder="Buscar ingrediente…"
                class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm w-64"
                @keydown.enter="applySearch"
            />
            <Button size="sm" variant="ghost" @click="applySearch">Buscar</Button>
        </div>

        <!-- New ingredient form -->
        <div
            v-if="showForm"
            class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6"
        >
            <h2 class="text-sm font-semibold uppercase tracking-widest text-[var(--color-fg-muted)] mb-4">
                Nuevo ingrediente
            </h2>
            <form @submit.prevent="submitNew" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="col-span-2">
                    <label class="field-label">Nombre</label>
                    <input v-model="form.name" class="field-input" placeholder="Ej. Carne molida" required />
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="field-label">Unidad</label>
                    <select v-model="form.unit" class="field-input">
                        <option v-for="u in UNITS" :key="u" :value="u">{{ UNIT_LABELS[u] }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Stock actual</label>
                    <input v-model="form.quantity_on_hand" type="number" step="0.0001" min="0" class="field-input" required />
                </div>
                <div>
                    <label class="field-label">Stock mínimo</label>
                    <input v-model="form.minimum_stock" type="number" step="0.0001" min="0" class="field-input" required />
                </div>
                <div class="col-span-2 md:col-span-4 flex justify-end gap-3">
                    <Button variant="ghost" size="sm" type="button" @click="showForm = false">Cancelar</Button>
                    <Button size="sm" type="submit" :loading="form.processing">Guardar</Button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs">Nombre</th>
                        <th class="text-left px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs">Unidad</th>
                        <th class="text-right px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs">En stock</th>
                        <th class="text-right px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs">Mínimo</th>
                        <th class="text-center px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs">Estado</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border-faint)]">
                    <tr v-if="!ingredients.data.length">
                        <td colspan="6" class="px-4 py-10 text-center text-[var(--color-fg-dim)] text-sm">
                            Sin ingredientes aún.
                        </td>
                    </tr>

                    <!-- Read row -->
                    <tr
                        v-for="ing in ingredients.data"
                        v-else
                        :key="ing.id"
                        class="hover:bg-[var(--color-surface)]/50"
                    >
                        <template v-if="editing !== ing.id">
                            <td class="px-4 py-3 font-medium">
                                {{ ing.name }}
                                <Badge v-if="ing.low_stock && ing.active" tone="warn" size="sm" class="ml-2">bajo</Badge>
                            </td>
                            <td class="px-4 py-3 text-[var(--color-fg-muted)]">{{ UNIT_LABELS[ing.unit] }}</td>
                            <td class="px-4 py-3 text-right font-numeric">{{ ing.quantity_on_hand }}</td>
                            <td class="px-4 py-3 text-right font-numeric text-[var(--color-fg-muted)]">{{ ing.minimum_stock }}</td>
                            <td class="px-4 py-3 text-center">
                                <Badge :tone="ing.active ? 'ok' : 'neutral'" size="sm">
                                    {{ ing.active ? 'Activo' : 'Inactivo' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="subtle" size="sm" @click="startRestock(ing)">Reponer</Button>
                                    <Button variant="subtle" size="sm" @click="startEdit(ing)">Editar</Button>
                                    <Button variant="subtle" size="sm" @click="destroy(ing)">✕</Button>
                                </div>
                            </td>
                        </template>

                        <!-- Restock row -->
                        <template v-else-if="restocking === ing.id">
                            <td colspan="4" class="px-4 py-2">
                                <div class="flex flex-wrap gap-3 items-end">
                                    <div>
                                        <label class="field-label">Cantidad a agregar</label>
                                        <input v-model="restockForm.quantity" type="number" step="0.0001" min="0.0001"
                                            class="field-input w-36" placeholder="Ej. 10" autofocus />
                                        <p v-if="restockForm.errors.quantity" class="field-error">{{ restockForm.errors.quantity }}</p>
                                    </div>
                                    <div>
                                        <label class="field-label">Nuevo costo ({{ ing.unit }}) — opcional</label>
                                        <input v-model="restockForm.cost_price" type="number" step="0.0001" min="0"
                                            class="field-input w-36" placeholder="Q 0.00" />
                                    </div>
                                    <div>
                                        <label class="field-label">Nota</label>
                                        <input v-model="restockForm.notes" type="text" maxlength="255"
                                            class="field-input w-48" placeholder="Proveedor, lote…" />
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 text-center"></td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="ghost" size="sm" @click="cancelRestock">Cancelar</Button>
                                    <Button size="sm" :loading="restockForm.processing" @click="submitRestock(ing)">Confirmar</Button>
                                </div>
                            </td>
                        </template>

                        <!-- Edit row (inline) -->
                        <template v-else>
                            <td class="px-4 py-2">
                                <input v-model="editForm.name" class="field-input" />
                            </td>
                            <td class="px-4 py-2">
                                <select v-model="editForm.unit" class="field-input">
                                    <option v-for="u in UNITS" :key="u" :value="u">{{ UNIT_LABELS[u] }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input v-model="editForm.quantity_on_hand" type="number" step="0.0001" min="0" class="field-input text-right" />
                            </td>
                            <td class="px-4 py-2">
                                <input v-model="editForm.minimum_stock" type="number" step="0.0001" min="0" class="field-input text-right" />
                            </td>
                            <td class="px-4 py-2 text-center">
                                <label class="flex items-center justify-center gap-1 cursor-pointer">
                                    <input v-model="editForm.active" type="checkbox" />
                                    <span class="text-xs text-[var(--color-fg-muted)]">Activo</span>
                                </label>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="ghost" size="sm" @click="cancelEdit">Cancelar</Button>
                                    <Button size="sm" :loading="editForm.processing" @click="submitEdit(ing)">Guardar</Button>
                                </div>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div v-if="ingredients.last_page > 1" class="flex items-center justify-between text-sm mt-4">
            <span class="text-[var(--color-fg-muted)]">{{ ingredients.from }}–{{ ingredients.to }} de {{ ingredients.total }}</span>
            <div class="flex gap-1">
                <Link
                    v-for="link in ingredients.links" :key="link.label"
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

<style scoped>
.field-label {
    @apply block text-xs font-medium text-[var(--color-fg-muted)] mb-1;
}
.field-input {
    @apply w-full h-10 px-3 rounded-lg text-sm
           bg-[var(--color-surface)] border border-[var(--color-border)]
           text-[var(--color-fg)]
           focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40;
}
.field-error {
    @apply text-xs text-[var(--color-err)] mt-1;
}
</style>
