<script setup lang="ts">
/**
 * AppLayout — the Precision Ledger application shell.
 *
 * Every page (Upload, Result) renders inside this layout. It provides the
 * surface background, the product header, and a centred content container.
 * All colors/fonts come from theme tokens (app.css `@theme`) — no hardcoded hex.
 */
withDefaults(
    defineProps<{
        /** Page heading shown in the header eyebrow/title area. */
        title?: string;
        /** Short supporting line under the title. */
        subtitle?: string;
    }>(),
    {
        title: 'Invoice Validation Copilot',
        subtitle: 'Precision invoice extraction, validation & explanation.',
    },
);
</script>

<template>
    <div class="min-h-screen bg-surface font-sans text-on-surface antialiased">
        <header class="border-b border-border-subtle bg-surface-lowest">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-8 py-4"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary font-mono text-sm font-medium text-on-primary"
                        aria-hidden="true"
                    >
                        IV
                    </span>
                    <div class="leading-tight">
                        <p
                            class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-secondary"
                        >
                            Precision Ledger
                        </p>
                        <h1 class="text-base font-semibold text-on-surface">
                            {{ title }}
                        </h1>
                    </div>
                </div>
                <span
                    class="hidden rounded-full bg-success-50 px-3 py-1 font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-success sm:inline-block"
                >
                    AU · GST
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-8 py-section">
            <p v-if="subtitle" class="mb-6 text-sm text-on-surface-variant">
                {{ subtitle }}
            </p>
            <slot />
        </main>
    </div>
</template>
