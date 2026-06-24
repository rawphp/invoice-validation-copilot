<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    appName: string;
}>();
</script>

<template>
    <Head title="Welcome" />

    <main class="flex min-h-screen items-center justify-center bg-slate-900 p-6 text-slate-100">
        <div class="w-full max-w-xl rounded-md border border-slate-700 bg-slate-800/60 p-8 shadow-lg">
            <p class="text-xs font-medium uppercase tracking-widest text-indigo-400">
                Scaffold ready
            </p>
            <h1 class="mt-2 text-2xl font-semibold">
                {{ appName }}
            </h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-400">
                Laravel 13 + Inertia + Vue 3 (TypeScript) + Tailwind are wired and booting with no
                database. This placeholder page confirms the front-to-back pipeline renders.
            </p>
        </div>
    </main>
</template>
