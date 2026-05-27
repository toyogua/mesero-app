<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const form = useForm({ pin: '' });
const digits = ref(['', '', '', '']);

function press(d) {
    const idx = digits.value.findIndex(v => v === '');
    if (idx === -1) return;
    digits.value[idx] = String(d);
    form.pin = digits.value.join('');
    if (digits.value.every(v => v !== '')) {
        submit();
    }
}

function del() {
    const idx = [...digits.value].reverse().findIndex(v => v !== '');
    if (idx === -1) return;
    digits.value[digits.value.length - 1 - idx] = '';
    form.pin = digits.value.join('').replace(/\s/g, '');
}

function submit() {
    form.post('/display/unlock', {
        onError: () => {
            digits.value = ['', '', '', ''];
            form.pin = '';
        },
    });
}
</script>

<template>
    <Head title="Display — Acceso" />

    <div class="min-h-screen bg-[#0a0a0a] flex flex-col items-center justify-center gap-10">

        <div class="text-center">
            <div class="text-white/40 text-sm uppercase tracking-widest mb-2">Display de órdenes</div>
            <h1 class="text-white text-3xl font-semibold tracking-tight">Ingresá el PIN</h1>
        </div>

        <!-- Indicadores -->
        <div class="flex gap-4">
            <div
                v-for="(d, i) in digits"
                :key="i"
                class="w-14 h-14 rounded-2xl border-2 flex items-center justify-center text-2xl font-bold font-numeric transition-colors"
                :class="d ? 'border-amber-400 text-amber-300 bg-amber-400/10' : 'border-white/20 text-white/20'"
            >
                {{ d ? '●' : '○' }}
            </div>
        </div>

        <div v-if="form.errors.pin" class="text-red-400 text-sm">
            {{ form.errors.pin }}
        </div>

        <!-- Teclado numérico -->
        <div class="grid grid-cols-3 gap-3 w-64">
            <button
                v-for="n in [1,2,3,4,5,6,7,8,9]"
                :key="n"
                type="button"
                class="h-16 rounded-2xl bg-white/5 hover:bg-white/10 active:scale-95 text-white text-2xl font-semibold font-numeric transition"
                @click="press(n)"
            >
                {{ n }}
            </button>
            <div></div>
            <button
                type="button"
                class="h-16 rounded-2xl bg-white/5 hover:bg-white/10 active:scale-95 text-white text-2xl font-semibold font-numeric transition"
                @click="press(0)"
            >
                0
            </button>
            <button
                type="button"
                class="h-16 rounded-2xl bg-white/5 hover:bg-white/10 active:scale-95 text-white/60 text-xl transition"
                @click="del"
            >
                ⌫
            </button>
        </div>
    </div>
</template>
