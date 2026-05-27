<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import UnitConverter from '@/Components/UI/UnitConverter.vue';

const props = defineProps({
    groups:      { type: Array, default: () => [] },
    ingredients: { type: Array, default: () => [] },
});

// ── Preset helpers ────────────────────────────────────────────────────────────
function derivePreset(min, max) {
    if (max === 1) return 'single';
    if (max === null && min <= 1) return 'multi';
    return 'custom';
}

// ── New group form ────────────────────────────────────────────────────────────
const showForm   = ref(false);
const formPreset = ref('single');
const form = useForm({ name: '', min_selections: 0, max_selections: 1 });

function setFormPreset(preset) {
    const wasRequired = form.min_selections >= 1;
    formPreset.value = preset;
    if (preset === 'single') { form.min_selections = wasRequired ? 1 : 0; form.max_selections = 1; }
    else if (preset === 'multi') { form.min_selections = wasRequired ? 1 : 0; form.max_selections = null; }
}

function setFormRequired(required) {
    if (formPreset.value !== 'custom') form.min_selections = required ? 1 : 0;
}

function submitGroup() {
    form.post('/admin/modifier-groups', {
        onSuccess: () => { form.reset(); formPreset.value = 'single'; showForm.value = false; },
    });
}

// ── Inline group edit ─────────────────────────────────────────────────────────
const editingGroup   = ref(null);
const editFormPreset = ref('single');
const editGroupForm  = useForm({ name: '', min_selections: 0, max_selections: 1 });

function startEditGroup(g) {
    editingGroup.value   = g.id;
    editFormPreset.value = derivePreset(g.min_selections, g.max_selections);
    editGroupForm.name           = g.name;
    editGroupForm.min_selections = g.min_selections;
    editGroupForm.max_selections = g.max_selections;
}

function setEditFormPreset(preset) {
    const wasRequired = editGroupForm.min_selections >= 1;
    editFormPreset.value = preset;
    if (preset === 'single') { editGroupForm.min_selections = wasRequired ? 1 : 0; editGroupForm.max_selections = 1; }
    else if (preset === 'multi') { editGroupForm.min_selections = wasRequired ? 1 : 0; editGroupForm.max_selections = null; }
}

function setEditFormRequired(required) {
    if (editFormPreset.value !== 'custom') editGroupForm.min_selections = required ? 1 : 0;
}

function submitEditGroup(g) {
    editGroupForm.patch(`/admin/modifier-groups/${g.id}`, {
        onSuccess: () => (editingGroup.value = null),
    });
}

function destroyGroup(g) {
    if (confirm(`¿Eliminar grupo "${g.name}" y todas sus opciones?`)) {
        router.delete(`/admin/modifier-groups/${g.id}`);
    }
}

// ── Expanded options panel ────────────────────────────────────────────────────
const expanded = ref(null);

function toggleExpand(id) {
    expanded.value = expanded.value === id ? null : id;
}

// ── New option form ───────────────────────────────────────────────────────────
const optionForms = ref({});

function optionFormFor(groupId) {
    if (!optionForms.value[groupId]) {
        optionForms.value[groupId] = useForm({ name: '', price_delta: 0 });
    }
    return optionForms.value[groupId];
}

function submitOption(groupId) {
    const f = optionFormFor(groupId);
    f.post(`/admin/modifier-groups/${groupId}/options`, {
        onSuccess: () => f.reset(),
    });
}

function destroyOption(group, option) {
    if (confirm(`¿Eliminar opción "${option.name}"?`)) {
        router.delete(`/admin/modifier-groups/${group.id}/options/${option.id}`);
    }
}

// ── Option ingredient lines ───────────────────────────────────────────────────
const expandedOption  = ref(null);
const ingForms        = ref({});

function toggleOptionIngredients(optionId) {
    expandedOption.value = expandedOption.value === optionId ? null : optionId;
}

function ingFormFor(optionId) {
    if (!ingForms.value[optionId]) {
        ingForms.value[optionId] = useForm({ ingredient_id: '', quantity_used: '' });
    }
    return ingForms.value[optionId];
}

function submitOptionIngredient(group, option) {
    const f = ingFormFor(option.id);
    f.post(`/admin/modifier-groups/${group.id}/options/${option.id}/ingredients`, {
        onSuccess: () => f.reset(),
    });
}

function destroyOptionIngredient(group, option, line) {
    router.delete(`/admin/modifier-groups/${group.id}/options/${option.id}/ingredients/${line.id}`);
}

function currency(v) {
    if (!v || v === 0) return '—';
    return `+Q ${Number(v).toFixed(2)}`;
}

// ── Ingredient hints & warnings (igual que en Recipes/Show) ──────────────────
const UNIT_NAMES = {
    lb: 'Libra', oz: 'Onza', kg: 'Kilogramo', g: 'Gramo',
    L: 'Litro', mL: 'Mililitro', unit: 'Unidad', portion: 'Porción',
};

