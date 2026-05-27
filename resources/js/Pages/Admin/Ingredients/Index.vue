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

const UNITS = [
    { value: 'lb',      label: 'Libra (lb)' },
    { value: 'oz',      label: 'Onza (oz)' },
    { value: 'kg',      label: 'Kilogramo (kg)' },
    { value: 'g',       label: 'Gramo (g)' },
    { value: 'L',       label: 'Litro (L)' },
    { value: 'mL',      label: 'Mililitro (mL)' },
    { value: 'unit',    label: 'Unidad (und)' },
    { value: 'portion', label: 'Porción (por)' },
];

const UNIT_LABELS = Object.fromEntries(UNITS.map((u) => [u.value, u.label]));

// ── New ingredient form ──────────────────────────────────────────────────────
const showForm    = ref(false);
const newPkgs     = ref('');
const newUnits    = ref('');
const form = useForm({
    name:               '',
    unit:               'unit',
    purchase_unit:      '',
    units_per_package:  '',
    quantity_on_hand:   '',
    minimum_stock:      '',
});

function calcNewTotal() {
    const factor = parseFloat(form.units_per_package) || 0;
    const pkgs   = parseFloat(newPkgs.value)  || 0;
    const units  = parseFloat(newUnits.value) || 0;
    return parseFloat(((pkgs * factor) + units).toFixed(4));
}

function applyNewPackages() {
    const total = calcNewTotal();
    if (total > 0) form.quantity_on_hand = total;
}

function submitNew() {
    if (form.purchase_unit && form.units_per_package) {
        applyNewPackages();
    }
    form.post('/admin/ingredients', {
        onSuccess: () => {
            form.reset();
            newPkgs.value   = '';
            newUnits.value  = '';
            showForm.value = false;
        },
    });
}

// ── Inline edit ──────────────────────────────────────────────────────────────
const editing = ref(null);
const editForm = useForm({
    name:               '',
    unit:               'unit',
    purchase_unit:      '',
    units_per_package:  '',
    quantity_on_hand:   '',
    minimum_stock:      '',
    active:             true,
});

