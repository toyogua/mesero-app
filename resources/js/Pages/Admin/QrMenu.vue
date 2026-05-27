<script setup>
import { ref, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import QRCode from 'qrcode';

const page    = usePage();
const menuUrl = `${window.location.origin}/menu`;
const canvas  = ref(null);
const copied  = ref(false);

onMounted(async () => {
    if (canvas.value) {
        await QRCode.toCanvas(canvas.value, menuUrl, {
            width: 256,
            margin: 2,
            color: { dark: '#000000', light: '#FFFFFF' },
        });
    }
});

async function copyUrl() {
    await navigator.clipboard.writeText(menuUrl);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function downloadQr() {
    if (!canvas.value) return;
    const link = document.createElement('a');
    link.download = 'menu-qr.png';
    link.href = canvas.value.toDataURL('image/png');
    link.click();
}

function printQr() {
    window.print();
}
</script>

<template>
    <Head title="QR Menú — Admin" />
    <AppLayout title="Código QR del menú">

        <div class="max-w-lg mx-auto">

            <div class="rounded-2xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-8 text-center">
                <!-- QR canvas -->
                <div class="inline-block rounded-2xl overflow-hidden bg-white p-4 mb-6 shadow-sm">
                    <canvas ref="canvas" />
                </div>

                <!-- URL -->
                <div class="mb-6">
                    <div class="text-xs text-[var(--color-fg-muted)] mb-2 uppercase tracking-widest">URL del menú</div>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 rounded-lg bg-[var(--color-bg)] border border-[var(--color-border-faint)] text-sm text-left truncate">
                            {{ menuUrl }}
                        </code>
                        <button
                            type="button"
                            class="shrink-0 px-3 py-2 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-bg)] text-sm hover:bg-[var(--color-surface-up)] transition-colors"
                            @click="copyUrl"
                        >
                            {{ copied ? '✓ Copiado' : 'Copiar' }}
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 justify-center flex-wrap">
                    <button
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-[var(--color-primary)] text-[oklch(15%_0.02_60)] font-medium text-sm hover:opacity-90 transition-opacity"
                        @click="downloadQr"
                    >
                        Descargar PNG
                    </button>
                    <button
                        type="button"
                        class="px-5 py-2.5 rounded-xl border border-[var(--color-border-faint)] text-sm hover:bg-[var(--color-surface-up)] transition-colors"
                        @click="printQr"
                    >
                        Imprimir
                    </button>
                    <a
                        :href="menuUrl"
                        target="_blank"
                        class="px-5 py-2.5 rounded-xl border border-[var(--color-border-faint)] text-sm hover:bg-[var(--color-surface-up)] transition-colors"
                    >
                        Ver menú →
                    </a>
                </div>
            </div>

            <p class="mt-4 text-xs text-center text-[var(--color-fg-dim)]">
                Imprimí el QR y colocalo en cada mesa. Los comensales pueden escanearlo para ver el menú sin necesidad de descargarse ninguna app.
            </p>
        </div>

    </AppLayout>
</template>

<style>
@media print {
    header, nav, aside, footer, .print-hide { display: none !important; }
    main { padding: 0 !important; }
}
</style>
