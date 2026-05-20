<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Menú — mesero-app" />
    <AppLayout title="Menú">
        <div class="rounded-2xl border border-dashed border-[var(--color-border)] py-20 text-center">
            <h2 class="text-xl font-semibold tracking-tight">Catálogo de menú</h2>
            <p class="text-sm text-[var(--color-fg-muted)] mt-2">CRUD pendiente para Fase 1 task #13.</p>
        </div>
    </AppLayout>
</template>
