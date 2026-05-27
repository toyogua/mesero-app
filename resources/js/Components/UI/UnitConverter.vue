<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    targetUnit: { type: String, default: '' },
    modelValue: { type: [Number, String], default: '' },
});

const emit = defineEmits(['update:modelValue']);

const FAMILIES = {
    weight: ['g', 'kg', 'lb', 'oz'],
    volume: ['mL', 'L'],
};

// Conversion factor to base unit (grams for weight, mL for volume)
const TO_BASE = { g: 1, kg: 1000, lb: 453.592, oz: 28.3495, mL: 1, L: 1000 };

const family = computed(() =>
    Object.values(FAMILIES).find((f) => f.includes(props.targetUnit)) ?? null,
);

const sourceUnits = computed(() =>
    family.value ? family.value.filter((u) => u !== props.targetUnit) : [],
);

const show = computed(() => sourceUnits.value.length > 0);

const sourceUnit = ref('');
const rawValue   = ref('');
const result     = ref(null);

watch(
    () => props.targetUnit,
    () => {
        sourceUnit.value = sourceUnits.value[0] ?? '';
        rawValue.value   = '';
        result.value     = null;
    },
    { immediate: true },
);

function convert() {
    const v = parseFloat(rawValue.value);
    if (!sourceUnit.value || isNaN(v) || v <= 0) {
        result.value = null;
        return;
    }
    const converted = (v * TO_BASE[sourceUnit.value]) / TO_BASE[props.targetUnit];
    result.value = Math.round(converted * 10000) / 10000;
    emit('update:modelValue', result.value);
}
</script>

<template>
    <div v-if="show" class="mt-1.5 flex items-center gap-1.5">
        <span class="text-[11px] text-[var(--color-fg-dim)] shrink-0">⇄</span>
        <input
            v-model="rawValue"
            type="number"
            step="any"
            min="0"
            placeholder="ej. 150"
            class="w-20 h-7 px-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-fg)] text-xs focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]/40"
            @input="convert"
        />
        <select
            v-model="sourceUnit"
            class="h-7 px-1.5 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-fg)] text-xs focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]/40"
            @change="convert"
        >
            <option v-for="u in sourceUnits" :key="u" :value="u">{{ u }}</option>
        </select>
        <span class="text-xs shrink-0" :class="result !== null ? 'text-[var(--color-primary)] font-medium' : 'text-[var(--color-fg-dim)]'">
            → {{ result !== null ? result : '?' }} {{ targetUnit }}
        </span>
    </div>
</template>
