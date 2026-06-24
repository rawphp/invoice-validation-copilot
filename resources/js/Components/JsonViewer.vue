<script setup lang="ts">
/**
 * JsonViewer — a collapsible, copyable block of the structured result JSON.
 *
 * Per DESIGN.md: a DARK block (Slate 900 / `bg-code-surface`) even in light
 * mode, using the `data-mono` JetBrains Mono typeface, to differentiate
 * code-level data from user-interface data.
 */
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Any JSON-serializable value to pretty-print. */
        value: unknown;
        /** Section label shown in the header. */
        label?: string;
        /** Whether the block starts expanded. */
        defaultOpen?: boolean;
    }>(),
    {
        label: 'Structured JSON',
        defaultOpen: true,
    },
);

const open = ref<boolean>(props.defaultOpen);
const copied = ref<boolean>(false);

const json = computed<string>(() => JSON.stringify(props.value, null, 2));

function toggle(): void {
    open.value = !open.value;
}

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(json.value);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <section
        class="overflow-hidden rounded-lg border border-border-subtle bg-code-surface"
        aria-label="Structured JSON payload"
    >
        <header
            class="flex items-center justify-between border-b border-white/10 px-4 py-3"
        >
            <button
                type="button"
                class="flex items-center gap-2 font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-outline-variant"
                :aria-expanded="open"
                @click="toggle"
            >
                <span aria-hidden="true">{{ open ? '▾' : '▸' }}</span>
                {{ label }}
            </button>
            <button
                type="button"
                class="rounded-sm border border-white/15 px-2 py-1 font-mono text-[12px] font-medium text-code-on-surface transition-colors hover:bg-white/10"
                @click="copy"
            >
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
        </header>
        <pre
            v-show="open"
            class="overflow-x-auto px-4 py-4 font-mono text-[13px] leading-[18px] text-code-on-surface"
        >{{ json }}</pre>
    </section>
</template>
