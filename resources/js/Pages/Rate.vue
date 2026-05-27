<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    invalid:      { type: Boolean, default: false },
    already_rated:{ type: Boolean, default: false },
    check:        { type: Object,  default: null },
    business:     { type: Object,  default: null },
    post_url:     { type: String,  default: null },
});

const selected = ref(0);
const comment  = ref('');
const sending  = ref(false);
const done     = ref(props.already_rated);

const LABELS = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', '¡Excelente!'];

function submit() {
    if (!selected.value) return;
    sending.value = true;
    router.post(
        props.post_url,
        { stars: selected.value, comment: comment.value },
        {
            preserveScroll: true,
            onSuccess: () => { done.value = true; },
            onFinish:  () => { sending.value = false; },
        },
    );
}
</script>

<template>
    <Head title="Calificá tu experiencia" />

    <div class="min-h-screen bg-[#080808] text-white flex flex-col items-center justify-center p-6 select-none">

        <!-- Enlace inválido -->
        <template v-if="invalid">
            <div class="text-center max-w-xs">
                <div class="text-5xl mb-4">🔗</div>
                <h1 class="text-xl font-semibold tracking-tight mb-2">Enlace inválido</h1>
                <p class="text-sm text-white/40">Este enlace ya expiró o no es válido.</p>
            </div>
        </template>

        <!-- Ya calificó / gracias -->
        <template v-else-if="done">
            <div class="text-center max-w-xs">
                <div class="text-6xl mb-5">🙌</div>
                <h1 class="text-2xl font-bold tracking-tight mb-2">¡Gracias!</h1>
                <p class="text-sm text-white/50">Tu opinión nos ayuda a mejorar cada día.</p>
            </div>
        </template>

        <!-- Formulario -->
        <template v-else>
            <div class="w-full max-w-sm">
                <!-- Cabecera -->
                <div class="text-center mb-8">
                    <p class="text-xs uppercase tracking-[0.2em] text-white/30 mb-1">
                        {{ business?.name }}
                    </p>
                    <h1 class="text-2xl font-bold tracking-tight">¿Cómo fue tu experiencia?</h1>
                    <p class="text-sm text-white/40 mt-1">
                        Orden {{ check?.number }}
                        <span v-if="check?.order_type === 'takeout'"> · Para llevar</span>
                    </p>
                </div>

                <!-- Estrellas -->
                <div class="flex justify-center gap-3 mb-3">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        class="text-5xl transition-transform active:scale-90"
                        :class="n <= selected ? 'opacity-100' : 'opacity-20'"
                        @click="selected = n"
                    >
                        ⭐
                    </button>
                </div>
                <div class="text-center text-sm font-medium text-white/60 h-5 mb-6 transition-all">
                    {{ selected ? LABELS[selected] : '' }}
                </div>

                <!-- Comentario -->
                <div class="mb-6">
                    <textarea
                        v-model="comment"
                        rows="3"
                        placeholder="Contanos algo más (opcional)..."
                        class="w-full rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder-white/20 px-4 py-3 resize-none focus:outline-none focus:border-white/30 transition"
                    />
                </div>

                <!-- Submit -->
                <button
                    type="button"
                    class="w-full h-12 rounded-xl font-semibold text-sm uppercase tracking-widest transition disabled:opacity-30"
                    :class="selected ? 'bg-white text-black hover:bg-white/90' : 'bg-white/10 text-white/30 cursor-not-allowed'"
                    :disabled="!selected || sending"
                    @click="submit"
                >
                    {{ sending ? 'Enviando…' : 'Enviar calificación' }}
                </button>
            </div>
        </template>
    </div>
</template>
