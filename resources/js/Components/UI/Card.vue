<script setup>
import { computed } from 'vue';

const props = defineProps({
    glass: { type: Boolean, default: false },
    interactive: { type: Boolean, default: false },
    padding: { type: String, default: 'md' },
});

const paddingMap = {
    none: '',
    sm: 'p-3',
    md: 'p-5',
    lg: 'p-6',
};

const classes = computed(() => [
    'rounded-xl',
    props.glass
        ? 'liquid-glass'
        : 'bg-[var(--color-surface)] border border-[var(--color-border-faint)]',
    props.interactive && 'cursor-pointer transition-all duration-200 hover:border-[var(--color-border)] hover:bg-[var(--color-surface-up)] hover:-translate-y-0.5',
    paddingMap[props.padding],
]);
</script>

<template>
    <div :class="classes">
        <slot />
    </div>
</template>
