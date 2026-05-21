<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import { useChannel } from '@/composables/useChannel.js';

const props = defineProps({
    check: { type: Object, required: true },
    menu: { type: Array, default: () => [] },
});

useChannel(`check.${props.check.id}`, '.CheckUpdated', () => {
    router.reload({ only: ['check'], preserveScroll: true });
});

const activeCategory = ref(props.menu[0]?.category || null);
const tab = ref('items'); // móvil: 'items' | 'menu'
const sending = ref(false);
const closing = ref(false);

// ── Modifier picker ──────────────────────────────────────────────────────────
const pending = ref(null); // { menuItem, selections: { groupId: [optionId] } }

function openPicker(menuItem) {
    if (!menuItem.modifier_groups?.length) {
        addItem(menuItem, []);
        return;
    }
    const selections = {};
    for (const g of menuItem.modifier_groups) {
        selections[g.id] = [];
    }
    pending.value = { menuItem, selections };
}

function toggleOption(group, optionId) {
    if (!pending.value) return;
    const sel = pending.value.selections[group.id];
    if (group.selection_type === 'single') {
        pending.value.selections[group.id] = sel[0] === optionId ? [] : [optionId];
    } else {
        const idx = sel.indexOf(optionId);
        if (idx === -1) sel.push(optionId);
        else sel.splice(idx, 1);
    }
}

function isSelected(groupId, optionId) {
    return pending.value?.selections[groupId]?.includes(optionId) ?? false;
}

function canConfirm() {
    if (!pending.value) return false;
    for (const g of pending.value.menuItem.modifier_groups) {
        if (g.required && pending.value.selections[g.id].length === 0) return false;
    }
    return true;
}

function confirmPicker() {
    if (!pending.value || !canConfirm()) return;
    const modifiers = Object.values(pending.value.selections).flat();
    addItem(pending.value.menuItem, modifiers);
    pending.value = null;
}

// ── Check item actions ────────────────────────────────────────────────────────
const categoryLabels = {
    entradas: 'Entradas',
    platos_fuertes: 'Platos fuertes',
    bebidas: 'Bebidas',
    postres: 'Postres',
};

const draftCount = computed(() =>
    props.check.items.filter((i) => i.status === 'draft').length
);

const itemsByStatus = computed(() => {
    const groups = { draft: [], kitchen: [], ready: [], served: [], cancelled: [] };
    for (const i of props.check.items) {
        if (i.status === 'draft') groups.draft.push(i);
        else if (i.status === 'served') groups.served.push(i);
        else if (i.status === 'cancelled') groups.cancelled.push(i);
        else if (i.status === 'ready') groups.ready.push(i);
        else groups.kitchen.push(i);
    }
    return groups;
});

const statusMeta = {
    draft: { tone: 'neutral', label: 'Borrador' },
    ordered: { tone: 'primary', label: 'En cocina' },
    preparing: { tone: 'warn', label: 'Preparando' },
    ready: { tone: 'ok', label: 'Listo' },
    served: { tone: 'neutral', label: 'Servido' },
    cancelled: { tone: 'err', label: 'Cancelado' },
};

function currency(v) {
    return `Q ${Number(v || 0).toFixed(2)}`;
}

function priceDelta(v) {
    if (!v || v === 0) return '';
    return v > 0 ? `+Q ${Number(v).toFixed(2)}` : `-Q ${Math.abs(v).toFixed(2)}`;
}

function addItem(menuItem, modifiers = []) {
    router.post(
        `/checks/${props.check.id}/items`,
        { items: [{ menu_item_id: menuItem.id, quantity: 1, modifiers }] },
        { preserveScroll: true, preserveState: false }
    );
}

function changeQty(item, delta) {
    const next = item.quantity + delta;
    if (next < 1) { removeItem(item); return; }
    router.patch(`/check-items/${item.id}`, { quantity: next }, { preserveScroll: true, preserveState: false });
}

function removeItem(item) {
    router.delete(`/check-items/${item.id}`, { preserveScroll: true, preserveState: false });
}

function markServed(item) {
    router.post(`/check-items/${item.id}/served`, {}, { preserveScroll: true, preserveState: false });
}

