<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    areas: { type: Array, required: true },
});

const form    = ref({ name: '', display_order: 0 });
const editing = ref(null);

function openEdit(area) {
    editing.value = area.id;
    form.value = { name: area.name, display_order: area.display_order, active: area.active };
}

function cancelEdit() { editing.value = null; form.value = { name: '', display_order: 0 }; }

function save() {
    if (editing.value) {
        router.patch(`/admin/areas/${editing.value}`, form.value, { preserveScroll: true, onSuccess: cancelEdit });
    } else {
        router.post('/admin/areas', form.value, { preserveScroll: true, onSuccess: () => { form.value = { name: '', display_order: 0 }; } });
    }
}

function deactivate(area) {
    if (!confirm(`¿Desactivar área "${area.name}"?`)) return;
    router.delete(`/admin/areas/${area.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Áreas — Admin" />
    <AppLayout title="Áreas">
        <div class="grid lg:grid-cols-[1fr_340px] gap-6">

            <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                        <tr>
                            <th class="table-th">Nombre</th>
                            <th class="table-th text-center">Mesas</th>
                            <th class="table-th text-center">Orden</th>
                            <th class="table-th">Estado</th>
                            <th class="table-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border-faint)]">
                        <tr v-if="!areas.length">
                            <td colspan="5" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">Sin áreas.</td>
                        </tr>
                        <tr v-for="area in areas" :key="area.id" class="hover:bg-[var(--color-surface)]/50" :class="{ 'opacity-50': !area.active }">
                            <td class="table-td font-medium">{{ area.name }}</td>
                            <td class="table-td text-center font-numeric">{{ area.tables_count }}</td>
                            <td class="table-td text-center font-numeric">{{ area.display_order }}</td>
                            <td class="table-td">
                                <Badge :tone="area.active ? 'ok' : 'neutral'" size="sm">{{ area.active ? 'Activa' : 'Inactiva' }}</Badge>
                            </td>
                            <td class="table-td text-right">
                                <div class="flex gap-1 justify-end">
                                    <Button variant="ghost" size="sm" @click="openEdit(area)">Editar</Button>
                                    <Button v-if="area.active" variant="ghost" size="sm" @click="deactivate(area)">Desactivar</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5">
                <h2 class="text-sm font-medium uppercase tracking-widest text-[var(--color-fg-dim)] mb-4">
                    {{ editing ? 'Editar área' : 'Nueva área' }}
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input v-model="form.name" type="text" class="form-input" placeholder="Salón principal" />
                    </div>
                    <div>
                        <label class="form-label">Orden de visualización</label>
                        <input v-model.number="form.display_order" type="number" min="0" class="form-input" />
                    </div>
                    <div v-if="editing" class="flex items-center gap-2">
                        <input id="area-active" v-model="form.active" type="checkbox" class="rounded" />
                        <label for="area-active" class="text-sm">Activa</label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button class="flex-1" @click="save">{{ editing ? 'Guardar' : 'Crear área' }}</Button>
                        <Button v-if="editing" variant="ghost" @click="cancelEdit">Cancelar</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";
.table-th { @apply text-left px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs; }
.table-td { @apply px-4 py-3; }
.form-label { @apply block text-xs text-[var(--color-fg-muted)] mb-1; }
.form-input { @apply w-full rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-bg)] px-3 py-2 text-sm; }
</style>
