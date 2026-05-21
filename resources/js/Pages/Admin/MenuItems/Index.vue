<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    items:    { type: Array,  required: true },
    stations: { type: Array,  required: true },
});

const CATEGORIES = [
    { value: 'entradas',      label: 'Entradas' },
    { value: 'platos_fuertes',label: 'Platos fuertes' },
    { value: 'bebidas',       label: 'Bebidas' },
    { value: 'postres',       label: 'Postres' },
    { value: 'otros',         label: 'Otros' },
];

const blank = () => ({ name: '', description: '', price: '', category: 'platos_fuertes', sku: '', kitchen_station_id: props.stations[0]?.id ?? '', active: true });

const form   = ref(blank());
const editing = ref(null); // item id being edited
const filterCat = ref('all');

const filtered = computed(() =>
    filterCat.value === 'all' ? props.items : props.items.filter(i => i.category === filterCat.value)
);

function openEdit(item) {
    editing.value = item.id;
    form.value = { ...item };
}

function cancelEdit() {
    editing.value = null;
    form.value = blank();
}

function save() {
    if (editing.value) {
        router.patch(`/admin/menu-items/${editing.value}`, form.value, {
            preserveScroll: true, onSuccess: cancelEdit,
        });
    } else {
        router.post('/admin/menu-items', form.value, {
            preserveScroll: true, onSuccess: () => { form.value = blank(); },
        });
    }
}

function deactivate(item) {
    if (!confirm(`¿Desactivar "${item.name}"?`)) return;
    router.delete(`/admin/menu-items/${item.id}`, { preserveScroll: true });
}

function currency(v) { return `Q ${Number(v).toFixed(2)}`; }
</script>

<template>
    <Head title="Menú — Admin" />
    <AppLayout title="Ítems de menú">

        <!-- Filter tabs -->
        <div class="flex gap-2 mb-6 flex-wrap">
            <button
                v-for="opt in [{ value: 'all', label: 'Todos' }, ...CATEGORIES]"
                :key="opt.value"
                type="button"
                class="h-9 px-4 rounded-full text-xs font-medium uppercase tracking-widest transition border"
                :class="filterCat === opt.value
                    ? 'bg-[var(--color-primary)] text-[oklch(15%_0.02_60)] border-transparent'
                    : 'text-[var(--color-fg-muted)] border-[var(--color-border-faint)] hover:border-[var(--color-border)]'"
                @click="filterCat = opt.value"
            >{{ opt.label }}</button>
        </div>

        <div class="grid lg:grid-cols-[1fr_380px] gap-6">

            <!-- Table -->
            <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                        <tr>
                            <th class="table-th">Nombre</th>
                            <th class="table-th">Categoría</th>
                            <th class="table-th">Estación</th>
                            <th class="table-th text-right">Precio</th>
                            <th class="table-th">Estado</th>
                            <th class="table-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--color-border-faint)]">
                        <tr v-if="!filtered.length">
                            <td colspan="6" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">Sin ítems.</td>
                        </tr>
                        <tr
                            v-for="item in filtered" :key="item.id"
                            class="hover:bg-[var(--color-surface)]/50"
                            :class="{ 'opacity-50': !item.active }"
                        >
                            <td class="table-td font-medium">
                                {{ item.name }}
                                <div v-if="item.description" class="text-xs text-[var(--color-fg-dim)] truncate max-w-xs">{{ item.description }}</div>
                            </td>
                            <td class="table-td text-[var(--color-fg-muted)]">{{ item.category }}</td>
                            <td class="table-td text-[var(--color-fg-muted)]">{{ item.station_name }}</td>
                            <td class="table-td text-right font-numeric">{{ currency(item.price) }}</td>
                            <td class="table-td">
                                <Badge :tone="item.active ? 'ok' : 'neutral'" size="sm">{{ item.active ? 'Activo' : 'Inactivo' }}</Badge>
                            </td>
                            <td class="table-td text-right">
                                <div class="flex gap-1 justify-end">
                                    <Button variant="ghost" size="sm" @click="openEdit(item)">Editar</Button>
                                    <Button v-if="item.active" variant="ghost" size="sm" @click="deactivate(item)">Desactivar</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Form -->
            <div class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5">
                <h2 class="text-sm font-medium uppercase tracking-widest text-[var(--color-fg-dim)] mb-4">
                    {{ editing ? 'Editar ítem' : 'Nuevo ítem' }}
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input v-model="form.name" type="text" class="form-input" placeholder="Burger clásica" />
                    </div>
                    <div>
                        <label class="form-label">Descripción</label>
                        <textarea v-model="form.description" class="form-input" rows="2" placeholder="Ingredientes, alérgenos…" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Precio (GTQ) *</label>
                            <input v-model="form.price" type="number" min="0" step="0.01" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">SKU</label>
                            <input v-model="form.sku" type="text" class="form-input" placeholder="BRG-001" />
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Categoría *</label>
                        <select v-model="form.category" class="form-input">
                            <option v-for="c in CATEGORIES" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Estación de cocina *</label>
                        <select v-model="form.kitchen_station_id" class="form-input">
                            <option v-for="s in stations" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div v-if="editing" class="flex items-center gap-2">
                        <input id="active" v-model="form.active" type="checkbox" class="rounded" />
                        <label for="active" class="text-sm">Activo</label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button class="flex-1" @click="save">{{ editing ? 'Guardar cambios' : 'Crear ítem' }}</Button>
                        <Button v-if="editing" variant="ghost" @click="cancelEdit">Cancelar</Button>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>

<style scoped>
.table-th { @apply text-left px-4 py-3 font-medium text-[var(--color-fg-muted)] uppercase tracking-wider text-xs; }
.table-td { @apply px-4 py-3; }
.form-label { @apply block text-xs text-[var(--color-fg-muted)] mb-1; }
.form-input { @apply w-full rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-bg)] px-3 py-2 text-sm; }
</style>
