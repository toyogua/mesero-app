<script setup>
defineProps({ title: String });
</script>

<template>
    <div class="min-h-screen grain relative overflow-hidden">
        <!-- Glow de fondo -->
        <div class="absolute inset-0 -z-10">
            <div class="absolute top-1/4 left-1/4 w-[40rem] h-[40rem] rounded-full opacity-30 blur-[120px]"
                 style="background: radial-gradient(circle, var(--color-primary) 0%, transparent 60%);" />
            <div class="absolute bottom-1/4 right-1/4 w-[30rem] h-[30rem] rounded-full opacity-20 blur-[120px]"
                 style="background: radial-gradient(circle, var(--color-accent) 0%, transparent 60%);" />
        </div>

        <main class="relative min-h-screen flex flex-col items-center justify-center px-6 py-8">
            <slot />
        </main>
    </div>
</template>
