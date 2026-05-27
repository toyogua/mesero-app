<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { useChannel } from '@/composables/useChannel.js';

const props = defineProps({
    checks: { type: Array, default: () => [] },
});

// ── Formulario nueva orden ────────────────────────────────────────────────────
const showForm = ref(false);
const creating = ref(false);

const form = ref({
    customer_name:    '',
    customer_phone:   '',
    customer_address: '',
});

const errors = ref({});

function openForm() {
    form.value = { customer_name: '', customer_phone: '', customer_address: '' };
    errors.value = {};
    showForm.value = true;
}

function submit() {
    creating.value = true;
    router.post('/takeout', form.value, {
        onError: (e) => { errors.value = e; creating.value = false; },
        onFinish: () => { creating.value = false; },
    });
}

// ── Realtime ─────────────────────────────────────────────────────────────────
useChannel('display', '.CheckUpdated', () => {
    router.reload({ only: ['checks'], preserveScroll: true });
}, { isPrivate: false });

// ── Helpers ───────────────────────────────────────────────────────────────────
function elapsed(iso) {
    if (!iso) return '—';
    const m = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (m < 60) return `${m}m`;
    return `${Math.floor(m / 60)}h ${m % 60}m`;
}
</script>

<template>
    <Head title="Para llevar — mesero-app" />
    <AppLayout title="Para llevar">
        <template #actions>
            <Button variant="primary" size="sm" @click="openForm">
                + Nueva orden
            </Button>
        </template>

        <!-- Modal nueva orden -->
        <Teleport to="body">
            <div
                v-if="showForm"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="showForm = false"
            >
                <div class="w-full max-w-md rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] shadow-2xl overflow-hidden">
                    <div class="px-6 py-5 border-b border-[var(--color-border-faint)] flex items-center justify-between">
                        <h2 class="text-base font-semibold tracking-tight">Nueva orden para llevar</h2>
                        <button
                            type="button"
                            class="p-1.5 rounded-lg text-[var(--color-fg-dim)] hover:text-[var(--color-fg)] hover:bg-[var(--color-surface-up)] transition"
                            @click="showForm = false"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form class="px-6 py-5 space-y-4" @submit.prevent="submit">
                        <!-- Nombre -->
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                                Nombre del cliente <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.customer_name"
                                type="text"
                                placeholder="Juan Pérez"
                                class="w-full h-10 px-3 rounded-lg bg-[var(--color-surface-down)] border border-[var(--color-border-faint)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                                :class="{ 'border-red-400': errors.customer_name }"
                                autofocus
                            />
                            <p v-if="errors.customer_name" class="mt-1 text-xs text-red-400">{{ errors.customer_name }}</p>
                        </div>

                        <!-- Teléfono -->
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                                Teléfono <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.customer_phone"
                                type="tel"
                                placeholder="5555-1234"
                                class="w-full h-10 px-3 rounded-lg bg-[var(--color-surface-down)] border border-[var(--color-border-faint)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                                :class="{ 'border-red-400': errors.customer_phone }"
                            />
                            <p v-if="errors.customer_phone" class="mt-1 text-xs text-red-400">{{ errors.customer_phone }}</p>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                                Dirección
                            </label>
                            <input
                                v-model="form.customer_address"
                                type="text"
                                placeholder="Zona 10, Calzada Roosevelt..."
                                class="w-full h-10 px-3 rounded-lg bg-[var(--color-surface-down)] border border-[var(--color-border-faint)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                            />
                        </div>

                        <div class="flex gap-2 pt-1">
                            <Button type="button" variant="ghost" class="flex-1" @click="showForm = false">
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                class="flex-1"
                                :loading="creating"
                                :disabled="!form.customer_name || !form.customer_phone"
                            >
                                Crear orden
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Empty -->
        <div
            v-if="!checks.length"
            class="rounded-2xl border border-dashed border-[var(--color-border)] py-24 text-center"
        >
            <div class="text-4xl mb-3">🛍️</div>
            <h2 class="text-xl font-semibold tracking-tight">Sin órdenes para llevar</h2>
            <p class="text-sm text-[var(--color-fg-muted)] mt-1 mb-6">
                Cuando llegue la primera, aparecerá acá.
            </p>
            <Button variant="primary" @click="openForm">+ Nueva orden</Button>
        </div>

        <!-- Grid de órdenes activas -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <Link
                v-for="c in checks"
                :key="c.id"
                :href="`/checks/${c.id}`"
                class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] hover:border-[var(--color-border)] transition p-5 flex flex-col gap-3 group"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">
                            Para llevar · {{ c.number }}
                        </div>
                        <div class="text-lg font-semibold tracking-tight truncate mt-0.5">
                            {{ c.customer_name }}
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <Badge tone="warn" size="md">{{ elapsed(c.opened_at) }}</Badge>
                        <span
                            v-if="c.source === 'web'"
                            class="text-[9px] uppercase tracking-widest font-bold px-2 py-0.5 rounded-full bg-[var(--color-primary)]/15 text-[var(--color-primary)] border border-[var(--color-primary)]/30"
                        >
                            Orden web
                        </span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span class="truncate">{{ c.customer_phone }}</span>
                    </div>
                    <div v-if="c.customer_address" class="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="truncate">{{ c.customer_address }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1 border-t border-[var(--color-border-faint)]">
                    <span class="text-xs text-[var(--color-fg-dim)]">
                        {{ c.items_count }} ítem{{ c.items_count !== 1 ? 's' : '' }} · {{ c.waiter.name }}
                    </span>
                    <span class="font-numeric text-sm font-semibold">
                        Q {{ c.total.toFixed(2) }}
                    </span>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
