<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    ingredients: { type: Array, default: () => [] },
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

// ── Stats ────────────────────────────────────────────────────────────────────
const lowCount = computed(() => props.ingredients.filter((i) => i.low_stock && i.active).length);
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
            v-if="lowCount > 0"
            class="mb-6 rounded-xl border border-[var(--color-warn)]/40 bg-[var(--color-warn)]/10 px-5 py-3 text-sm text-[var(--color-warn)] flex items-center gap-2"
        >
            <span class="text-base">⚠️</span>
            <span>{{ lowCount }} ingrediente{{ lowCount > 1 ? 's' : '' }} con stock bajo mínimo</span>
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
                    <tr v-if="!ingredients.length">
                        <td colspan="6" class="px-4 py-10 text-center text-[var(--color-fg-dim)] text-sm">
                            Sin ingredientes aún.
                        </td>
                    </tr>

                    <!-- Read row -->
                    <tr
                        v-for="ing in ingredients"
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
                                    <Button variant="subtle" size="sm" @click="startEdit(ing)">Editar</Button>
                                    <Button variant="subtle" size="sm" @click="destroy(ing)">✕</Button>
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
