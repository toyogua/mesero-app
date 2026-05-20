<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    title: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const nav = computed(() => {
    const role = user.value?.role;
    const items = [
        { label: 'Salón', href: '/floor', icon: 'grid', roles: ['waiter', 'admin'] },
        { label: 'Cocina', href: '/kitchen', icon: 'flame', roles: ['kitchen', 'admin'] },
        { label: 'Menú', href: '/menu', icon: 'book', roles: ['admin'] },
        { label: 'Reportes', href: '/reports', icon: 'chart', roles: ['admin'] },
    ];
    return items.filter((i) => !role || i.roles.includes(role));
});

const isActive = (href) => page.url.startsWith(href);

const open = ref(false);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen flex bg-[var(--color-bg)]">
        <!-- Sidebar -->
        <aside
            class="fixed lg:sticky top-0 left-0 h-screen z-40 transition-transform duration-300"
            :class="[
                open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <div class="w-64 h-full bg-[var(--color-surface-down)] border-r border-[var(--color-border-faint)] flex flex-col">
                <!-- Logo -->
                <div class="h-16 px-5 flex items-center gap-3 border-b border-[var(--color-border-faint)]">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-[oklch(15%_0.02_60)]"
                         style="background: linear-gradient(135deg, var(--color-primary), var(--color-primary-down));">
                        m
                    </div>
                    <div class="flex flex-col leading-tight">
                        <span class="text-sm font-semibold tracking-tight">mesero</span>
                        <span class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">app</span>
                    </div>
                </div>

                <!-- Nav -->
                <nav class="flex-1 px-3 py-4 space-y-1">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="group flex items-center gap-3 px-3 h-11 rounded-lg tap-target transition-all text-sm font-medium"
                        :class="[
                            isActive(item.href)
                                ? 'bg-[var(--color-surface-up)] text-[var(--color-fg)]'
                                : 'text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] hover:bg-[var(--color-surface)]',
                        ]"
                    >
                        <span class="w-1 h-5 rounded-full transition-all"
                              :class="isActive(item.href) ? 'bg-[var(--color-primary)]' : 'bg-transparent'" />
                        {{ item.label }}
                    </Link>
                </nav>

                <!-- User -->
                <div v-if="user" class="border-t border-[var(--color-border-faint)] p-3">
                    <div class="flex items-center gap-3 px-2 py-2">
                        <div class="w-9 h-9 rounded-full bg-[var(--color-surface-up)] flex items-center justify-center text-sm font-medium">
                            {{ user.name?.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">{{ user.name }}</div>
                            <div class="text-[10px] uppercase tracking-widest text-[var(--color-fg-dim)]">{{ user.role }}</div>
                        </div>
                        <button
                            type="button"
                            class="p-2 rounded-md text-[var(--color-fg-dim)] hover:text-[var(--color-fg)] hover:bg-[var(--color-surface)] focus-ring"
                            title="Salir"
                            @click="logout"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Overlay para móvil -->
        <div
            v-if="open"
            class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm lg:hidden"
            @click="open = false"
        />

        <!-- Main -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="sticky top-0 z-20 h-16 flex items-center gap-3 px-5 lg:px-8 liquid-glass">
                <button
                    type="button"
                    class="lg:hidden p-2 -ml-2 rounded-md tap-target hover:bg-[var(--color-surface)]"
                    @click="open = !open"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <h1 v-if="title" class="text-lg font-semibold tracking-tight">{{ title }}</h1>

                <div class="ml-auto flex items-center gap-2">
                    <slot name="actions" />
                </div>
            </header>

            <main class="flex-1 p-5 lg:p-8 grain">
                <slot />
            </main>
        </div>
    </div>
</template>
