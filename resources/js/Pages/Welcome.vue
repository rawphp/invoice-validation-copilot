<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { STATUS_TONE_CLASSES } from '@/types/design';

defineProps<{
    appName: string;
}>();
</script>

<template>
    <Head title="Welcome" />

    <AppLayout :title="appName" subtitle="Design system & layout shell preview.">
        <div class="grid gap-5 sm:grid-cols-2">
            <section
                class="rounded-lg border border-border-subtle bg-surface-lowest p-6"
            >
                <p
                    class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-secondary"
                >
                    Precision Ledger ready
                </p>
                <h2 class="mt-2 text-xl font-semibold text-on-surface">
                    Design tokens wired
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-on-surface-variant">
                    Surface, Deep Slate primary, Indigo secondary, Emerald and Amber
                    are theme tokens consumed via utilities — no per-component hex.
                    Inter drives the UI; JetBrains Mono marks technical strings.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span
                        v-for="(tone, key) in STATUS_TONE_CLASSES"
                        :key="key"
                        class="rounded-full px-3 py-1 font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                        :class="[tone.text, tone.bg]"
                    >
                        {{ key }}
                    </span>
                </div>
            </section>

            <section
                class="rounded-lg border border-border-subtle bg-code-surface p-6"
            >
                <p
                    class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-outline-variant"
                >
                    Structured JSON
                </p>
                <pre
                    class="mt-3 overflow-x-auto font-mono text-[13px] leading-[18px] text-code-on-surface"
                >{{ '{\n  "supplier": "Acme Pty Ltd",\n  "abn": "51 824 753 556",\n  "gst": 42.00,\n  "total": 462.00\n}' }}</pre>
            </section>
        </div>
    </AppLayout>
</template>