function send() {
    sending.value = true;
    router.post(`/checks/${props.check.id}/send`, {}, {
        preserveScroll: true, preserveState: false, onFinish: () => (sending.value = false),
    });
}

function close() {
    if (!confirm('¿Cerrar la cuenta? Esta acción no se puede deshacer.')) return;
    closing.value = true;
    router.post(`/checks/${props.check.id}/close`, {}, { onFinish: () => (closing.value = false) });
}

function printTicket() {
    window.open(`/checks/${props.check.id}/ticket`, '_blank');
}

const filteredMenu = computed(() =>
    activeCategory.value
        ? props.menu.filter((g) => g.category === activeCategory.value)
        : props.menu
);

// ── Tip ──────────────────────────────────────────────────────────────────────
const tipInput = ref('');

function applyTipPct(pct) {
    const amount = ((props.check.subtotal * pct) / 100).toFixed(2);
    tipInput.value = amount;
    submitTip(amount);
}

function submitTip(amount) {
    const val = parseFloat(amount || tipInput.value);
    if (isNaN(val) || val < 0) return;
    router.patch(`/checks/${props.check.id}/tip`, { amount: val }, { preserveScroll: true, preserveState: false });
    tipInput.value = '';
}

// ── Splits ───────────────────────────────────────────────────────────────────
const splitParts = ref(2);
const showSplitForm = ref(false);

function createSplits() {
    router.post(`/checks/${props.check.id}/splits`, { parts: splitParts.value }, {
        preserveScroll: true, preserveState: false,
        onSuccess: () => { showSplitForm.value = false; },
    });
}

function paySplit(split, method) {
    router.patch(`/check-splits/${split.id}/pay`, { method }, { preserveScroll: true, preserveState: false });
}

