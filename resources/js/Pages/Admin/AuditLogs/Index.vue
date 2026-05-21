<script setup>
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    logs:     { type: Object, required: true },
    users:    { type: Array,  required: true },
    entities: { type: Array,  required: true },
    filters:  { type: Object, required: true },
});

const entity   = ref(props.filters.entity  ?? '');
const action   = ref(props.filters.action  ?? '');
const userId   = ref(props.filters.user_id ?? '');
const fromDate = ref(props.filters.from);
const toDate   = ref(props.filters.to);

const expanded = ref(new Set());

function toggle(id) {
    expanded.value.has(id) ? expanded.value.delete(id) : expanded.value.add(id);
    expanded.value = new Set(expanded.value); // trigger reactivity
}

function apply() {
    router.get('/admin/audit-logs', {
        entity:  entity.value  || undefined,
        action:  action.value  || undefined,
        user_id: userId.value  || undefined,
        from:    fromDate.value,
        to:      toDate.value,
    }, { preserveScroll: true });
}

function setPreset(days) {
    const to   = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days + 1);
    fromDate.value = from.toISOString().slice(0, 10);
    toDate.value   = to.toISOString().slice(0, 10);
    apply();
}

function fmt(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' });
}

const ACTION_STYLE = {
    created: 'text-green-600 bg-green-50',
    updated: 'text-blue-600 bg-blue-50',
    deleted: 'text-red-500 bg-red-50',
};

const ACTION_LABEL = { created: 'Creó', updated: 'Editó', deleted: 'Eliminó' };
</script>

<template>
    <Head title="Auditoría — Admin" />
    <AppLayout title="Registro de cambios">

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-6 items-end">
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Entidad</label>
                <select v-model="entity"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm"
                    @change="apply">
                    <option value="">Todas</option>
                    <option v-for="e in entities" :key="e" :value="e">{{ e }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Acción</label>
                <select v-model="action"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm"
                    @change="apply">
                    <option value="">Todas</option>
                    <option value="created">Creado</option>
                    <option value="updated">Editado</option>
                    <option value="deleted">Eliminado</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Usuario</label>
                <select v-model="userId"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm"
                    @change="apply">
                    <option value="">Todos</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Desde</label>
                <input v-model="fromDate" type="date"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm" />
            </div>
            <div>
                <label class="block text-xs text-[var(--color-fg-muted)] mb-1">Hasta</label>
                <input v-model="toDate" type="date"
                    class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm" />
            </div>
            <div class="flex gap-2 items-end">
                <Button size="sm" @click="apply">Filtrar</Button>
                <Button variant="ghost" size="sm" @click="setPreset(7)">7 días</Button>
                <Button variant="ghost" size="sm" @click="setPreset(30)">30 días</Button>
            </div>
        </div>

        <!-- Log table -->
        <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-[var(--color-surface)] text-xs uppercase tracking-wide text-[var(--color-fg-muted)]">
                    <tr>
                        <th class="px-4 py-3 text-left">Fecha</th>
                        <th class="px-4 py-3 text-left">Usuario</th>
                        <th class="px-4 py-3 text-left">Acción</th>
                        <th class="px-4 py-3 text-left">Entidad</th>
                        <th class="px-4 py-3 text-left">Registro</th>
                        <th class="px-4 py-3 text-left">Cambios</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border-faint)]">
                    <tr v-if="!logs.data.length">
                        <td colspan="6" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">
                            Sin actividad en el período seleccionado.
                        </td>
                    </tr>
                    <template v-for="log in logs.data" :key="log.id">
                        <tr class="hover:bg-[var(--color-surface)]/50">
                            <td class="px-4 py-3 text-xs text-[var(--color-fg-muted)] whitespace-nowrap">{{ fmt(log.created_at) }}</td>
                            <td class="px-4 py-3 font-medium">{{ log.user }}</td>
                            <td class="px-4 py-3">
                                <span :class="ACTION_STYLE[log.action]"
                                    class="text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ ACTION_LABEL[log.action] ?? log.action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[var(--color-fg-muted)]">{{ log.entity }}</td>
                            <td class="px-4 py-3 font-medium">{{ log.auditable_label ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <button
                                    v-if="log.old_values || log.new_values"
                                    type="button"
                                    class="text-xs text-[var(--color-primary)] hover:underline"
                                    @click="toggle(log.id)"
                                >
                                    {{ expanded.has(log.id) ? 'Ocultar' : 'Ver' }}
                                </button>
                                <span v-else class="text-xs text-[var(--color-fg-dim)]">—</span>
                            </td>
                        </tr>

                        <!-- Expanded diff row -->
                        <tr v-if="expanded.has(log.id)" class="bg-[var(--color-surface)]/60">
                            <td colspan="6" class="px-6 py-3">
                                <div class="grid md:grid-cols-2 gap-4 text-xs font-mono">
                                    <!-- Created: show new values only -->
                                    <template v-if="log.action === 'created'">
                                        <div>
                                            <div class="text-[var(--color-fg-dim)] mb-1 uppercase tracking-widest text-[10px]">Valores iniciales</div>
                                            <div v-for="(v, k) in log.new_values" :key="k" class="flex gap-2 py-0.5">
                                                <span class="text-[var(--color-fg-muted)] w-32 shrink-0">{{ k }}</span>
                                                <span class="text-green-700">{{ v }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Updated: side-by-side old → new -->
                                    <template v-else-if="log.action === 'updated'">
                                        <div>
                                            <div class="text-[var(--color-fg-dim)] mb-1 uppercase tracking-widest text-[10px]">Antes</div>
                                            <div v-for="(v, k) in log.old_values" :key="k" class="flex gap-2 py-0.5">
                                                <span class="text-[var(--color-fg-muted)] w-32 shrink-0">{{ k }}</span>
                                                <span class="text-red-600 line-through">{{ v }}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-[var(--color-fg-dim)] mb-1 uppercase tracking-widest text-[10px]">Después</div>
                                            <div v-for="(v, k) in log.new_values" :key="k" class="flex gap-2 py-0.5">
                                                <span class="text-[var(--color-fg-muted)] w-32 shrink-0">{{ k }}</span>
                                                <span class="text-green-700">{{ v }}</span>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Deleted: show old values -->
                                    <template v-else-if="log.action === 'deleted'">
                                        <div>
                                            <div class="text-[var(--color-fg-dim)] mb-1 uppercase tracking-widest text-[10px]">Valores al eliminar</div>
                                            <div v-for="(v, k) in log.old_values" :key="k" class="flex gap-2 py-0.5">
                                                <span class="text-[var(--color-fg-muted)] w-32 shrink-0">{{ k }}</span>
                                                <span class="text-red-600">{{ v }}</span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-[var(--color-fg-muted)]">{{ logs.from }}–{{ logs.to }} de {{ logs.total }}</span>
            <div class="flex gap-1">
                <Link v-for="link in logs.links" :key="link.label" :href="link.url ?? '#'"
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
