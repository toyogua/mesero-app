<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    length: { type: Number, default: 4 },
    modelValue: { type: String, default: '' },
    error: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'complete']);

const value = ref(props.modelValue);

watch(value, (next) => {
    emit('update:modelValue', next);
    if (next.length === props.length) {
        emit('complete', next);
    }
});

watch(() => props.modelValue, (next) => {
    value.value = next;
});

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', null, '0', 'del'];

function press(key) {
    if (key === 'del') {
        value.value = value.value.slice(0, -1);
        return;
    }
    if (value.value.length >= props.length) return;
    value.value += key;
}

const dots = computed(() =>
    Array.from({ length: props.length }, (_, i) => i < value.value.length)
);
</script>

<template>
    <div class="flex flex-col items-center gap-8 w-full max-w-xs mx-auto">
        <!-- Indicador de PIN -->
        <div class="flex gap-3" :class="error && 'animate-pulse'">
            <div
                v-for="(filled, i) in dots"
                :key="i"
                class="w-3.5 h-3.5 rounded-full transition-all duration-200"
                :class="[
                    filled
                        ? error
                            ? 'bg-[var(--color-err)]'
                            : 'bg-[var(--color-primary)]'
                        : 'bg-[var(--color-surface-up)] border border-[var(--color-border)]',
                ]"
            />
        </div>

        <!-- Teclado numérico -->
        <div class="grid grid-cols-3 gap-3 w-full">
            <button
                v-for="(key, i) in keys"
                :key="i"
                type="button"
                :disabled="!key"
                :class="[
                    'h-16 rounded-2xl text-xl font-medium select-none',
                    'transition-all duration-150',
                    'focus-ring',
                    key
                        ? key === 'del'
                            ? 'bg-[var(--color-surface)] text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-up)] active:scale-95'
                            : 'bg-[var(--color-surface)] text-[var(--color-fg)] hover:bg-[var(--color-surface-up)] active:scale-95 active:bg-[color-mix(in_oklch,var(--color-primary)_20%,var(--color-surface))]'
                        : 'invisible',
                ]"
                @click="key && press(key)"
            >
                <svg v-if="key === 'del'" class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l4.586-4.586a2 2 0 011.414-.586H19a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-1.414-.586L3 12z" />
                </svg>
                <span v-else>{{ key }}</span>
            </button>
        </div>
    </div>
</template>
