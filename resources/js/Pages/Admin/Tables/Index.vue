<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    areas:     { type: Array, required: true },
    area_list: { type: Array, required: true },
});

const form    = ref({ area_id: props.area_list[0]?.id ?? '', name: '', capacity: 4 });
const editing = ref(null);

function openEdit(table) {
    editing.value = table.id;
    form.value = { name: table.name, capacity: table.capacity, active: table.active };
}

function cancelEdit() { editing.value = null; }

function save() {
    if (editing.value) {
        router.patch(`/admin/tables/${editing.value}`, form.value, { preserveScroll: true, onSuccess: cancelEdit });
    } else {
        router.post('/admin/tables', form.value, { preserveScroll: true, onSuccess: () => { form.value = { area_id: props.area_list[0]?.id ?? '', name: '', capacity: 4 }; } });
    }
}

function deactivate(table) {
    if (table.occupied) { alert('La mesa tiene una comanda abierta.'); return; }
    if (!confirm(`¿Desactivar mesa "${table.name}"?`)) return;
    router.delete(`/admin/tables/${table.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mesas — Admin" />
    <AppLayout title="Mesas">
        <div class="grid lg:grid-cols-[1fr_340px] gap-6">

            <div class="space-y-4">
                <div v-for="area in areas" :key="area.id" class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                    <div class="px-4 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border-faint)] flex items-center justify-between">
                        <span class="font-medium text-sm">{{ area.name }}</span>
                        <Badge :tone="area.active ? 'ok' : 'neutral'" size="sm">{{ area.active ? 'Activa' : 'Inactiva' }}</Badge>
                    </div>
                    <div v-if="!area.tables.length" class="px-4 py-6 text-center text-sm text-[var(--color-fg-dim)]">Sin mesas en esta área.</div>
                    <table v-else class="w-full text-sm">
                        <tbody class="divide-y divide-[var(--color-border-faint)]">
                            <tr v-for="table in area.tables" :key="table.id" class="hover:bg-[var(--color-surface)]/50" :class="{ 'opacity-50': !table.active }">
                                <td class="px-4 py-3 font-medium">{{ table.name }}</td>
                                <td class="px-4 py-3 text-[var(--color-fg-muted)] text-xs">{{ table.capacity }} personas</td>
                                <td class="px-4 py-3">
                                    <Badge v-if="table.occupied" tone="warn" size="sm">Ocupada</Badge>
                                    <Badge v-else-if="!table.active" tone="neutral" size="sm">Inactiva</Badge>
                                    <Badge v-else tone="ok" size="sm">Libre</Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex gap-1 justify-end">
                                        <Button variant="ghost" size="sm" @click="openEdit(table)">Editar</Button>
                                        <Button v-if="table.active && !table.occupied" variant="ghost" size="sm" @click="deactivate(table)">Desactivar</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5 h-fit">
                <h2 class="text-sm font-medium uppercase tracking-widest text-[var(--color-fg-dim)] mb-4">
                    {{ editing ? 'Editar mesa' : 'Nueva mesa' }}
                </h2>
                <div class="space-y-3">
                    <div v-if="!editing">
                        <label class="form-label">Área *</label>
                        <select v-model="form.area_id" class="form-input">
                            <option v-for="a in area_list" :key="a.id" :value="a.id">{{ a.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Nombre / número *</label>
                        <input v-model="form.name" type="text" class="form-input" placeholder="Mesa 1" />
                    </div>
                    <div>
                        <label class="form-label">Capacidad</label>
                        <input v-model.number="form.capacity" type="number" min="1" max="50" class="form-input" />
                    </div>
                    <div v-if="editing" class="flex items-center gap-2">
                        <input id="table-active" v-model="form.active" type="checkbox" class="rounded" />
                        <label for="table-active" class="text-sm">Activa</label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button class="flex-1" @click="save">{{ editing ? 'Guardar' : 'Crear mesa' }}</Button>
                        <Button v-if="editing" variant="ghost" @click="cancelEdit">Cancelar</Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.form-label { @apply block text-xs text-[var(--color-fg-muted)] mb-1; }
.form-input { @apply w-full rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-bg)] px-3 py-2 text-sm; }
</style>
