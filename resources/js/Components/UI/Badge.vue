<script setup>
import { computed } from 'vue';

const props = defineProps({
    tone: { type: String, default: 'neutral' },
    size: { type: String, default: 'md' },
});

const toneMap = {
    neutral: 'bg-[var(--color-surface-up)] text-[var(--color-fg-muted)] border-[var(--color-border-faint)]',
    primary: 'bg-[color-mix(in_oklch,var(--color-primary)_15%,transparent)] text-[var(--color-primary-up)] border-[color-mix(in_oklch,var(--color-primary)_25%,transparent)]',
    ok: 'bg-[color-mix(in_oklch,var(--color-ok)_15%,transparent)] text-[var(--color-ok)] border-[color-mix(in_oklch,var(--color-ok)_25%,transparent)]',
    warn: 'bg-[color-mix(in_oklch,var(--color-warn)_15%,transparent)] text-[var(--color-warn)] border-[color-mix(in_oklch,var(--color-warn)_25%,transparent)]',
    err: 'bg-[color-mix(in_oklch,var(--color-err)_15%,transparent)] text-[var(--color-err)] border-[color-mix(in_oklch,var(--color-err)_25%,transparent)]',
};

const sizeMap = {
    sm: 'text-[10px] px-2 py-0.5',
    md: 'text-xs px-2.5 py-1',
    lg: 'text-sm px-3 py-1',
};

const classes = computed(() => [
    'inline-flex items-center gap-1.5',
    'font-medium tracking-wide uppercase',
    'border rounded-full',
    toneMap[props.tone],
    sizeMap[props.size],
]);
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
