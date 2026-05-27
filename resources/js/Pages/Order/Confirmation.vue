<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    number:        { type: String, required: true },
    customer_name: { type: String, required: true },
    total:         { type: Number, required: true },
    items_count:   { type: Number, required: true },
});
</script>

<template>
    <Head title="Orden recibida" />

    <div class="min-h-screen bg-[oklch(12%_0.01_60)] text-white flex flex-col items-center justify-center p-6 text-center">
        <div class="text-6xl mb-6">🎉</div>
        <h1 class="text-2xl font-bold tracking-tight mb-1">¡Orden recibida!</h1>
        <p class="text-white/50 text-sm mb-8">
            Gracias, {{ customer_name }}. Te llamaremos para confirmar tu pedido.
        </p>

        <div class="w-full max-w-xs rounded-2xl border border-white/10 bg-white/5 p-6 space-y-3 text-left mb-8">
            <div class="flex justify-between text-sm">
                <span class="text-white/50">Número de orden</span>
                <span class="font-semibold font-numeric">{{ number }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-white/50">Ítems</span>
                <span class="font-numeric">{{ items_count }}</span>
            </div>
            <div class="flex justify-between text-sm border-t border-white/10 pt-3">
                <span class="text-white/50">Total estimado</span>
                <span class="font-semibold font-numeric">Q {{ total.toFixed(2) }}</span>
            </div>
        </div>

        <p class="text-xs text-white/30 max-w-xs">
            El total puede variar si se realizan cambios durante la confirmación telefónica.
        </p>
    </div>
</template>
