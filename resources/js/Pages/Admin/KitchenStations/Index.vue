<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    stations: { type: Array, required: true },
});

const blank   = () => ({ name: '', code: '', display_order: 0 });
const form    = ref(blank());
const editing = ref(null);

function openEdit(s) {
    editing.value = s.id;
    form.value = { name: s.name, code: s.code, display_order: s.display_order, active: s.active };
}

function cancelEdit() {
    editing.value = null;
    form.value = blank();
}

function save() {
    if (editing.value) {
        router.patch(`/admin/kitchen-stations/${editing.value}`, form.value, {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
    } else {
        router.post('/admin/kitchen-stations', form.value, {
            preserveScroll: true,
            onSuccess: () => { form.value = blank(); },
        });
    }
}

function deactivate(s) {
    if (!confirm(`¿Desactivar estación "${s.name}"?`)) return;
    router.delete(`/admin/kitchen-stations/${s.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Estaciones de cocina — Admin" />
    <AppLayout title="Estaciones de cocina">
        <div class="grid lg:grid-cols-[1fr_340px] gap-6">

            <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                        <tr>
                            <th class="table-th">Nombre</th>
                            <th class="table-th">Código</th>
                            <th class="table-th text-center">Platos</th>
                            <th class="table-th text-center">Orden</th>
                            <th class="table-th">Estado</th>
                            <th class="table-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border-faint)]">
                        <tr v-if="!stations.length">
                            <td colspan="6" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">Sin estaciones.</td>
                        </tr>
                        <tr
                            v-for="s in stations"
                            :key="s.id"
                            class="hover:bg-[var(--color-surface)]/50"
                            :class="{ 'opacity-50': !s.active }"
                        >
                            <td class="table-td font-medium">{{ s.name }}</td>
                            <td class="table-td font-mono text-xs text-[var(--color-fg-muted)]">{{ s.code }}</td>
                            <td class="table-td text-center font-numeric">{{ s.menu_items_count }}</td>
                            <td class="table-td text-center font-numeric">{{ s.display_order }}</td>
                            <td class="table-td">
                                <Badge :tone="s.active ? 'ok' : 'neutral'" size="sm">
                                    {{ s.active ? 'Activa' : 'Inactiva' }}
                                </Badge>
                            </td>
                            <td class="table-td text-right">
                                <div class="flex gap-1 justify-end">
                                    <Button variant="ghost" size="sm" @click="openEdit(s)">Editar</Button>
                                    <Button v-if="s.active" variant="ghost" size="sm" @click="deactivate(s)">Desactivar</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5">
                <h2 class="text-sm font-medium uppercase tracking-widest text-[var(--color-fg-dim)] mb-4">
                    {{ editing ? 'Editar estación' : 'Nueva estación' }}
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input v-model="form.name" type="text" class="form-input" placeholder="Cocina caliente" />
                    </div>
                    <div>
                        <label class="form-label">Código (slug único)</label>
                        <input v-model="form.code" type="text" class="form-input font-mono" placeholder="cocina-caliente (auto si vacío)" />
                    </div>
                    <div>
                        <label class="form-label">Orden de visualización</label>
                        <input v-model.number="form.display_order" type="number" min="0" class="form-input" />
                    </div>
                    <div v-if="editing" class="flex items-center gap-2">
                        <input id="station-active" v-model="form.active" type="checkbox" class="rounded" />
                        <label for="station-active" class="text-sm">Activa</label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button class="flex-1" @click="save">{{ editing ? 'Guardar' : 'Crear estación' }}</Button>
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
