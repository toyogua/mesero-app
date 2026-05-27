<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import UnitConverter from '@/Components/UI/UnitConverter.vue';

const props = defineProps({
    menuItem: { type: Object, required: true },
    recipe: { type: Array, default: () => [] },
    ingredients: { type: Array, default: () => [] },
    modifier_groups: { type: Array, default: () => [] },
});

const activeTab = ref('recipe');

// ── Recipe (BOM) ─────────────────────────────────────────────────────────────
const lines = ref(
    props.recipe.map((r) => ({
        ingredient_id: r.ingredient_id,
        quantity_used: r.quantity_used,
    })),
);

function addLine() { lines.value.push({ ingredient_id: '', quantity_used: '' }); }
function removeLine(i) { lines.value.splice(i, 1); }

const ingredientMap = computed(() =>
    Object.fromEntries(props.ingredients.map((i) => [i.id, i])),
);

function unitFor(id) { return ingredientMap.value[id]?.unit ?? ''; }
function stockFor(id) {
    const i = ingredientMap.value[id];
    return i ? `${i.quantity_on_hand} ${i.unit}` : '';
}

// ── Validaciones y hints de unidad ───────────────────────────────────────────
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

function hintFor(id) {
    return CONVERSION_HINTS[unitFor(id)] ?? null;
}

function warningFor(id, qty) {
    const unit  = unitFor(id);
    const value = parseFloat(qty);
    if (!unit || isNaN(value) || value <= 0) return null;

    const max = WARN_THRESHOLDS[unit];
    if (max && value > max) {
        return `${value} ${unit} por porción parece muy alto. ¿Olvidaste convertir unidades?`;
    }
    if (unit === 'unit' && !Number.isInteger(value)) {
        return 'La unidad "unit" debería ser un número entero (1, 2, 3…).';
    }
    return null;
}

const recipeForm = useForm({ lines: [] });
const recipeSaved = ref(false);

function submitRecipe() {
    recipeForm.lines = lines.value.filter((l) => l.ingredient_id && l.quantity_used > 0);
    recipeForm.put(`/admin/menu-items/${props.menuItem.id}/recipe`, {
        onSuccess: () => { recipeSaved.value = true; setTimeout(() => (recipeSaved.value = false), 2000); },
    });
}

// ── Modifiers ─────────────────────────────────────────────────────────────────
const selectedGroups  = ref(props.modifier_groups.filter((g) => g.assigned).map((g) => g.id));
const modifierSaving  = ref(false);
const modifierSaved   = ref(false);


function submitModifiers() {
    modifierSaving.value = true;
    router.post(
        `/admin/menu-items/${props.menuItem.id}/modifiers`,
        { group_ids: [...selectedGroups.value] },
        {
            preserveState: false,
            onSuccess: () => {
                modifierSaved.value = true;
                setTimeout(() => (modifierSaved.value = false), 2000);
            },
            onFinish: () => { modifierSaving.value = false; },
        },
    );
}
</script>

