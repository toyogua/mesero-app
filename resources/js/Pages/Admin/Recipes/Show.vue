<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    menuItem: { type: Object, required: true },
    recipe: { type: Array, default: () => [] },
    ingredients: { type: Array, default: () => [] },
});

// ── Local lines — initialized from server ────────────────────────────────────
const lines = ref(
    props.recipe.map((r) => ({
        ingredient_id: r.ingredient_id,
        quantity_used: r.quantity_used,
    })),
);

function addLine() {
    lines.value.push({ ingredient_id: '', quantity_used: '' });
}

function removeLine(index) {
    lines.value.splice(index, 1);
}

// ── Lookup ingredient metadata for display ───────────────────────────────────
const ingredientMap = computed(() =>
    Object.fromEntries(props.ingredients.map((i) => [i.id, i])),
);

function unitFor(id) {
    return ingredientMap.value[id]?.unit ?? '';
}

function stockFor(id) {
    const i = ingredientMap.value[id];
    return i ? `${i.quantity_on_hand} ${i.unit}` : '';
}

// ── Submit ───────────────────────────────────────────────────────────────────
const form = useForm({ lines: [] });
const saved = ref(false);

function submit() {
    form.lines = lines.value.filter((l) => l.ingredient_id && l.quantity_used > 0);
    form.put(`/admin/menu-items/${props.menuItem.id}/recipe`, {
        onSuccess: () => {
            saved.value = true;
            setTimeout(() => (saved.value = false), 2000);
        },
    });
}
</script>

<template>
    <Head :title="`Receta: ${menuItem.name}`" />
    <AppLayout :title="`Receta — ${menuItem.name}`">
        <template #actions>
            <Link href="/admin/ingredients" class="text-xs text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] underline">
                Gestionar ingredientes
            </Link>
        </template>

        <div class="max-w-2xl mx-auto">
            <p class="text-sm text-[var(--color-fg-muted)] mb-6">
                Definí qué ingredientes consume <strong>{{ menuItem.name }}</strong> por unidad vendida.
                Cuando el mesero marque el ítem como "servido", el stock se descuenta automáticamente.
            </p>

            <!-- Lines -->
            <div class="space-y-3 mb-6">
                <div
                    v-for="(line, i) in lines"
                    :key="i"
                    class="flex items-end gap-3"
                >
                    <div class="flex-1">
                        <label class="field-label">Ingrediente</label>
                        <select v-model="line.ingredient_id" class="field-input">
                            <option value="" disabled>Seleccioná...</option>
                            <option
                                v-for="ing in ingredients"
                                :key="ing.id"
                                :value="ing.id"
                            >
                                {{ ing.name }} ({{ stockFor(ing.id) }})
                            </option>
                        </select>
                    </div>

                    <div class="w-36">
                        <label class="field-label">
                            Cantidad
                            <span v-if="line.ingredient_id" class="text-[var(--color-fg-dim)]">
                                ({{ unitFor(line.ingredient_id) }})
                            </span>
                        </label>
                        <input
                            v-model="line.quantity_used"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            class="field-input"
                            placeholder="0.00"
                        />
                    </div>

                    <button
                        type="button"
                        class="h-10 w-10 flex items-center justify-center rounded-lg text-[var(--color-fg-muted)] hover:text-[var(--color-err)] hover:bg-[var(--color-err)]/10 transition mb-0.5"
                        @click="removeLine(i)"
                    >
                        ✕
                    </button>
                </div>

                <button
                    type="button"
                    class="w-full h-11 rounded-xl border border-dashed border-[var(--color-border)] text-sm text-[var(--color-fg-muted)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition"
                    @click="addLine"
                >
                    + Agregar ingrediente
                </button>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between">
                <Link href="/admin/ingredients" class="text-sm text-[var(--color-fg-muted)] hover:underline">
                    ← Volver
                </Link>

                <div class="flex items-center gap-3">
                    <span
                        v-if="saved"
                        class="text-sm text-[var(--color-ok)] font-medium"
                    >
                        ✓ Guardado
                    </span>
                    <Button :loading="form.processing" @click="submit">
                        Guardar receta
                    </Button>
                </div>
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
</style>
