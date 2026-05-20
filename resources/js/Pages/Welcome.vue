<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: String,
    phpVersion: String,
    laravelVersion: String,
});
</script>

<template>
    <Head title="mesero-app" />
    <main class="min-h-screen flex items-center justify-center bg-zinc-950 text-zinc-100">
        <div class="text-center space-y-6 max-w-xl px-6">
            <h1 class="text-5xl font-bold tracking-tight">
                {{ appName }}
            </h1>
            <p class="text-zinc-400 text-lg">
                POS para restaurantes en Guatemala — Laravel {{ laravelVersion }} · PHP {{ phpVersion }} · Vue 3 + Inertia
            </p>
            <div class="text-xs text-zinc-600 pt-8">
                Bootstrap inicial · Fase 0 completada
            </div>
        </div>
    </main>
</template>
