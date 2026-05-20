<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PinKeypad from '@/Components/UI/PinKeypad.vue';

const props = defineProps({
    waiters: { type: Array, default: () => [] },
});

const selected = ref(null);

const form = useForm({
    user_id: null,
    pin: '',
});

const error = computed(() => form.errors.pin);

function pick(waiter) {
    selected.value = waiter;
    form.reset();
    form.clearErrors();
    form.user_id = waiter.id;
}

function close() {
    selected.value = null;
    form.reset();
    form.clearErrors();
}

function submit(pin) {
    form.pin = pin;
    form.post('/login', {
        preserveScroll: true,
        onError: () => {
            form.pin = '';
            nextTick(() => {
                // El error queda visible hasta el próximo tap
            });
        },
    });
}

const roleLabel = {
    waiter: 'Mesero',
    admin: 'Admin',
    cashier: 'Cajero',
    kitchen: 'Cocina',
};
</script>

<template>
    <Head title="Ingresar — mesero-app" />
    <AuthLayout>
        <div class="w-full max-w-3xl flex flex-col items-center text-center gap-10">
            <!-- Brand -->
            <div class="flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center font-bold text-2xl text-[oklch(15%_0.02_60)]"
                     style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-down));">
                    m
                </div>
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight">¿Quién entra?</h1>
                    <p class="text-[var(--color-fg-muted)] mt-1">Tocá tu nombre y digitá tu PIN</p>
                </div>
            </div>

            <!-- Grid de meseros -->
            <div v-if="waiters.length" class="w-full grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                <button
                    v-for="w in waiters"
                    :key="w.id"
                    type="button"
                    class="group flex flex-col items-center gap-3 p-5 rounded-2xl liquid-glass tap-target focus-ring transition-all duration-200 hover:-translate-y-0.5 active:scale-95"
                    @click="pick(w)"
                >
                    <div class="w-14 h-14 rounded-full bg-[var(--color-surface-up)] flex items-center justify-center text-lg font-semibold group-hover:bg-[color-mix(in_oklch,var(--color-primary)_20%,var(--color-surface-up))] transition-colors">
                        {{ w.initials }}
                    </div>
                    <div class="flex flex-col items-center gap-0.5">
                        <span class="text-sm font-medium leading-tight">{{ w.name }}</span>
                        <span class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">
                            {{ roleLabel[w.role] || w.role }}
                        </span>
                    </div>
                </button>
            </div>

            <div v-else class="text-[var(--color-fg-muted)] py-12">
                <p>Sin usuarios activos. Pedile a un admin que cree el primero.</p>
            </div>

            <!-- Admin link -->
            <Link
                href="/admin/login"
                class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] hover:text-[var(--color-fg-muted)] transition"
            >
                Soy admin · ingresar con correo
            </Link>
        </div>

        <!-- Modal del PIN -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="selected"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-md"
                @click.self="close"
            >
                <div class="w-full max-w-sm liquid-glass-strong rounded-3xl p-8 flex flex-col items-center gap-6">
                    <button
                        type="button"
                        class="self-end -mt-2 -mr-2 p-2 rounded-full text-[var(--color-fg-dim)] hover:text-[var(--color-fg)] hover:bg-white/5 focus-ring"
                        @click="close"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="flex flex-col items-center gap-3">
                        <div class="w-16 h-16 rounded-full bg-[var(--color-surface-up)] flex items-center justify-center text-xl font-semibold">
                            {{ selected.initials }}
                        </div>
                        <div class="text-center">
                            <h2 class="text-lg font-semibold tracking-tight">{{ selected.name }}</h2>
                            <p class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mt-0.5">
                                {{ roleLabel[selected.role] || selected.role }}
                            </p>
                        </div>
                    </div>

                    <PinKeypad
                        :model-value="form.pin"
                        :error="!!error"
                        @update:model-value="(v) => (form.pin = v)"
                        @complete="submit"
                    />

                    <div class="h-5 text-sm text-[var(--color-err)]" aria-live="polite">
                        {{ error || '' }}
                    </div>
                </div>
            </div>
        </Transition>
    </AuthLayout>
</template>
