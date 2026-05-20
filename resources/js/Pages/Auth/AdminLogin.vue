<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/UI/Button.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/admin/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Admin — mesero-app" />
    <AuthLayout>
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-3 mb-8 text-center">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center font-bold text-xl text-[oklch(15%_0.02_60)]"
                     style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-down));">
                    m
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">Ingresar como admin</h1>
                <p class="text-sm text-[var(--color-fg-muted)]">Acceso para configuración y reportes</p>
            </div>

            <form class="liquid-glass rounded-2xl p-6 flex flex-col gap-4" @submit.prevent="submit">
                <label class="flex flex-col gap-1.5">
                    <span class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">Correo</span>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="username"
                        class="h-11 px-3 rounded-lg bg-[var(--color-surface)] border border-[var(--color-border-faint)] focus:border-[var(--color-primary)] focus-ring transition"
                    />
                    <span v-if="form.errors.email" class="text-xs text-[var(--color-err)]">{{ form.errors.email }}</span>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)]">Contraseña</span>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="h-11 px-3 rounded-lg bg-[var(--color-surface)] border border-[var(--color-border-faint)] focus:border-[var(--color-primary)] focus-ring transition"
                    />
                </label>

                <label class="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                    <input v-model="form.remember" type="checkbox" class="rounded" />
                    Recordar este dispositivo
                </label>

                <Button type="submit" variant="primary" size="lg" :loading="form.processing">
                    {{ form.processing ? 'Ingresando…' : 'Ingresar' }}
                </Button>

                <Link
                    href="/"
                    class="text-xs uppercase tracking-widest text-center text-[var(--color-fg-dim)] hover:text-[var(--color-fg-muted)] transition pt-2"
                >
                    ← Soy mesero · usar PIN
                </Link>
            </form>
        </div>
    </AuthLayout>
</template>
