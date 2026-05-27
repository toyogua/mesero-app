<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    as: { type: String, default: 'button' },
    type: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
});

const baseClasses = [
    'inline-flex items-center justify-center gap-2',
    'font-medium tracking-tight',
    'transition-all duration-200',
    'focus-ring tap-target',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    'select-none',
];

const variantMap = {
    primary: [
        'bg-[var(--color-primary)] text-[oklch(15%_0.02_60)]',
        'hover:bg-[var(--color-primary-up)]',
        'shadow-[0_8px_24px_-8px_color-mix(in_oklch,var(--color-primary)_60%,transparent)]',
    ],
    ghost: [
        'bg-[var(--color-surface)] text-[var(--color-fg)]',
        'border border-[var(--color-border)]',
        'hover:bg-[var(--color-surface-up)]',
    ],
    glass: [
        'liquid-glass text-[var(--color-fg)]',
        'hover:bg-white/10',
    ],
    danger: [
        'bg-[var(--color-err)] text-white',
        'hover:opacity-90',
    ],
    subtle: [
        'text-[var(--color-fg-muted)]',
        'hover:text-[var(--color-fg)]',
        'hover:bg-[var(--color-surface)]',
    ],
};

const sizeMap = {
    sm: 'h-9 px-3 text-sm rounded-md',
    md: 'h-11 px-4 text-sm rounded-lg',
    lg: 'h-12 px-5 text-base rounded-lg',
    xl: 'h-14 px-6 text-base rounded-xl',
    icon: 'h-11 w-11 rounded-lg',
};

const classes = computed(() => [
    ...baseClasses,
    ...variantMap[props.variant],
    sizeMap[props.size],
]);
</script>

<template>
    <component :is="as" v-bind="as !== 'button' ? { href: $attrs.href } : { type }" :class="classes" :disabled="loading">
        <slot />
    </component>
</template>
