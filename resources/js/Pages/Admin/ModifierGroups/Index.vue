<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
});

// ── New group form ────────────────────────────────────────────────────────────
const showForm = ref(false);
const form = useForm({ name: '', selection_type: 'single', required: false });

function submitGroup() {
    form.post('/admin/modifier-groups', {
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
}

// ── Inline group edit ─────────────────────────────────────────────────────────
const editingGroup = ref(null);
const editGroupForm = useForm({ name: '', selection_type: 'single', required: false });

function startEditGroup(g) {
    editingGroup.value = g.id;
    editGroupForm.name = g.name;
    editGroupForm.selection_type = g.selection_type;
    editGroupForm.required = g.required;
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

function currency(v) {
    if (!v || v === 0) return '—';
    return `+Q ${Number(v).toFixed(2)}`;
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
            <form @submit.prevent="submitGroup" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-48">
                    <label class="field-label">Nombre</label>
                    <input v-model="form.name" class="field-input" placeholder="Ej. Término, Extras" required />
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="field-label">Selección</label>
                    <select v-model="form.selection_type" class="field-input">
                        <option value="single">Una opción</option>
                        <option value="multi">Múltiple</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 cursor-pointer pb-1">
                    <input v-model="form.required" type="checkbox" />
                    <span class="text-sm">Obligatorio</span>
                </label>
                <Button size="sm" type="submit" :loading="form.processing">Guardar</Button>
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
                            <Badge :tone="g.required ? 'warn' : 'neutral'" size="sm" class="ml-2">
                                {{ g.required ? 'Requerido' : 'Opcional' }}
                            </Badge>
                            <Badge tone="neutral" size="sm" class="ml-1">
                                {{ g.selection_type === 'single' ? 'Una opción' : 'Múltiple' }}
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
                        <input v-model="editGroupForm.name" class="field-input flex-1" />
                        <select v-model="editGroupForm.selection_type" class="field-input w-36">
                            <option value="single">Una opción</option>
                            <option value="multi">Múltiple</option>
                        </select>
                        <label class="flex items-center gap-1 cursor-pointer text-sm">
                            <input v-model="editGroupForm.required" type="checkbox" />
                            Requerido
                        </label>
                        <Button variant="ghost" size="sm" @click="editingGroup = null">Cancelar</Button>
                        <Button size="sm" :loading="editGroupForm.processing" @click="submitEditGroup(g)">Guardar</Button>
                    </template>
                </div>

                <!-- Options panel -->
                <div v-if="expanded === g.id" class="border-t border-[var(--color-border-faint)] px-5 py-4 bg-[var(--color-surface-up)]/40">
                    <!-- Existing options -->
                    <div v-if="g.options.length" class="mb-4 space-y-1">
                        <div
                            v-for="opt in g.options"
                            :key="opt.id"
                            class="flex items-center gap-3 text-sm py-1"
                        >
                            <span class="flex-1">{{ opt.name }}</span>
                            <span class="font-numeric text-[var(--color-fg-muted)]">{{ currency(opt.price_delta) }}</span>
                            <Badge :tone="opt.active ? 'ok' : 'neutral'" size="sm">
                                {{ opt.active ? 'Activa' : 'Inactiva' }}
                            </Badge>
                            <button
                                type="button"
                                class="text-[var(--color-fg-dim)] hover:text-[var(--color-err)] text-xs"
                                @click="destroyOption(g, opt)"
                            >✕</button>
                        </div>
                    </div>
                    <p v-else class="text-xs text-[var(--color-fg-dim)] mb-4">Sin opciones todavía.</p>

                    <!-- Add option form -->
                    <form @submit.prevent="submitOption(g.id)" class="flex items-end gap-3">
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
.field-label { @apply block text-xs font-medium text-[var(--color-fg-muted)] mb-1; }
.field-input {
    @apply w-full h-10 px-3 rounded-lg text-sm
           bg-[var(--color-surface)] border border-[var(--color-border)]
           text-[var(--color-fg)]
           focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40;
}
.field-error { @apply text-xs text-[var(--color-err)] mt-1; }
</style>
