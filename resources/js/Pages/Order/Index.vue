<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    categories:       { type: Array,   required: true },
    restaurant_name:  { type: String,  default: 'Restaurante' },
    ordering_enabled: { type: Boolean, default: true },
    min_amount:       { type: Number,  default: null },
});

// ── Navegación ────────────────────────────────────────────────────────────────
const activeCategory = ref(props.categories[0]?.category ?? null);
const currentItems   = computed(() =>
    props.categories.find((c) => c.category === activeCategory.value)?.items ?? [],
);

// ── Carrito ───────────────────────────────────────────────────────────────────
// { id, name, price, quantity, modifiers: [{id,name,price_delta}], notes, lineKey }
const cart    = ref([]);
const lineKey = ref(0);

const cartCount = computed(() => cart.value.reduce((s, l) => s + l.quantity, 0));
const cartTotal = computed(() =>
    cart.value.reduce((s, l) => s + (l.price + l.modifiers.reduce((a, m) => a + m.price_delta, 0)) * l.quantity, 0),
);

function addLine(item, modifiers = [], notes = '') {
    cart.value.push({
        lineKey:   lineKey.value++,
        menu_item_id: item.id,
        name:      item.name,
        price:     item.price,
        quantity:  1,
        modifiers,
        notes,
    });
}

function removeLine(key) {
    cart.value = cart.value.filter((l) => l.lineKey !== key);
}

function changeQty(key, delta) {
    const line = cart.value.find((l) => l.lineKey === key);
    if (!line) return;
    line.quantity = Math.max(1, line.quantity + delta);
}

// ── Modifier picker ───────────────────────────────────────────────────────────
const picker = ref(null); // { item, selections: { groupId: [optionId] } }

function openPicker(item) {
    if (!item.modifier_groups?.length) {
        addLine(item);
        return;
    }
    const selections = {};
    for (const g of item.modifier_groups) selections[g.id] = [];
    picker.value = { item, selections };
}

function toggleOption(group, optionId) {
    if (!picker.value) return;
    const sel = picker.value.selections[group.id];
    if (group.selection_type === 'single') {
        picker.value.selections[group.id] = sel[0] === optionId ? [] : [optionId];
    } else {
        const idx = sel.indexOf(optionId);
        idx === -1 ? sel.push(optionId) : sel.splice(idx, 1);
    }
}

function isSelected(groupId, optionId) {
    return picker.value?.selections[groupId]?.includes(optionId) ?? false;
}

function pickerCanConfirm() {
    if (!picker.value) return false;
    for (const g of picker.value.item.modifier_groups) {
        if (g.required && picker.value.selections[g.id].length === 0) return false;
    }
    return true;
}

function confirmPicker() {
    if (!pickerCanConfirm()) return;
    const mods = picker.value.item.modifier_groups.flatMap((g) =>
        g.options.filter((o) => picker.value.selections[g.id].includes(o.id))
            .map((o) => ({ id: o.id, name: o.name, price_delta: o.price_delta })),
    );
    addLine(picker.value.item, mods);
    picker.value = null;
}

// ── Vista: menu | cart ────────────────────────────────────────────────────────
const view = ref('menu'); // 'menu' | 'cart'

// ── Formulario ────────────────────────────────────────────────────────────────
const form = ref({ customer_name: '', customer_phone: '', customer_address: '' });
const errors  = ref({});
const sending = ref(false);

const belowMinimum = computed(() =>
    props.min_amount > 0 && cartTotal.value < props.min_amount,
);