<template>
    <Head :title="`Config: ${menuItem.name}`" />
    <AppLayout :title="`${menuItem.name}`">
        <template #actions>
            <div class="flex items-center gap-3">
                <Link href="/admin/modifier-groups" class="text-xs text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] underline">
                    Gestionar grupos
                </Link>
                <Link href="/admin/ingredients" class="text-xs text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] underline">
                    Ingredientes
                </Link>
            </div>
        </template>

        <div class="max-w-2xl mx-auto">
            <!-- Tabs -->
            <div class="flex gap-1 mb-6 bg-[var(--color-surface)] p-1 rounded-xl border border-[var(--color-border-faint)]">
                <button
                    v-for="tab in [{ id: 'recipe', label: 'Receta (BOM)' }, { id: 'modifiers', label: 'Modificadores' }]"
                    :key="tab.id"
                    type="button"
                    class="flex-1 h-9 rounded-lg text-sm font-medium transition"
                    :class="activeTab === tab.id
                        ? 'bg-[var(--color-fg)] text-[var(--color-bg)]'
                        : 'text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]'"
                    @click="activeTab = tab.id"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── BOM tab ── -->
            <div v-if="activeTab === 'recipe'">
                <p class="text-sm text-[var(--color-fg-muted)] mb-5">
                    Qué ingredientes consume <strong>{{ menuItem.name }}</strong> por unidad vendida.
                    El stock se descuenta automáticamente al marcar como servido.
                </p>

                <div class="space-y-3 mb-6">
                    <div v-for="(line, i) in lines" :key="i" class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="field-label">Ingrediente</label>
                            <select v-model="line.ingredient_id" class="field-input">
                                <option value="" disabled>Seleccioná...</option>
                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                    {{ ing.name }} ({{ stockFor(ing.id) }})
                                </option>
                            </select>
                        </div>
                        <div class="w-52">
                            <label class="field-label">
                                Cantidad
                                <span v-if="line.ingredient_id" class="font-semibold text-[var(--color-primary)]">
                                    ({{ unitFor(line.ingredient_id) }} — {{ UNIT_NAMES[unitFor(line.ingredient_id)] }})
                                </span>
                            </label>
                            <input
                                v-model="line.quantity_used"
                                type="number"
                                step="0.0001"
                                min="0.0001"
                                placeholder="0.00"
                                class="field-input"
                                :class="warningFor(line.ingredient_id, line.quantity_used)
                                    ? 'border-amber-400 focus:ring-amber-400/40'
                                    : ''"
                            />
                            <p v-if="warningFor(line.ingredient_id, line.quantity_used)"
                               class="mt-1 flex items-start gap-1 text-[11px] text-amber-500 leading-tight">
                                <span class="shrink-0">⚠</span>
                                {{ warningFor(line.ingredient_id, line.quantity_used) }}
                            </p>
                            <p v-else-if="hintFor(line.ingredient_id)"
                               class="mt-1 text-[11px] text-[var(--color-fg-dim)] leading-tight">
                                {{ hintFor(line.ingredient_id) }}
                            </p>
                            <UnitConverter
                                v-if="line.ingredient_id"
                                :target-unit="unitFor(line.ingredient_id)"
                                v-model="line.quantity_used"
                            />
                        </div>
                        <button type="button" class="h-10 w-10 flex items-center justify-center rounded-lg text-[var(--color-fg-muted)] hover:text-[var(--color-err)] hover:bg-[var(--color-err)]/10 transition mb-0.5" @click="removeLine(i)">✕</button>
                    </div>

                    <button type="button" class="w-full h-11 rounded-xl border border-dashed border-[var(--color-border)] text-sm text-[var(--color-fg-muted)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition" @click="addLine">
                        + Agregar ingrediente
                    </button>
                </div>

                <div class="flex items-center justify-between">
                    <Link href="/admin/ingredients" class="text-sm text-[var(--color-fg-muted)] hover:underline">← Volver</Link>
                    <div class="flex items-center gap-3">
                        <span v-if="recipeSaved" class="text-sm text-[var(--color-ok)] font-medium">✓ Guardado</span>
                        <Button :loading="recipeForm.processing" @click="submitRecipe">Guardar receta</Button>
                    </div>
                </div>
            </div>

            <!-- ── Modifiers tab ── -->
            <div v-else>
                <p class="text-sm text-[var(--color-fg-muted)] mb-5">
                    Seleccioná qué grupos de modificadores aplican a <strong>{{ menuItem.name }}</strong>.
                    Al agregar el plato a una comanda, el mesero verá estos grupos.
                </p>

                <div v-if="!modifier_groups.length" class="py-12 text-center text-[var(--color-fg-muted)] text-sm">
                    Sin grupos creados. <Link href="/admin/modifier-groups" class="underline">Creá uno primero →</Link>
                </div>

                <div v-else class="space-y-2 mb-6">
                    <label
                        v-for="g in modifier_groups"
                        :key="g.id"
                        class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                        :class="selectedGroups.includes(g.id)
                            ? 'border-[var(--color-primary)] bg-[color-mix(in_oklch,var(--color-primary)_8%,var(--color-surface))]'
                            : 'border-[var(--color-border-faint)] bg-[var(--color-surface)] hover:border-[var(--color-border)]'"
                    >
                        <input
                            type="checkbox"
                            :value="g.id"
                            v-model="selectedGroups"
                        />
                        <span class="flex-1 text-sm font-medium">{{ g.name }}</span>
                        <Badge :tone="g.min_selections > 0 ? 'warn' : 'neutral'" size="sm">
                            {{ g.min_selections > 0 ? 'Requerido' : 'Opcional' }}
                        </Badge>
                        <Badge tone="neutral" size="sm">
                            <template v-if="g.max_selections === 1">Una opción</template>
                            <template v-else-if="g.min_selections === g.max_selections">Exactamente {{ g.min_selections }}</template>
                            <template v-else-if="g.max_selections === null">Mín. {{ g.min_selections }}</template>
                            <template v-else>{{ g.min_selections }}–{{ g.max_selections }} opciones</template>
                        </Badge>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <span v-if="modifierSaved" class="text-sm text-[var(--color-ok)] font-medium">✓ Guardado</span>
                    <Button :loading="modifierSaving" @click="submitModifiers">Guardar modificadores</Button>
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
</style>
