<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    categories:      { type: Array,  required: true },
    restaurant_name: { type: String, default: 'Restaurante' },
});

const activeCategory = ref(props.categories[0]?.category ?? null);

const currentItems = computed(() =>
    props.categories.find((c) => c.category === activeCategory.value)?.items ?? [],
);

function currency(v) {
    return `Q ${Number(v).toFixed(2)}`;
}

function priceDelta(v) {
    if (v === 0) return '';
    return v > 0 ? `+Q ${v.toFixed(2)}` : `-Q ${Math.abs(v).toFixed(2)}`;
}
</script>

<template>
    <Head :title="`Menú — ${restaurant_name}`" />

    <div class="min-h-screen bg-[oklch(12%_0.01_60)]">

        <!-- Header -->
        <header class="sticky top-0 z-20 bg-[oklch(12%_0.01_60)]/90 backdrop-blur-md border-b border-white/10 px-5 py-4">
            <div class="max-w-2xl mx-auto flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-[oklch(12%_0.01_60)] shrink-0"
                     style="background: linear-gradient(135deg, oklch(70% 0.2 60), oklch(60% 0.2 60));">
                    m
                </div>
                <div>
                    <div class="text-sm font-semibold text-white leading-tight">{{ restaurant_name }}</div>
                    <div class="text-[10px] uppercase tracking-widest text-white/40">Menú digital</div>
                </div>
            </div>
        </header>

        <!-- Category tabs -->
        <div v-if="categories.length > 1"
             class="sticky top-[65px] z-10 bg-[oklch(12%_0.01_60)]/90 backdrop-blur-md border-b border-white/10">
            <div class="max-w-2xl mx-auto flex gap-1 px-4 py-2 overflow-x-auto no-scrollbar">
                <button
                    v-for="cat in categories"
                    :key="cat.category"
                    type="button"
                    class="shrink-0 px-4 py-1.5 rounded-full text-xs font-medium transition-all"
                    :class="activeCategory === cat.category
                        ? 'bg-[oklch(70%_0.2_60)] text-[oklch(12%_0.01_60)]'
                        : 'bg-white/10 text-white/60 hover:bg-white/20 hover:text-white'"
                    @click="activeCategory = cat.category"
                >
                    {{ cat.category }}
                </button>
            </div>
        </div>

        <!-- Items -->
        <main class="max-w-2xl mx-auto px-4 py-6 space-y-3">

            <div v-if="!categories.length" class="text-center py-20 text-white/40 text-sm">
                Menú no disponible.
            </div>

            <template v-else>
                <div
                    v-for="item in currentItems"
                    :key="item.id"
                    class="rounded-2xl bg-white/5 border border-white/10 px-5 py-4 hover:bg-white/8 transition-colors"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-white leading-tight">{{ item.name }}</div>
                            <div v-if="item.description" class="text-sm text-white/50 mt-1 leading-snug">
                                {{ item.description }}
                            </div>

                            <!-- Modificadores -->
                            <div v-if="item.modifier_groups?.length" class="mt-2 space-y-1">
                                <div
                                    v-for="group in item.modifier_groups"
                                    :key="group.name"
                                    class="text-xs text-white/40"
                                >
                                    <span class="font-medium text-white/60">{{ group.name }}:</span>
                                    {{ group.options.map(o => o.name + (priceDelta(o.price_delta) ? ` (${priceDelta(o.price_delta)})` : '')).join(', ') }}
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            <span class="text-lg font-semibold text-[oklch(70%_0.2_60)]">
                                {{ currency(item.price) }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <!-- Footer -->
        <footer class="max-w-2xl mx-auto px-4 pb-10 pt-4 text-center text-xs text-white/25">
            Precios en Quetzales (GTQ) · IVA incluido
        </footer>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