function submit() {
    if (!cart.value.length || belowMinimum.value) return;
    sending.value = true;
    const payload = {
        customer_name:    form.value.customer_name,
        customer_phone:   form.value.customer_phone,
        customer_address: form.value.customer_address || null,
        items: cart.value.map((l) => ({
            menu_item_id: l.menu_item_id,
            quantity:     l.quantity,
            notes:        l.notes || null,
            modifiers:    l.modifiers.map((m) => m.id),
        })),
    };
    router.post('/order', payload, {
        onError:  (e) => { errors.value = e; sending.value = false; },
        onFinish: () => { sending.value = false; },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function currency(v) { return `Q ${Number(v).toFixed(2)}`; }

// ── Animaciones carrito ───────────────────────────────────────────────────────
const countBump = ref(false);
const totalBump = ref(false);

watch(cartCount, () => {
    countBump.value = true;
    setTimeout(() => { countBump.value = false; }, 400);
});

watch(cartTotal, () => {
    totalBump.value = true;
    setTimeout(() => { totalBump.value = false; }, 400);
});

function formatCategory(cat) {
    const s = cat.replace(/_/g, ' ');
    return s.charAt(0).toUpperCase() + s.slice(1);
}
function linePrice(line) {
    return (line.price + line.modifiers.reduce((a, m) => a + m.price_delta, 0)) * line.quantity;
}
</script>

<template>
    <Head :title="`Ordenar — ${restaurant_name}`" />

    <div class="min-h-screen bg-[oklch(12%_0.01_60)] text-white pb-28">

        <!-- ── NO DISPONIBLE ────────────────────────────────────────────────── -->
        <template v-if="!ordering_enabled">
            <div class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
                <div class="text-5xl mb-5">🚫</div>
                <h1 class="text-xl font-bold tracking-tight mb-2">Órdenes en línea no disponibles</h1>
                <p class="text-sm text-white/50 max-w-xs">
                    Por el momento no estamos recibiendo órdenes en línea. Podés comunicarte con nosotros directamente.
                </p>
            </div>
        </template>

        <template v-else>

        <!-- Header -->
        <header class="sticky top-0 z-20 bg-[oklch(12%_0.01_60)]/95 backdrop-blur-md border-b border-white/10 px-5 py-4">
            <div class="max-w-2xl mx-auto flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-[oklch(12%_0.01_60)] shrink-0"
                         style="background: linear-gradient(135deg, oklch(70% 0.2 60), oklch(60% 0.2 60));">
                        m
                    </div>
                    <div>
                        <div class="text-sm font-semibold leading-tight">{{ restaurant_name }}</div>
                        <div class="text-[10px] uppercase tracking-widest text-white/40">Ordenar en línea</div>
                    </div>
                </div>
                <button
                    v-if="view === 'cart'"
                    type="button"
                    class="text-xs uppercase tracking-widest text-white/50 hover:text-white transition"
                    @click="view = 'menu'"
                >
                    ← Menú
                </button>
            </div>
        </header>

        <!-- ── VISTA MENÚ ─────────────────────────────────────────────────── -->
        <template v-if="view === 'menu'">

            <!-- Badge monto mínimo -->
            <div v-if="min_amount > 0" class="max-w-2xl mx-auto px-4 pt-4">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-[oklch(70%_0.2_60)]/15 border border-[oklch(70%_0.2_60)]/30 text-[oklch(70%_0.2_60)]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Orden mínima: Q {{ Number(min_amount).toFixed(2) }}
                </span>
            </div>

            <!-- Category tabs -->
            <div v-if="categories.length > 1"
                 class="sticky top-[65px] z-10 bg-[oklch(12%_0.01_60)]/95 backdrop-blur-md border-b border-white/10">
                <div class="max-w-2xl mx-auto flex gap-1 px-4 py-2 overflow-x-auto no-scrollbar">
                    <button
                        v-for="cat in categories"
                        :key="cat.category"
                        type="button"
                        class="shrink-0 px-4 py-1.5 rounded-full text-xs font-medium transition-all"
                        :class="activeCategory === cat.category
                            ? 'bg-[oklch(70%_0.2_60)] text-[oklch(12%_0.01_60)]'
                            : 'bg-white/10 text-white/60 hover:bg-white/20'"
                        @click="activeCategory = cat.category"
                    >
                        {{ formatCategory(cat.category) }}
                    </button>
                </div>
            </div>

            <!-- Items -->
            <div class="max-w-2xl mx-auto px-4 pt-4 space-y-3">
                <div
                    v-for="item in currentItems"
                    :key="item.id"
                    class="flex items-start gap-4 rounded-2xl bg-white/5 border border-white/8 p-4"
                >
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold">{{ item.name }}</div>
                        <div v-if="item.description" class="text-xs text-white/40 mt-0.5 line-clamp-2">
                            {{ item.description }}
                        </div>
                        <div class="font-numeric text-sm font-semibold mt-2 text-[oklch(70%_0.2_60)]">
                            {{ currency(item.price) }}
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center text-xl font-bold transition active:scale-90"
                        style="background: oklch(70% 0.2 60); color: oklch(12% 0.01 60);"
                        @click="openPicker(item)"
                    >
                        +
                    </button>
                </div>

                <div v-if="!currentItems.length" class="py-16 text-center text-white/20">
                    Sin ítems en esta categoría
                </div>
            </div>
        </template>

        <!-- ── VISTA CARRITO + FORMULARIO ────────────────────────────────── -->
        <template v-else>
            <div class="max-w-2xl mx-auto px-4 pt-5 space-y-5">

                <!-- Ítems del carrito -->
                <div class="rounded-2xl bg-white/5 border border-white/8 overflow-hidden divide-y divide-white/8">
                    <div
                        v-for="line in cart"
                        :key="line.lineKey"
                        class="flex items-start gap-3 p-4"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold">{{ line.name }}</div>
                            <div v-if="line.modifiers.length" class="mt-0.5 space-y-0.5">
                                <div v-for="m in line.modifiers" :key="m.id" class="text-[10px] text-[oklch(70%_0.2_60)]">
                                    + {{ m.name }} <template v-if="m.price_delta > 0">(+Q {{ m.price_delta.toFixed(2) }})</template>
                                </div>
                            </div>
                            <div class="font-numeric text-xs text-white/40 mt-1">
                                {{ currency(linePrice(line)) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" class="w-7 h-7 rounded-full bg-white/10 text-sm font-bold hover:bg-white/20 transition flex items-center justify-center" @click="changeQty(line.lineKey, -1)">−</button>
                            <span class="font-numeric text-sm w-4 text-center">{{ line.quantity }}</span>
                            <button type="button" class="w-7 h-7 rounded-full bg-white/10 text-sm font-bold hover:bg-white/20 transition flex items-center justify-center" @click="changeQty(line.lineKey, +1)">+</button>
                            <button type="button" class="w-7 h-7 rounded-full bg-red-500/20 text-red-400 text-xs hover:bg-red-500/30 transition flex items-center justify-center" @click="removeLine(line.lineKey)">✕</button>
                        </div>
                    </div>

                    <div class="flex justify-between px-4 py-3">
                        <span class="text-sm text-white/50">Total estimado</span>
                        <span class="font-numeric text-sm font-semibold">{{ currency(cartTotal) }}</span>
                    </div>
                </div>

                <!-- Datos del cliente -->
                <div class="rounded-2xl bg-white/5 border border-white/8 p-5 space-y-4">
                    <h2 class="text-sm font-semibold">Tus datos</h2>

                    <div>
                        <label class="block text-xs uppercase tracking-widest text-white/40 mb-1.5">
                            Nombre <span class="text-red-400">*</span>
                        </label>
                        <input v-model="form.customer_name" type="text" placeholder="Juan Pérez"
                            class="w-full h-10 px-3 rounded-lg bg-white/8 border border-white/10 text-sm placeholder-white/20 focus:outline-none focus:border-white/30 transition"
                            :class="{ 'border-red-400': errors.customer_name }" />
                        <p v-if="errors.customer_name" class="mt-1 text-xs text-red-400">{{ errors.customer_name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-widest text-white/40 mb-1.5">
                            Teléfono <span class="text-red-400">*</span>
                        </label>
                        <input v-model="form.customer_phone" type="tel" placeholder="5555-1234"
                            class="w-full h-10 px-3 rounded-lg bg-white/8 border border-white/10 text-sm placeholder-white/20 focus:outline-none focus:border-white/30 transition"
                            :class="{ 'border-red-400': errors.customer_phone }" />
                        <p v-if="errors.customer_phone" class="mt-1 text-xs text-red-400">{{ errors.customer_phone }}</p>
                    </div>

                    <div>
                        <label class="block text-xs uppercase tracking-widest text-white/40 mb-1.5">Dirección</label>
                        <input v-model="form.customer_address" type="text" placeholder="Zona 10, Calzada..."
                            class="w-full h-10 px-3 rounded-lg bg-white/8 border border-white/10 text-sm placeholder-white/20 focus:outline-none focus:border-white/30 transition" />
                    </div>
                </div>

                <div v-if="min_amount > 0 && belowMinimum" class="flex items-center gap-2 px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <span>Mínimo Q {{ Number(min_amount).toFixed(2) }} — te faltan Q {{ Number(min_amount - cartTotal).toFixed(2) }}</span>
                </div>

                <button
                    type="button"
                    class="w-full h-12 rounded-xl font-semibold text-sm uppercase tracking-widest transition disabled:opacity-30"
                    style="background: oklch(70% 0.2 60); color: oklch(12% 0.01 60);"
                    :disabled="!form.customer_name || !form.customer_phone || sending || belowMinimum"
                    @click="submit"
                >
                    {{ sending ? 'Enviando…' : `Confirmar orden · ${currency(cartTotal)}` }}
                </button>
            </div>
        </template>

        <!-- ── MODIFIER PICKER MODAL ─────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="picker"
                 class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 backdrop-blur-sm"
                 @click.self="picker = null">
                <div class="w-full max-w-lg bg-[oklch(15%_0.01_60)] rounded-t-3xl border-t border-white/10 overflow-hidden">
                    <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
                        <div>
                            <div class="text-sm font-semibold">{{ picker.item.name }}</div>
                            <div class="text-xs text-white/40">{{ currency(picker.item.price) }}</div>
                        </div>
                        <button type="button" class="p-2 text-white/40 hover:text-white transition" @click="picker = null">✕</button>
                    </div>

                    <div class="px-5 py-4 space-y-5 max-h-[60vh] overflow-y-auto">
                        <div v-for="group in picker.item.modifier_groups" :key="group.id">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-white/80">{{ group.name }}</span>
                                <span v-if="group.required" class="text-[9px] uppercase tracking-widest px-1.5 py-0.5 rounded-full bg-red-400/15 text-red-400">Requerido</span>
                            </div>
                            <div class="space-y-1.5">
                                <button
                                    v-for="opt in group.options"
                                    :key="opt.id"
                                    type="button"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl border transition text-sm"
                                    :class="isSelected(group.id, opt.id)
                                        ? 'border-[oklch(70%_0.2_60)] bg-[oklch(70%_0.2_60)]/15 text-white'
                                        : 'border-white/10 bg-white/5 text-white/70 hover:border-white/20'"
                                    @click="toggleOption(group, opt.id)"
                                >
                                    <span>{{ opt.name }}</span>
                                    <span v-if="opt.price_delta > 0" class="text-xs text-white/40">+{{ currency(opt.price_delta) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-white/10">
                        <button
                            type="button"
                            class="w-full h-11 rounded-xl font-semibold text-sm uppercase tracking-widest transition disabled:opacity-30"
                            style="background: oklch(70% 0.2 60); color: oklch(12% 0.01 60);"
                            :disabled="!pickerCanConfirm()"
                            @click="confirmPicker"
                        >
                            Agregar al carrito
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── CARRITO FLOTANTE ───────────────────────────────────────────── -->
        <div v-if="cartCount > 0 && view === 'menu'" class="fixed bottom-6 left-0 right-0 flex justify-center z-30 px-4">
            <button
                type="button"
                class="flex items-center gap-4 px-6 py-3.5 rounded-2xl shadow-2xl transition active:scale-95 w-full max-w-sm"
                style="background: oklch(70% 0.2 60); color: oklch(12% 0.01 60);"
                @click="view = 'cart'"
            >
                <span
                    class="w-7 h-7 rounded-full bg-[oklch(12%_0.01_60)]/20 flex items-center justify-center text-xs font-bold shrink-0"
                    :class="{ 'cart-bump': countBump }"
                >
                    {{ cartCount }}
                </span>
                <span class="flex-1 text-sm font-semibold">Ver carrito</span>
                <span
                    class="font-numeric text-sm font-bold"
                    :class="{ 'cart-bump': totalBump }"
                >{{ currency(cartTotal) }}</span>
            </button>
        </div>

        </template><!-- end v-else ordering_enabled -->
    </div>
</template>

<style scoped>
@keyframes cart-bump {
    0%   { transform: scale(1); }
    35%  { transform: scale(1.45); }
    65%  { transform: scale(0.9); }
    100% { transform: scale(1); }
}

.cart-bump {
    animation: cart-bump 0.38s cubic-bezier(0.34, 1.56, 0.64, 1);
}
</style>