const CONVERSION_HINTS = {
    lb:   'Es lb — 1 lb = 16 oz. Para media libra escribí 0.5',
    oz:   'Es oz — usá decimales para fracciones (ej: 2.5 oz)',
    kg:   'Es kg — para 150 g escribí 0.15 · para 500 g escribí 0.5',
    L:    'Es L — para 100 mL escribí 0.1 · para 500 mL escribí 0.5',
    g:    'Es g — si superás 500 g, considerá usar lb o kg',
    mL:   'Es mL — si superás 500 mL, considerá usar L',
    unit: 'Es unidad entera — usá 1, 2, 3… Los decimales no están permitidos',
};

const WARN_THRESHOLDS = { lb: 20, oz: 64, kg: 10, L: 10, g: 3000, mL: 3000, unit: 50, portion: 20 };

const ingredientMap = computed(() =>
    Object.fromEntries(props.ingredients.map((i) => [i.id, i])),
);

function unitFor(id) { return ingredientMap.value[id]?.unit ?? ''; }

function hintFor(id) { return CONVERSION_HINTS[unitFor(id)] ?? null; }

function warningFor(id, qty) {
    const unit  = unitFor(id);
    const value = parseFloat(qty);
    if (!unit || isNaN(value) || value <= 0) return null;
    const max = WARN_THRESHOLDS[unit];
    if (max && value > max) return `${value} ${unit} por porción parece muy alto. ¿Olvidaste convertir unidades?`;
    if (unit === 'unit' && !Number.isInteger(value)) return 'La unidad "unit" debería ser un número entero (1, 2, 3…).';
    return null;
}
</script>

