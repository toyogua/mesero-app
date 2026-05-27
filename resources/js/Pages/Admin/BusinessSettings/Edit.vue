<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    business_name:              props.settings.business_name ?? '',
    address:                    props.settings.address ?? '',
    phone:                      props.settings.phone ?? '',
    display_pin:                props.settings.display_pin ?? '',
    logo:                       null,
    online_ordering_enabled:    props.settings.online_ordering_enabled ?? true,
    online_ordering_min_amount: props.settings.online_ordering_min_amount ?? '',
});

const logoPreview = ref(props.settings.logo_url ?? null);
const logoInput   = ref(null);

function onLogoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    form.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function removeLogo() {
    if (props.settings.logo_url) {
        router.delete('/admin/settings/logo', { preserveScroll: true });
    }
    form.logo = null;
    logoPreview.value = null;
    if (logoInput.value) logoInput.value.value = '';
}

function submit() {
    form.post('/admin/settings', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Configuración del negocio" />
    <AppLayout title="Configuración">
        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="flex flex-col gap-6">

                <!-- Logo -->
                <section class="rounded-xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5">
                    <h2 class="text-sm font-semibold mb-4">Logo del negocio</h2>

                    <div class="flex items-start gap-5">
                        <!-- Preview -->
                        <div
                            class="w-24 h-24 rounded-xl border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] flex items-center justify-center shrink-0 overflow-hidden"
                        >
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="w-full h-full object-contain p-1" />
                            <span v-else class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">Sin logo</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2">
                            <label class="cursor-pointer">
                                <input
                                    ref="logoInput"
                                    type="file"
                                    accept="image/*"
                                    class="sr-only"
                                    @change="onLogoChange"
                                />
                                <span class="inline-flex h-9 items-center gap-2 px-4 rounded-lg border border-[var(--color-border)] text-sm font-medium hover:bg-[var(--color-surface-up)] transition">
                                    Subir imagen
                                </span>
                            </label>
                            <button
                                v-if="logoPreview"
                                type="button"
                                class="text-xs text-[var(--color-fg-dim)] hover:text-[var(--color-err)] transition text-left"
                                @click="removeLogo"
                            >
                                Eliminar logo
                            </button>
                            <p class="text-xs text-[var(--color-fg-dim)]">PNG, JPG o SVG · máx. 2 MB</p>
                        </div>
                    </div>
                    <p v-if="form.errors.logo" class="mt-2 text-xs text-[var(--color-err)]">{{ form.errors.logo }}</p>
                </section>

                <!-- Datos del negocio -->
                <section class="rounded-xl border border-[var(--color-border-faint)] bg-[var(--color-surface)] p-5">
                    <h2 class="text-sm font-semibold mb-4">Datos del negocio</h2>

                    <div class="flex flex-col gap-4">
                        <!-- Nombre -->
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                                Nombre del negocio
                            </label>
                            <input
                                v-model="form.business_name"
                                type="text"
                                maxlength="120"
                                placeholder="Restaurante El Buen Sabor"
                                class="w-full h-10 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                            />
                            <p v-if="form.errors.business_name" class="mt-1 text-xs text-[var(--color-err)]">{{ form.errors.business_name }}</p>
                        </div>

                        <!-- Dirección -->
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                                Dirección
                            </label>
                            <input
                                v-model="form.address"
                                type="text"
                                maxlength="255"
                                placeholder="6a Av. 12-34, Zona 1, Guatemala"
                                class="w-full h-10 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                            />
                            <p v-if="form.errors.address" class="mt-1 text-xs text-[var(--color-err)]">{{ form.errors.address }}</p>
                        </div>

                        <!-- Teléfono -->
                        <div>
                            <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                                Teléfono
                            </label>
                            <input
                                v-model="form.phone"
                                type="tel"
                                maxlength="30"
                                placeholder="2222-3333"
                                class="w-full h-10 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-[var(--color-err)]">{{ form.errors.phone }}</p>
                        </div>
                    </div>
                </section>

                <!-- PIN Display -->
                <section class="rounded-xl border border-[var(--color-border-faint)] p-6 space-y-4">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-[var(--color-fg-dim)]">
                        Display de órdenes
                    </h2>
                    <div class="max-w-xs">
                        <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                            PIN de acceso
                        </label>
                        <input
                            v-model="form.display_pin"
                            type="text"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="Dejar vacío para no requerir PIN"
                            class="w-full h-10 px-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                        />
                        <p class="mt-1.5 text-xs text-[var(--color-fg-dim)]">
                            Acceso en <span class="font-numeric">/display</span>
                        </p>
                        <p v-if="form.errors.display_pin" class="mt-1 text-xs text-[var(--color-err)]">{{ form.errors.display_pin }}</p>
                    </div>
                </section>

                <!-- Ordenar en línea -->
                <section class="rounded-xl border border-[var(--color-border-faint)] p-6 space-y-5">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-[var(--color-fg-dim)]">
                        Ordenar en línea
                    </h2>

                    <!-- Toggle habilitado -->
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium">Recibir órdenes web</div>
                            <div class="text-xs text-[var(--color-fg-dim)] mt-0.5">
                                Disponible en <span class="font-numeric">/order</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.online_ordering_enabled"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
                            :class="form.online_ordering_enabled
                                ? 'bg-[var(--color-primary)]'
                                : 'bg-[var(--color-surface-up)]'"
                            @click="form.online_ordering_enabled = !form.online_ordering_enabled"
                        >
                            <span
                                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200"
                                :class="form.online_ordering_enabled ? 'translate-x-5' : 'translate-x-0'"
                            />
                        </button>
                    </div>

                    <!-- Monto mínimo -->
                    <div class="max-w-xs">
                        <label class="block text-xs uppercase tracking-widest text-[var(--color-fg-dim)] mb-1.5">
                            Monto mínimo de orden
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[var(--color-fg-dim)] font-numeric select-none">Q</span>
                            <input
                                v-model="form.online_ordering_min_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00 — sin mínimo"
                                class="w-full h-10 pl-8 pr-3 rounded-lg border border-[var(--color-border-faint)] bg-[var(--color-surface-down)] text-sm focus:outline-none focus:border-[var(--color-primary)] transition"
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-[var(--color-fg-dim)]">
                            Dejar vacío para no requerir mínimo
                        </p>
                        <p v-if="form.errors.online_ordering_min_amount" class="mt-1 text-xs text-[var(--color-err)]">
                            {{ form.errors.online_ordering_min_amount }}
                        </p>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :loading="form.processing">Guardar configuración</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