function resetSplits() {
    router.delete(`/checks/${props.check.id}/splits`, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <Head :title="`${check.number} — mesero-app`" />
    <AppLayout>
        <template #actions>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] transition"
                    @click="printTicket"
                >🖨 Imprimir</button>
                <Link href="/floor" class="text-xs uppercase tracking-widest text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] transition">
                    ← Salón
                </Link>
            </div>
        </template>

        <!-- Cabecera -->
        <header class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <div class="text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1">
                    {{ check.table?.area_name || 'Sin área' }} · {{ check.number }}
                </div>
                <h1 class="text-3xl font-semibold tracking-tight flex items-center gap-3">
                    {{ check.table?.name || 'Comanda libre' }}
                    <Badge tone="primary" size="md">{{ check.status }}</Badge>
                </h1>
                <div class="text-sm text-[var(--color-fg-muted)] mt-2">
                    {{ check.covers }} comensales · Atiende {{ check.waiter.name }}
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button v-if="draftCount > 0" variant="primary" size="lg" :loading="sending" @click="send">
                    Enviar a cocina ({{ draftCount }})
                </Button>
                <Button v-if="check.is_ready_to_close" variant="ghost" size="lg" :loading="closing" @click="close">
                    Cerrar cuenta
                </Button>
            </div>
        </header>

        <!-- Tabs móvil -->
        <div class="lg:hidden flex gap-2 mb-4">
            <button
                v-for="opt in [{ id: 'items', label: `Cuenta (${check.items.length})` }, { id: 'menu', label: 'Menú' }]"
                :key="opt.id"
                type="button"
                class="flex-1 h-11 rounded-lg text-sm font-medium transition tap-target focus-ring"
                :class="tab === opt.id ? 'bg-[var(--color-fg)] text-[var(--color-bg)]' : 'bg-[var(--color-surface)] text-[var(--color-fg-muted)]'"
                @click="tab = opt.id"
            >{{ opt.label }}</button>
        </div>

        <div class="grid lg:grid-cols-[1fr_1.2fr] gap-6">
            <!-- ── Cuenta ── -->
            <section :class="{ 'hidden lg:block': tab !== 'items' }">
                <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
                    <div class="px-5 py-4 border-b border-[var(--color-border-faint)] flex items-center justify-between">
                        <h2 class="text-sm uppercase tracking-widest text-[var(--color-fg-dim)]">Cuenta</h2>
                        <span class="text-xs text-[var(--color-fg-dim)]">{{ check.items.length }} items</span>
                    </div>

                    <div v-if="!check.items.length" class="p-8 text-center text-[var(--color-fg-muted)]">
                        Sin items todavía. Agregá desde el menú →
                    </div>

                    <div v-else class="divide-y divide-[var(--color-border-faint)]">
                        <template v-for="(group, label) in {
                            'Borrador (no enviado)': itemsByStatus.draft,
                            'En cocina': itemsByStatus.kitchen,
                            'Listos para servir': itemsByStatus.ready,
                            'Servidos': itemsByStatus.served,
                            'Cancelados': itemsByStatus.cancelled,
                        }" :key="label">
                            <div v-if="group.length" class="py-2">
                                <div class="px-5 py-2 text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">{{ label }}</div>
                                <div v-for="item in group" :key="item.id" class="px-5 py-3 flex items-start gap-4">
                                    <!-- Qty controls (solo draft) -->
                                    <div v-if="item.status === 'draft'" class="flex items-center gap-1 bg-[var(--color-surface-up)] rounded-lg p-0.5 mt-0.5">
                                        <button type="button" class="w-7 h-7 rounded-md hover:bg-[var(--color-surface)] focus-ring text-sm" @click="changeQty(item, -1)">−</button>
                                        <span class="font-numeric text-sm w-6 text-center">{{ item.quantity }}</span>
                                        <button type="button" class="w-7 h-7 rounded-md hover:bg-[var(--color-surface)] focus-ring text-sm" @click="changeQty(item, 1)">+</button>
                                    </div>
                                    <span v-else class="font-numeric text-sm text-[var(--color-fg-dim)] w-6 text-center mt-1">{{ item.quantity }}×</span>

                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium truncate">{{ item.name }}</div>
                                        <!-- Modifiers -->
                                        <div v-if="item.modifiers?.length" class="mt-0.5 space-y-0.5">
                                            <div v-for="mod in item.modifiers" :key="mod.name" class="text-[10px] text-[var(--color-fg-muted)]">
                                                + {{ mod.name }}
                                                <span v-if="mod.price_delta" class="text-[var(--color-primary)]">{{ priceDelta(mod.price_delta) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 text-[10px] text-[var(--color-fg-dim)]">
                                            <span>{{ item.kitchen_station?.name || '—' }}</span>
                                            <span v-if="item.notes" class="italic">· {{ item.notes }}</span>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <div class="font-numeric text-sm">{{ currency(item.line_total) }}</div>
                                        <Badge :tone="statusMeta[item.status].tone" size="sm">{{ statusMeta[item.status].label }}</Badge>
                                    </div>

                                    <div class="flex items-center gap-1 mt-0.5">
                                        <button v-if="item.status === 'ready'" type="button" class="h-9 px-3 rounded-lg text-xs uppercase tracking-widest font-medium bg-[var(--color-ok)] text-[oklch(15%_0.02_60)] hover:opacity-90 focus-ring" @click="markServed(item)">Servido</button>
                                        <button v-if="item.status === 'draft' || item.status === 'ordered'" type="button" class="w-9 h-9 rounded-lg text-[var(--color-fg-dim)] hover:text-[var(--color-err)] hover:bg-[var(--color-surface-up)] focus-ring" @click="removeItem(item)">
                                            <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Totales -->
                    <div class="px-5 py-4 border-t border-[var(--color-border-faint)] space-y-1.5">
                        <div class="flex justify-between text-sm text-[var(--color-fg-muted)]">
                            <span>Subtotal</span><span class="font-numeric">{{ currency(check.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-[var(--color-fg-muted)]">
                            <span>IVA (12%)</span><span class="font-numeric">{{ currency(check.tax) }}</span>
                        </div>
                        <div v-if="check.tip > 0" class="flex justify-between text-sm text-[var(--color-fg-muted)]">
                            <span>Propina</span><span class="font-numeric">{{ currency(check.tip) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-semibold pt-2 border-t border-[var(--color-border-faint)] mt-2">
                            <span>Total</span><span class="font-numeric">{{ currency(check.total) }}</span>
                        </div>
                    </div>

                    <!-- Tip widget -->
                    <div v-if="check.status === 'open'" class="px-5 py-4 border-t border-[var(--color-border-faint)]">
                        <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)] mb-2">Propina</div>
                        <div class="flex gap-2 mb-2">
                            <button
                                v-for="pct in [10, 15, 20]" :key="pct"
                                type="button"
                                class="flex-1 h-9 rounded-lg text-xs font-medium border border-[var(--color-border-faint)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition"
                                @click="applyTipPct(pct)"
                            >{{ pct }}%</button>
                        </div>
                        <div class="flex gap-2">
                            <input
                                v-model="tipInput"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Monto manual"
                                class="flex-1 h-9 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 text-sm"
                                @keydown.enter="submitTip()"
                            />
                            <Button size="sm" variant="ghost" @click="submitTip()">Aplicar</Button>
                        </div>
                    </div>

                    <!-- Split section -->
                    <div v-if="check.status === 'open'" class="px-5 py-4 border-t border-[var(--color-border-faint)]">
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">División de cuenta</div>
                            <button
                                v-if="check.splits?.length"
                                type="button"
                                class="text-xs text-[var(--color-err)] hover:underline"
                                @click="resetSplits"
                            >Reiniciar</button>
                        </div>

                        <!-- No splits yet -->
                        <div v-if="!check.splits?.length">
                            <div v-if="!showSplitForm" class="flex gap-2">
                                <Button variant="ghost" size="sm" class="w-full" @click="showSplitForm = true">Dividir en partes iguales</Button>
                            </div>
                            <div v-else class="flex gap-2 items-center">
                                <span class="text-sm text-[var(--color-fg-muted)]">Partes:</span>
                                <input
                                    v-model.number="splitParts"
                                    type="number" min="2" max="20"
                                    class="w-16 h-9 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface)] px-3 text-sm text-center"
                                />
                                <Button size="sm" @click="createSplits">Dividir</Button>
                                <Button size="sm" variant="ghost" @click="showSplitForm = false">✕</Button>
                            </div>
                        </div>

                        <!-- Splits list -->
                        <div v-else class="space-y-2">
                            <div
                                v-for="split in check.splits"
                                :key="split.id"
                                class="flex items-center gap-3 rounded-xl border px-3 py-2.5 transition"
                                :class="split.paid_at ? 'border-[var(--color-ok)]/40 bg-[var(--color-ok)]/5' : 'border-[var(--color-border-faint)]'"
                            >
                                <div class="flex-1">
                                    <div class="text-sm font-medium">{{ split.label }}</div>
                                    <div class="text-xs text-[var(--color-fg-muted)]">
                                        {{ currency(split.amount) }}
                                        <span v-if="split.paid_at" class="text-[var(--color-ok)]"> · Pagado ({{ split.method }})</span>
                                    </div>
                                </div>
                                <div v-if="!split.paid_at" class="flex gap-1">
                                    <button
                                        v-for="method in ['cash', 'card']"
                                        :key="method"
                                        type="button"
                                        class="h-8 px-2.5 rounded-lg text-[10px] uppercase tracking-wide font-medium border border-[var(--color-border-faint)] hover:border-[var(--color-ok)] hover:text-[var(--color-ok)] transition"
                                        @click="paySplit(split, method)"
                                    >{{ method === 'cash' ? 'Efectivo' : 'Tarjeta' }}</button>
                                </div>
                                <div v-else class="text-[var(--color-ok)]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Menú ── -->
            <section :class="{ 'hidden lg:block': tab !== 'menu' }">
                <div class="rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border-faint)] overflow-hidden">
                    <div class="px-5 py-4 border-b border-[var(--color-border-faint)]">
                        <h2 class="text-sm uppercase tracking-widest text-[var(--color-fg-dim)]">Menú</h2>
                    </div>

                    <!-- Categorías -->
                    <div class="px-5 py-3 flex items-center gap-2 overflow-x-auto border-b border-[var(--color-border-faint)]">
                        <button
                            v-for="g in menu" :key="g.category" type="button"
                            class="h-9 px-3 rounded-full text-xs font-medium uppercase tracking-widest transition border focus-ring whitespace-nowrap"
                            :class="activeCategory === g.category
                                ? 'bg-[var(--color-primary)] text-[oklch(15%_0.02_60)] border-transparent'
                                : 'text-[var(--color-fg-muted)] border-[var(--color-border-faint)] hover:border-[var(--color-border)]'"
                            @click="activeCategory = g.category"
                        >{{ categoryLabels[g.category] || g.category }}</button>
                    </div>

                    <!-- Items -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-3">
                        <template v-for="group in filteredMenu" :key="group.category">
                            <button
                                v-for="m in group.items" :key="m.id"
                                type="button"
                                class="text-left p-3 rounded-xl bg-[var(--color-surface-up)] hover:bg-[color-mix(in_oklch,var(--color-primary)_15%,var(--color-surface-up))] active:scale-[0.97] transition-all focus-ring tap-target relative"
                                @click="openPicker(m)"
                            >
                                <div class="text-sm font-medium leading-tight mb-2 line-clamp-2">{{ m.name }}</div>
                                <div class="flex items-center justify-between">
                                    <span class="font-numeric text-sm text-[var(--color-primary)]">{{ currency(m.price) }}</span>
                                    <span class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">{{ m.kitchen_station_name }}</span>
                                </div>
                                <!-- Indicator when item has modifier groups -->
                                <span
                                    v-if="m.modifier_groups?.length"
                                    class="absolute top-2 right-2 w-1.5 h-1.5 rounded-full bg-[var(--color-primary)]"
                                    title="Tiene modificadores"
                                />
                            </button>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <!-- ── Modifier Picker Modal ── -->
        <Teleport to="body">
            <div
                v-if="pending"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
                @click.self="pending = null"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="pending = null" />
                <div class="relative w-full sm:max-w-md bg-[var(--color-bg)] rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden z-10">
                    <!-- Header -->
                    <div class="px-6 pt-6 pb-4 border-b border-[var(--color-border-faint)]">
                        <h3 class="text-lg font-semibold tracking-tight">{{ pending.menuItem.name }}</h3>
                        <p class="text-sm text-[var(--color-fg-muted)] mt-0.5">{{ currency(pending.menuItem.price) }} · personalizá tu pedido</p>
                    </div>

                    <!-- Groups -->
                    <div class="px-6 py-4 max-h-[60vh] overflow-y-auto space-y-5">
                        <div v-for="g in pending.menuItem.modifier_groups" :key="g.id">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-semibold">{{ g.name }}</span>
                                <Badge :tone="g.required ? 'warn' : 'neutral'" size="sm">
                                    {{ g.required ? 'Requerido' : 'Opcional' }}
                                </Badge>
                                <span class="text-[10px] text-[var(--color-fg-dim)] ml-auto">
                                    {{ g.selection_type === 'single' ? 'Elegí uno' : 'Elegí varios' }}
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="opt in g.options"
                                    :key="opt.id"
                                    type="button"
                                    class="h-12 px-3 rounded-xl text-sm font-medium transition border text-left flex items-center justify-between"
                                    :class="isSelected(g.id, opt.id)
                                        ? 'bg-[var(--color-primary)] text-[oklch(15%_0.02_60)] border-transparent'
                                        : 'bg-[var(--color-surface)] border-[var(--color-border-faint)] hover:border-[var(--color-border)] text-[var(--color-fg)]'"
                                    @click="toggleOption(g, opt.id)"
                                >
                                    <span>{{ opt.name }}</span>
                                    <span v-if="opt.price_delta" class="text-[10px] opacity-75">{{ priceDelta(opt.price_delta) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 pb-6 pt-4 border-t border-[var(--color-border-faint)] flex gap-3">
                        <Button variant="ghost" class="flex-1" @click="pending = null">Cancelar</Button>
                        <Button class="flex-1" :disabled="!canConfirm()" @click="confirmPicker">Agregar</Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