function startEdit(ing) {
    restocking.value = null;
    editing.value = ing.id;
    editForm.name               = ing.name;
    editForm.unit               = ing.unit;
    editForm.purchase_unit      = ing.purchase_unit ?? '';
    editForm.units_per_package  = ing.units_per_package ?? '';
    editForm.quantity_on_hand   = ing.quantity_on_hand;
    editForm.minimum_stock      = ing.minimum_stock;
    editForm.active             = ing.active;
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
const restocking    = ref(null);
const restockIng    = ref(null);
const restockPkgs   = ref('');
const restockUnits  = ref('');
const restockForm   = useForm({
    quantity:   '',
    cost_price: '',
    notes:      '',
});

function startRestock(ing) {
    editing.value       = null;
    restocking.value    = ing.id;
    restockIng.value    = ing;
    restockPkgs.value   = '';
    restockUnits.value  = '';
    restockForm.reset();
}

function calcRestockTotal() {
    const factor = restockIng.value?.units_per_package ?? 0;
    const pkgs   = parseFloat(restockPkgs.value)  || 0;
    const units  = parseFloat(restockUnits.value) || 0;
    return parseFloat(((pkgs * factor) + units).toFixed(4));
}

function applyPackages() {
    const total = calcRestockTotal();
    if (total > 0) restockForm.quantity = total;
}

function submitRestock(ing) {
    if (ing.purchase_unit && ing.units_per_package) {
        applyPackages();
    }
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

                <!-- Fila 1: nombre + unidad -->
                <div class="col-span-2">
                    <label class="field-label">Nombre</label>
                    <input v-model="form.name" class="field-input" placeholder="Ej. Carne molida" required />
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div class="col-span-2">
                    <label class="field-label">Unidad de almacén</label>
                    <select v-model="form.unit" class="field-input">
                        <option v-for="u in UNITS" :key="u.value" :value="u.value">{{ u.label }}</option>
                    </select>
                </div>

                <!-- Fila 2: empaque (UoM) — va ANTES del stock para que la conversión tenga contexto -->
                <div class="col-span-2 md:col-span-4 border-t border-[var(--color-border-faint)] pt-4">
                    <p class="text-xs font-medium text-[var(--color-fg-muted)] uppercase tracking-widest mb-3">
                        Empaque de compra
                        <span class="normal-case font-normal text-[var(--color-fg-dim)]">— opcional · caja, fardo, paca, etc.</span>
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="field-label">Nombre del empaque</label>
                            <input v-model="form.purchase_unit" class="field-input" placeholder="Ej. caja, fardo" />
                            <p v-if="form.errors.purchase_unit" class="field-error">{{ form.errors.purchase_unit }}</p>
                        </div>
                        <div>
                            <label class="field-label">
                                Unidades por empaque
                                <span v-if="form.purchase_unit && form.unit" class="font-normal text-[var(--color-fg-dim)]">
                                    (1 {{ form.purchase_unit }} = ? {{ form.unit }})
                                </span>
                            </label>
                            <input v-model="form.units_per_package" type="number" step="0.0001" min="0.0001" class="field-input"
                                placeholder="Ej. 12"
                                @input="applyNewPackages" />
                            <p v-if="form.errors.units_per_package" class="field-error">{{ form.errors.units_per_package }}</p>
                        </div>
                    </div>
                </div>

                <!-- Fila 3: stock inicial -->
                <div class="col-span-2 md:col-span-4 border-t border-[var(--color-border-faint)] pt-4">
                    <p class="text-xs font-medium text-[var(--color-fg-muted)] uppercase tracking-widest mb-3">Stock inicial</p>

                    <!-- Con empaque: cajas + sueltas + total -->
                    <div v-if="form.purchase_unit && form.units_per_package" class="flex flex-wrap items-end gap-x-2 gap-y-3 mb-4">
                        <div class="flex flex-col gap-1">
                            <label class="field-label">
                                {{ form.purchase_unit }}s
                                <span class="font-normal text-[var(--color-fg-dim)]">(× {{ form.units_per_package }} {{ form.unit }})</span>
                            </label>
                            <input v-model="newPkgs" type="number" step="1" min="0" class="field-input w-28"
                                placeholder="0" @input="applyNewPackages" />
                        </div>

                        <div class="flex items-end pb-2 text-[var(--color-fg-dim)]">+</div>

                        <div class="flex flex-col gap-1">
                            <label class="field-label">Sueltas ({{ form.unit }})</label>
                            <input v-model="newUnits" type="number" step="0.0001" min="0" class="field-input w-28"
                                placeholder="0" @input="applyNewPackages" />
                        </div>

                        <div class="flex items-end pb-2 text-[var(--color-fg-dim)]">=</div>

                        <div class="flex flex-col gap-1">
                            <label class="field-label">Total inicial ({{ form.unit }})</label>
                            <span class="h-10 flex items-center gap-1.5 px-3 rounded-lg border text-sm font-numeric font-bold tabular-nums min-w-[5rem] transition-colors"
                                :class="calcNewTotal() > 0
                                    ? 'border-[var(--color-ok)]/40 bg-[var(--color-ok)]/10 text-[var(--color-ok)]'
                                    : 'border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-[var(--color-fg-dim)]'">
                                {{ calcNewTotal() > 0 ? calcNewTotal() : '—' }}
                                <span class="text-xs font-normal ml-0.5">{{ form.unit }}</span>
                            </span>
                            <input v-model="form.quantity_on_hand" type="hidden" required />
                        </div>
                        <p v-if="form.errors.quantity_on_hand" class="w-full field-error">{{ form.errors.quantity_on_hand }}</p>
                    </div>

                    <!-- Sin empaque: entrada directa -->
                    <div v-else class="flex flex-wrap gap-4 mb-4">
                        <div class="flex flex-col gap-1">
                            <label class="field-label">Stock actual</label>
                            <input v-model="form.quantity_on_hand" type="number" step="0.0001" min="0"
                                class="field-input w-40" required />
                            <p v-if="form.errors.quantity_on_hand" class="field-error">{{ form.errors.quantity_on_hand }}</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="field-label">Stock mínimo ({{ form.unit }})</label>
                        <input v-model="form.minimum_stock" type="number" step="0.0001" min="0"
                            class="field-input w-40" required />
                    </div>
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
                        <!-- Restock row -->
                        <template v-if="restocking === ing.id">
                            <td colspan="5" class="px-4 py-3">
                                <div class="flex flex-col gap-3">

                                    <!-- Con empaque: layout de conversión -->
                                    <template v-if="ing.purchase_unit && ing.units_per_package">
                                        <div class="flex flex-wrap gap-x-2 gap-y-3">

                                            <!-- Stock actual -->
                                            <div class="flex flex-col gap-1">
                                                <span class="field-label">Stock actual</span>
                                                <span class="h-10 flex items-center gap-1.5 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm font-numeric font-semibold text-[var(--color-fg-muted)] tabular-nums">
                                                    {{ ing.quantity_on_hand }} <span class="text-xs font-normal">{{ ing.unit }}</span>
                                                </span>
                                            </div>

                                            <div class="flex items-end pb-2 text-[var(--color-fg-dim)]">+</div>

                                            <!-- Cajas: hint en el label, no debajo del input -->
                                            <div class="flex flex-col gap-1">
                                                <span class="field-label">
                                                    {{ ing.purchase_unit }}s
                                                    <span class="font-normal text-[var(--color-fg-dim)]">(× {{ ing.units_per_package }} {{ ing.unit }})</span>
                                                </span>
                                                <input v-model="restockPkgs" type="number" step="1" min="0"
                                                    class="field-input w-24" placeholder="0"
                                                    @input="applyPackages" autofocus />
                                            </div>

                                            <div class="flex items-end pb-2 text-[var(--color-fg-dim)]">+</div>

                                            <!-- Sueltas -->
                                            <div class="flex flex-col gap-1">
                                                <span class="field-label">Sueltas ({{ ing.unit }})</span>
                                                <input v-model="restockUnits" type="number" step="0.0001" min="0"
                                                    class="field-input w-24" placeholder="0"
                                                    @input="applyPackages" />
                                            </div>

                                            <div class="flex items-end pb-2 text-[var(--color-fg-dim)]">=</div>

                                            <!-- Total -->
                                            <div class="flex flex-col gap-1">
                                                <span class="field-label">Total a ingresar</span>
                                                <span class="h-10 flex items-center gap-1.5 px-3 rounded-lg border text-sm font-numeric font-bold tabular-nums min-w-[5rem] transition-colors"
                                                    :class="calcRestockTotal() > 0
                                                        ? 'border-[var(--color-ok)]/40 bg-[var(--color-ok)]/10 text-[var(--color-ok)]'
                                                        : 'border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-[var(--color-fg-dim)]'">
                                                    {{ calcRestockTotal() > 0 ? calcRestockTotal() : '—' }}
                                                    <span class="text-xs font-normal ml-0.5">{{ ing.unit }}</span>
                                                </span>
                                                <span v-if="calcRestockTotal() > 0" class="text-[11px] text-[var(--color-fg-dim)]">
                                                    → {{ (ing.quantity_on_hand + calcRestockTotal()).toFixed(4) }} {{ ing.unit }} total
                                                </span>
                                            </div>

                                            <div class="w-px bg-[var(--color-border-faint)] self-stretch mx-1"></div>

                                            <!-- Costo -->
                                            <div class="flex flex-col gap-1">
                                                <span class="field-label">Costo ({{ ing.unit }}) — opcional</span>
                                                <input v-model="restockForm.cost_price" type="number" step="0.0001" min="0"
                                                    class="field-input w-28" placeholder="Q 0.00" />
                                            </div>

                                            <!-- Nota -->
                                            <div class="flex flex-col gap-1">
                                                <span class="field-label">Nota</span>
                                                <input v-model="restockForm.notes" type="text" maxlength="255"
                                                    class="field-input w-44" placeholder="Proveedor, lote…" />
                                            </div>

                                        </div>
                                        <p v-if="restockForm.errors.quantity" class="field-error">{{ restockForm.errors.quantity }}</p>
                                    </template>

                                    <!-- Sin empaque: layout simple -->
                                    <div v-else class="flex flex-wrap items-end gap-3">
                                        <div class="flex flex-col gap-1">
                                            <span class="field-label">Stock actual</span>
                                            <span class="h-10 flex items-center gap-1.5 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm font-numeric font-semibold text-[var(--color-fg-muted)] tabular-nums">
                                                {{ ing.quantity_on_hand }}<span class="text-xs font-normal">{{ ing.unit }}</span>
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="field-label">Cantidad a agregar</span>
                                            <input v-model="restockForm.quantity" type="number" step="0.0001" min="0.0001"
                                                class="field-input w-32" placeholder="Ej. 10" autofocus />
                                            <p v-if="restockForm.errors.quantity" class="field-error">{{ restockForm.errors.quantity }}</p>
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="field-label">Costo ({{ ing.unit }}) — opcional</span>
                                            <input v-model="restockForm.cost_price" type="number" step="0.0001" min="0"
                                                class="field-input w-32" placeholder="Q 0.00" />
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <span class="field-label">Nota</span>
                                            <input v-model="restockForm.notes" type="text" maxlength="255"
                                                class="field-input w-44" placeholder="Proveedor, lote…" />
                                        </div>
                                    </div>

                                </div>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="ghost" size="sm" @click="cancelRestock">Cancelar</Button>
                                    <Button size="sm" :loading="restockForm.processing" @click="submitRestock(ing)">Confirmar</Button>
                                </div>
                            </td>
                        </template>

                        <!-- Edit row (inline) -->
                        <template v-else-if="editing === ing.id">
                            <td class="px-4 py-2">
                                <input v-model="editForm.name" class="field-input" />
                            </td>
                            <td class="px-4 py-2">
                                <select v-model="editForm.unit" class="field-input mb-1">
                                    <option v-for="u in UNITS" :key="u.value" :value="u.value">{{ u.label }}</option>
                                </select>
                                <div class="flex gap-1 mt-1">
                                    <input v-model="editForm.purchase_unit" class="field-input text-xs" style="height:2rem"
                                        placeholder="caja" title="Nombre del empaque" />
                                    <input v-model="editForm.units_per_package" type="number" step="0.0001" min="0.0001"
                                        class="field-input text-xs w-16" style="height:2rem"
                                        placeholder="12" title="Unidades por empaque" />
                                </div>
                                <p class="text-[10px] text-[var(--color-fg-dim)] mt-0.5 leading-tight">empaque · und/empaque</p>
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

                        <!-- Read row -->
                        <template v-else>
                            <td class="px-4 py-3 font-medium">
                                {{ ing.name }}
                                <Badge v-if="ing.low_stock && ing.active" tone="warn" size="sm" class="ml-2">bajo</Badge>
                            </td>
                            <td class="px-4 py-3 text-[var(--color-fg-muted)]">
                                {{ UNIT_LABELS[ing.unit] }}
                                <span v-if="ing.purchase_unit && ing.units_per_package"
                                    class="block text-[11px] text-[var(--color-fg-dim)] mt-0.5">
                                    1 {{ ing.purchase_unit }} = {{ ing.units_per_package }} {{ ing.unit }}
                                </span>
                            </td>
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
@reference "../../../../css/app.css";
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
