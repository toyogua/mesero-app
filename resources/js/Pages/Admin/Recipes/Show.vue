<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

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

const recipeForm = useForm({ lines: [] });
const recipeSaved = ref(false);

function submitRecipe() {
    recipeForm.lines = lines.value.filter((l) => l.ingredient_id && l.quantity_used > 0);
    recipeForm.put(`/admin/menu-items/${props.menuItem.id}/recipe`, {
        onSuccess: () => { recipeSaved.value = true; setTimeout(() => (recipeSaved.value = false), 2000); },
    });
}

// ── Modifiers ─────────────────────────────────────────────────────────────────
const selectedGroups = ref(
    props.modifier_groups.filter((g) => g.assigned).map((g) => g.id),
);

const modifierForm = useForm({ group_ids: [] });
const modifierSaved = ref(false);

function toggleGroup(id) {
    const idx = selectedGroups.value.indexOf(id);
    if (idx === -1) selectedGroups.value.push(id);
    else selectedGroups.value.splice(idx, 1);
}

function submitModifiers() {
    modifierForm.group_ids = selectedGroups.value;
    modifierForm.put(`/admin/menu-items/${props.menuItem.id}/modifiers`, {
        onSuccess: () => { modifierSaved.value = true; setTimeout(() => (modifierSaved.value = false), 2000); },
    });
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
                        <div class="w-36">
                            <label class="field-label">
                                Cantidad
                                <span v-if="line.ingredient_id" class="text-[var(--color-fg-dim)]">({{ unitFor(line.ingredient_id) }})</span>
                            </label>
                            <input v-model="line.quantity_used" type="number" step="0.0001" min="0.0001" class="field-input" placeholder="0.00" />
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
                        @click="toggleGroup(g.id)"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedGroups.includes(g.id)"
                            class="pointer-events-none"
                            @click.prevent
                        />
                        <span class="flex-1 text-sm font-medium">{{ g.name }}</span>
                        <Badge :tone="g.required ? 'warn' : 'neutral'" size="sm">
                            {{ g.required ? 'Requerido' : 'Opcional' }}
                        </Badge>
                        <Badge tone="neutral" size="sm">
                            {{ g.selection_type === 'single' ? 'Una opción' : 'Múltiple' }}
                        </Badge>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <span v-if="modifierSaved" class="text-sm text-[var(--color-ok)] font-medium">✓ Guardado</span>
                    <Button :loading="modifierForm.processing" @click="submitModifiers">Guardar modificadores</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.field-label { @apply block text-xs font-medium text-[var(--color-fg-muted)] mb-1; }
.field-input {
    @apply w-full h-10 px-3 rounded-lg text-sm
           bg-[var(--color-surface)] border border-[var(--color-border)]
           text-[var(--color-fg)]
           focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40;
}
</style>
