<script setup>
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';

const props = defineProps({
    users:   { type: Object, required: true },
    roles:   { type: Array,  required: true },
    filters: { type: Object, required: true },
});

const ROLE_LABEL = {
    admin:   'Admin',
    waiter:  'Mesero',
    kitchen: 'Cocina',
    cashier: 'Caja',
};

const blank = () => ({ name: '', role: 'waiter', pin: '', email: '', password: '' });
const form    = ref(blank());
const editing = ref(null);

const search   = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');

const usesPin = () => ['waiter', 'kitchen', 'cashier'].includes(form.value.role);

function navigate(params = {}) {
    router.get('/admin/users', {
        search: search.value || undefined,
        role:   roleFilter.value || undefined,
        ...params,
    }, { preserveScroll: true });
}

function applySearch() { navigate({ page: 1 }); }

function openEdit(user) {
    editing.value = user.id;
    form.value = { name: user.name, role: user.role, pin: '', email: user.email ?? '', password: '', active: user.active };
}

function cancelEdit() { editing.value = null; form.value = blank(); }

function save() {
    if (editing.value) {
        router.patch(`/admin/users/${editing.value}`, form.value, { preserveScroll: true, onSuccess: cancelEdit });
    } else {
        router.post('/admin/users', form.value, { preserveScroll: true, onSuccess: () => { form.value = blank(); } });
    }
}

function deactivate(user) {
    if (!confirm(`¿Desactivar usuario "${user.name}"?`)) return;
    router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios — Admin" />
    <AppLayout title="Usuarios">

        <!-- Filter bar -->
        <div class="flex flex-wrap gap-3 mb-6 items-center">
            <input
                v-model="search"
                type="search"
                placeholder="Buscar por nombre…"
                class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm w-52"
                @keydown.enter="applySearch"
            />
            <select
                v-model="roleFilter"
                class="h-9 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] text-sm"
                @change="navigate({ page: 1 })"
            >
                <option value="">Todos los roles</option>
                <option v-for="r in roles" :key="r" :value="r">{{ ROLE_LABEL[r] || r }}</option>
            </select>
            <Button size="sm" variant="ghost" @click="applySearch">Buscar</Button>
        </div>

        <div class="grid lg:grid-cols-[1fr_360px] gap-6">

            <div>
                <div class="rounded-2xl border border-[var(--color-border-faint)] overflow-hidden mb-4">
                    <table class="w-full text-sm">
                        <thead class="bg-[var(--color-surface)] border-b border-[var(--color-border-faint)]">
                            <tr>
                                <th class="table-th">Nombre</th>
                                <th class="table-th">Rol</th>
                                <th class="table-th">Acceso</th>
                                <th class="table-th">Estado</th>
                                <th class="table-th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-border-faint)]">
                            <tr v-if="!users.data.length">
                                <td colspan="5" class="px-4 py-10 text-center text-[var(--color-fg-dim)]">Sin usuarios.</td>
                            </tr>
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-[var(--color-surface)]/50" :class="{ 'opacity-50': !user.active }">
                                <td class="table-td font-medium">{{ user.name }}</td>
                                <td class="table-td">
                                    <Badge :tone="user.role === 'admin' ? 'primary' : 'neutral'" size="sm">{{ ROLE_LABEL[user.role] || user.role }}</Badge>
                                </td>
                                <td class="table-td text-xs text-[var(--color-fg-muted)]">
                                    <span v-if="user.has_pin">PIN configurado</span>
                                    <span v-else-if="user.email && !user.email.endsWith('@local')">{{ user.email }}</span>
                                    <span v-else class="text-[var(--color-err)]">Sin acceso</span>
                                </td>
                                <td class="table-td">
                                    <Badge :tone="user.active ? 'ok' : 'neutral'" size="sm">{{ user.active ? 'Activo' : 'Inactivo' }}</Badge>
                                </td>
                                <td class="table-td text-right">
                                    <div class="flex gap-1 justify-end">
                                        <Button variant="ghost" size="sm" @click="openEdit(user)">Editar</Button>
                                        <Button v-if="user.active" variant="ghost" size="sm" @click="deactivate(user)">Desactivar</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="flex items-center justify-between text-sm">
                    <span class="text-[var(--color-fg-muted)]">{{ users.from }}–{{ users.to }} de {{ users.total }}</span>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in users.links" :key="link.label"
                            :href="link.url ?? '#'"
                            :class="[
                                'px-3 py-1 rounded-lg border border-[var(--color-border-faint)]',
                                link.active ? 'bg-[var(--color-primary)] text-white border-[var(--color-primary)]' : 'bg-[var(--color-surface)]',
                                !link.url ? 'opacity-40 pointer-events-none' : '',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5 h-fit">
                <h2 class="text-sm font-medium uppercase tracking-widest text-[var(--color-fg-dim)] mb-4">
                    {{ editing ? 'Editar usuario' : 'Nuevo usuario' }}
                </h2>
                <div class="space-y-3">
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input v-model="form.name" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Rol *</label>
                        <select v-model="form.role" class="form-input" :disabled="!!editing">
                            <option v-for="r in roles" :key="r" :value="r">{{ ROLE_LABEL[r] || r }}</option>
                        </select>
                    </div>

                    <template v-if="usesPin()">
                        <div>
                            <label class="form-label">PIN {{ editing ? '(dejar vacío para no cambiar)' : '* 4 dígitos' }}</label>
                            <input v-model="form.pin" type="text" inputmode="numeric" maxlength="4" pattern="\d{4}" class="form-input font-numeric" placeholder="1234" />
                        </div>
                    </template>
                    <template v-else>
                        <div>
                            <label class="form-label">Email *</label>
                            <input v-model="form.email" type="email" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Contraseña {{ editing ? '(dejar vacío para no cambiar)' : '* mín. 8 caracteres' }}</label>
                            <input v-model="form.password" type="password" class="form-input" />
                        </div>
                    </template>

                    <div v-if="editing" class="flex items-center gap-2">
                        <input id="user-active" v-model="form.active" type="checkbox" class="rounded" />
                        <label for="user-active" class="text-sm">Activo</label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button class="flex-1" @click="save">{{ editing ? 'Guardar' : 'Crear usuario' }}</Button>
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