<template>
    <Head title="Modificadores — Admin" />
    <AppLayout title="Grupos de modificadores">
        <template #actions>
            <Button size="sm" @click="showForm = !showForm">
                {{ showForm ? 'Cancelar' : '+ Nuevo grupo' }}
            </Button>
        </template>

        <!-- New group form -->
        <div v-if="showForm" class="mb-6 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-[var(--color-fg-muted)] mb-4">Nuevo grupo</h2>
            <form @submit.prevent="submitGroup" class="space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-48">
                        <label class="field-label">Nombre</label>
                        <input v-model="form.name" class="field-input" placeholder="Ej. Guarniciones, Término, Extras" required />
                        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    </div>

                    <!-- Preset selector -->
                    <div>
                        <label class="field-label">Tipo de selección</label>
                        <div class="flex rounded-lg border border-[var(--color-border)] overflow-hidden h-10">
                            <button
                                v-for="opt in [{ value: 'single', label: 'Una opción' }, { value: 'multi', label: 'Múltiple' }, { value: 'custom', label: 'Personalizado' }]"
                                :key="opt.value"
                                type="button"
                                class="px-3 text-xs font-medium transition border-r border-[var(--color-border)] last:border-0"
                                :class="formPreset === opt.value
                                    ? 'bg-[var(--color-primary)] text-[oklch(15%_0.02_60)]'
                                    : 'bg-[var(--color-surface)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]'"
                                @click="setFormPreset(opt.value)"
                            >{{ opt.label }}</button>
                        </div>
                    </div>

                    <!-- Required toggle -->
                    <label class="flex items-center gap-2 cursor-pointer h-10 pb-0">
                        <input
                            type="checkbox"
                            :checked="form.min_selections >= 1"
                            @change="setFormRequired($event.target.checked)"
                        />
                        <span class="text-sm">Obligatorio</span>
                    </label>

                    <!-- Custom min/max fields -->
                    <template v-if="formPreset === 'custom'">
                        <div class="w-24">
                            <label class="field-label">Mín.</label>
                            <input v-model.number="form.min_selections" type="number" min="0" class="field-input" />
                        </div>
                        <div class="w-24">
                            <label class="field-label">Máx. (vacío=∞)</label>
                            <input v-model.number="form.max_selections" type="number" min="1" placeholder="∞" class="field-input" />
                        </div>
                    </template>

                    <Button size="sm" type="submit" :loading="form.processing">Guardar</Button>
                </div>
                <p v-if="form.errors.min_selections || form.errors.max_selections" class="field-error">
                    {{ form.errors.min_selections || form.errors.max_selections }}
                </p>
            </form>
        </div>

        <!-- Groups list -->
        <div class="space-y-3">
            <div v-if="!groups.length" class="rounded-2xl border border-dashed border-[var(--color-border)] py-16 text-center text-[var(--color-fg-muted)] text-sm">
                Sin grupos todavía.
            </div>

            <div
                v-for="g in groups"
                :key="g.id"
                class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] overflow-hidden"
            >
                <!-- Group header row -->
                <div class="px-5 py-4 flex items-center gap-4">
                    <template v-if="editingGroup !== g.id">
                        <div class="flex-1 min-w-0">
                            <span class="font-semibold">{{ g.name }}</span>
                            <Badge :tone="g.min_selections > 0 ? 'warn' : 'neutral'" size="sm" class="ml-2">
                                {{ g.min_selections > 0 ? 'Requerido' : 'Opcional' }}
                            </Badge>
                            <Badge tone="neutral" size="sm" class="ml-1">
                                <template v-if="g.max_selections === 1">Una opción</template>
                                <template v-else-if="g.min_selections === g.max_selections">Exactamente {{ g.min_selections }}</template>
                                <template v-else-if="g.max_selections === null">Mín. {{ g.min_selections }}</template>
                                <template v-else>{{ g.min_selections }}–{{ g.max_selections }} opciones</template>
                            </Badge>
                        </div>
                        <span class="text-xs text-[var(--color-fg-dim)]">{{ g.options.length }} opciones</span>
                        <div class="flex items-center gap-2">
                            <Button variant="subtle" size="sm" @click="toggleExpand(g.id)">
                                {{ expanded === g.id ? '▲ Opciones' : '▼ Opciones' }}
                            </Button>
                            <Button variant="subtle" size="sm" @click="startEditGroup(g)">Editar</Button>
                            <Button variant="subtle" size="sm" @click="destroyGroup(g)">✕</Button>
                        </div>
                    </template>

                    <!-- Inline edit -->
                    <template v-else>
                        <input v-model="editGroupForm.name" class="field-input flex-1 min-w-32" />

                        <!-- Preset selector -->
                        <div class="flex rounded-lg border border-[var(--color-border)] overflow-hidden h-10 shrink-0">
                            <button
                                v-for="opt in [{ value: 'single', label: 'Una' }, { value: 'multi', label: 'Múltiple' }, { value: 'custom', label: 'Custom' }]"
                                :key="opt.value"
                                type="button"
                                class="px-2.5 text-xs font-medium transition border-r border-[var(--color-border)] last:border-0"
                                :class="editFormPreset === opt.value
                                    ? 'bg-[var(--color-primary)] text-[oklch(15%_0.02_60)]'
                                    : 'bg-[var(--color-surface)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]'"
                                @click="setEditFormPreset(opt.value)"
                            >{{ opt.label }}</button>
                        </div>

                        <label class="flex items-center gap-1 cursor-pointer text-sm shrink-0">
                            <input
                                type="checkbox"
                                :checked="editGroupForm.min_selections >= 1"
                                @change="setEditFormRequired($event.target.checked)"
                            />
                            Requerido
                        </label>

                        <template v-if="editFormPreset === 'custom'">
                            <input v-model.number="editGroupForm.min_selections" type="number" min="0" class="field-input w-16" placeholder="Mín" />
                            <input v-model.number="editGroupForm.max_selections" type="number" min="1" class="field-input w-16" placeholder="Máx" />
                        </template>

                        <Button variant="ghost" size="sm" @click="editingGroup = null">Cancelar</Button>
                        <Button size="sm" :loading="editGroupForm.processing" @click="submitEditGroup(g)">Guardar</Button>
                    </template>
                </div>

                <!-- Options panel -->
                <div v-if="expanded === g.id" class="border-t border-[var(--color-border-faint)] px-5 py-4 bg-[var(--color-surface-up)]/40">
                    <!-- Existing options -->
                    <div v-if="g.options.length" class="mb-4 space-y-2">
                        <div
                            v-for="opt in g.options"
                            :key="opt.id"
                            class="rounded-xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] overflow-hidden"
                        >
                            <!-- Option row -->
                            <div class="flex items-center gap-3 text-sm px-3 py-2">
                                <span class="flex-1 font-medium">{{ opt.name }}</span>
                                <span class="font-numeric text-[var(--color-fg-muted)]">{{ currency(opt.price_delta) }}</span>
                                <Badge :tone="opt.active ? 'ok' : 'neutral'" size="sm">
                                    {{ opt.active ? 'Activa' : 'Inactiva' }}
                                </Badge>
                                <button
                                    type="button"
                                    class="text-xs px-2 py-1 rounded-md text-[var(--color-fg-muted)] hover:text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10 transition"
                                    @click="toggleOptionIngredients(opt.id)"
                                >
                                    {{ expandedOption === opt.id ? '▲ Receta' : '▼ Receta' }}
                                    <span v-if="opt.ingredient_lines.length" class="ml-1 text-[var(--color-primary)]">({{ opt.ingredient_lines.length }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="text-[var(--color-fg-dim)] hover:text-[var(--color-err)] text-xs"
                                    @click="destroyOption(g, opt)"
                                >✕</button>
                            </div>

                            <!-- Ingredient lines sub-panel -->
                            <div v-if="expandedOption === opt.id" class="border-t border-[var(--color-border-faint)] px-4 py-3 bg-[var(--color-surface-up)]/60">
                                <p class="text-[11px] text-[var(--color-fg-dim)] mb-2 uppercase tracking-wider font-medium">Ingredientes que descuenta esta opción</p>

                                <!-- Existing lines -->
                                <div v-if="opt.ingredient_lines.length" class="mb-3 space-y-1">
                                    <div
                                        v-for="line in opt.ingredient_lines"
                                        :key="line.id"
                                        class="flex items-center gap-2 text-xs py-0.5"
                                    >
                                        <span class="flex-1">{{ line.ingredient_name }}</span>
                                        <span class="font-numeric text-[var(--color-fg-muted)]">{{ line.quantity_used }} {{ line.unit }}</span>
                                        <button
                                            type="button"
                                            class="text-[var(--color-fg-dim)] hover:text-[var(--color-err)]"
                                            @click="destroyOptionIngredient(g, opt, line)"
                                        >✕</button>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-[var(--color-fg-dim)] mb-3">Sin ingredientes — esta opción no descuenta stock.</p>

                                <!-- Add ingredient form -->
                                <form @submit.prevent="submitOptionIngredient(g, opt)" class="flex flex-wrap items-end gap-2">
                                    <div class="flex-1 min-w-36">
                                        <label class="field-label">Ingrediente</label>
                                        <select v-model="ingFormFor(opt.id).ingredient_id" class="field-input" required>
                                            <option value="" disabled>Seleccioná...</option>
                                            <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                                {{ ing.name }} ({{ ing.unit }})
                                            </option>
                                        </select>
                                    </div>
                                    <div class="w-52">
                                        <label class="field-label">
                                            Cantidad
                                            <span v-if="ingFormFor(opt.id).ingredient_id" class="font-semibold text-[var(--color-primary)]">
                                                ({{ unitFor(ingFormFor(opt.id).ingredient_id) }} — {{ UNIT_NAMES[unitFor(ingFormFor(opt.id).ingredient_id)] }})
                                            </span>
                                        </label>
                                        <input
                                            v-model="ingFormFor(opt.id).quantity_used"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            placeholder="0.00"
                                            class="field-input"
                                            :class="warningFor(ingFormFor(opt.id).ingredient_id, ingFormFor(opt.id).quantity_used)
                                                ? 'border-amber-400 focus:ring-amber-400/40' : ''"
                                            required
                                        />
                                        <p v-if="warningFor(ingFormFor(opt.id).ingredient_id, ingFormFor(opt.id).quantity_used)"
                                           class="mt-1 flex items-start gap-1 text-[11px] text-amber-500 leading-tight">
                                            <span class="shrink-0">⚠</span>
                                            {{ warningFor(ingFormFor(opt.id).ingredient_id, ingFormFor(opt.id).quantity_used) }}
                                        </p>
                                        <p v-else-if="hintFor(ingFormFor(opt.id).ingredient_id)"
                                           class="mt-1 text-[11px] text-[var(--color-fg-dim)] leading-tight">
                                            {{ hintFor(ingFormFor(opt.id).ingredient_id) }}
                                        </p>
                                        <UnitConverter
                                            v-if="ingFormFor(opt.id).ingredient_id"
                                            :target-unit="unitFor(ingFormFor(opt.id).ingredient_id)"
                                            :model-value="ingFormFor(opt.id).quantity_used"
                                            @update:model-value="ingFormFor(opt.id).quantity_used = $event"
                                        />
                                    </div>
                                    <Button size="sm" type="submit" :loading="ingFormFor(opt.id).processing" class="mb-0.5">+ Agregar</Button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-xs text-[var(--color-fg-dim)] mb-4">Sin opciones todavía.</p>

                    <!-- Add option form -->
                    <form @submit.prevent="submitOption(g.id)" class="flex items-end gap-3 mt-2">
                        <div class="flex-1">
                            <label class="field-label">Nueva opción</label>
                            <input v-model="optionFormFor(g.id).name" class="field-input" placeholder="Ej. Término medio" required />
                        </div>
                        <div class="w-28">
                            <label class="field-label">+Precio (Q)</label>
                            <input v-model="optionFormFor(g.id).price_delta" type="number" step="0.01" class="field-input" />
                        </div>
                        <Button size="sm" type="submit">Agregar</Button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.field-label { @apply block text-xs font-medium text-[var(--color-fg-muted)] mb-1; }
.field-input {
    @apply w-full h-10 px-3 rounded-lg text-sm
           bg-[var(--color-surface)] border border-[var(--color-border)]
           text-[var(--color-fg)]
           focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40;
}
.field-error { @apply text-xs text-[var(--color-err)] mt-1; }
</style>
