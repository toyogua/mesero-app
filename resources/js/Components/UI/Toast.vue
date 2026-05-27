<script setup>
import { useToast } from '@/composables/useToast.js';

const { toasts, remove } = useToast();

const toneClass = {
    info:    'bg-[var(--color-surface-up)] border-[var(--color-border)] text-[var(--color-fg)]',
    ok:      'bg-[oklch(25%_0.06_145)] border-[var(--color-ok)]/40 text-[var(--color-ok)]',
    warn:    'bg-[oklch(25%_0.06_60)] border-[var(--color-warn)]/40 text-[var(--color-warn)]',
    err:     'bg-[oklch(25%_0.06_20)] border-[var(--color-err)]/40 text-[var(--color-err)]',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 items-end pointer-events-none">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm font-medium max-w-xs"
                    :class="toneClass[t.tone] ?? toneClass.info"
                >
                    <span class="flex-1">{{ t.message }}</span>
                    <button
                        type="button"
                        class="opacity-60 hover:opacity-100 shrink-0"
                        @click="remove(t.id)"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
