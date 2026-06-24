<script setup lang="ts">
/**
 * AuditTimeline — renders the per-step pipeline audit trail as a vertical
 * timeline. Each entry shows the step name, status (toned), duration, and any
 * model / token metadata as mono technical strings.
 */
import { computed } from 'vue';
import { STATUS_TONE_CLASSES, type StatusTone } from '@/types/design';
import type { ResultAuditEntry } from '@/types/result';

const props = defineProps<{
    /** Audit entries in insertion (chronological) order. */
    entries: ResultAuditEntry[];
}>();

const hasEntries = computed<boolean>(() => props.entries.length > 0);

function toneFor(status: string): StatusTone {
    const normalized = status.toLowerCase();

    if (normalized === 'ok' || normalized === 'success' || normalized === 'passed') {
        return 'success';
    }

    if (normalized === 'warning' || normalized === 'skipped') {
        return 'warning';
    }

    if (normalized === 'error' || normalized === 'failed') {
        return 'error';
    }

    return 'neutral';
}

function dotClasses(status: string): string {
    return STATUS_TONE_CLASSES[toneFor(status)].border;
}

function statusChipClasses(status: string): string[] {
    const tone = STATUS_TONE_CLASSES[toneFor(status)];

    return [tone.text, tone.bg];
}

function formatDuration(seconds: number): string {
    if (seconds < 1) {
        return `${Math.round(seconds * 1000)}ms`;
    }

    return `${seconds.toFixed(2)}s`;
}
</script>

<template>
    <section
        class="rounded-lg border border-border-subtle bg-surface-lowest p-6"
        aria-label="Audit timeline"
    >
        <h2 class="text-base font-semibold text-on-surface">Audit log</h2>

        <ol v-if="hasEntries" class="mt-4 space-y-0">
            <li
                v-for="(entry, i) in entries"
                :key="`audit-${i}`"
                class="relative flex gap-4 pb-5 last:pb-0"
            >
                <!-- connector -->
                <span
                    v-if="i < entries.length - 1"
                    class="absolute left-[5px] top-3 h-full w-px bg-border-subtle"
                    aria-hidden="true"
                />
                <span
                    class="relative z-10 mt-1 h-[11px] w-[11px] shrink-0 rounded-full border-[3px] bg-surface-lowest"
                    :class="dotClasses(entry.status)"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-on-surface">
                            {{ entry.step }}
                        </p>
                        <span
                            class="rounded-full px-2 py-0.5 font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                            :class="statusChipClasses(entry.status)"
                        >
                            {{ entry.status }}
                        </span>
                    </div>
                    <div
                        class="mt-1 flex flex-wrap gap-x-4 gap-y-1 font-mono text-[12px] text-on-surface-variant"
                    >
                        <span>{{ formatDuration(entry.duration) }}</span>
                        <span v-if="entry.model_id">{{ entry.model_id }}</span>
                        <span v-if="entry.input_tokens != null">
                            in {{ entry.input_tokens }}t
                        </span>
                        <span v-if="entry.output_tokens != null">
                            out {{ entry.output_tokens }}t
                        </span>
                        <span v-if="entry.error_count != null">
                            {{ entry.error_count }} err
                        </span>
                    </div>
                </div>
            </li>
        </ol>

        <p v-else class="mt-4 text-sm text-on-surface-variant">
            No audit entries recorded.
        </p>
    </section>
</template>
