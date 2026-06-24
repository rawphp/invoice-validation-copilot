<script setup lang="ts">
/**
 * ValidationPanel — groups deterministic validation findings by severity
 * (errors first, then warnings, then info notes) and lists the checks that passed.
 *
 * Mirrors example screen 2: a critical-error card, amber warning cards, and a
 * "passing checks" list with green Matched chips. All token-backed.
 *
 * Info-severity findings are rendered in a "Notes" section and excluded from
 * the issue count — an invoice with only an info note reads as "0 issues".
 */
import { computed } from 'vue';
import {
    STATUS_TONE_CLASSES,
    toneForSeverity,
    type Severity,
} from '@/types/design';
import type { ResultValidationError } from '@/types/result';

const props = defineProps<{
    /** All validation findings from the pipeline. */
    errors: ResultValidationError[];
    /** Human-readable names of the deterministic checks that passed. */
    passingChecks?: string[];
}>();

const blockingErrors = computed<ResultValidationError[]>(() =>
    props.errors.filter((e) => e.severity === 'error'),
);

const warnings = computed<ResultValidationError[]>(() =>
    props.errors.filter((e) => e.severity === 'warning'),
);

const infoNotes = computed<ResultValidationError[]>(() =>
    props.errors.filter((e) => e.severity === 'info'),
);

/** Issue count excludes info-severity findings — they are notes, not issues. */
const issueCount = computed<number>(
    () => blockingErrors.value.length + warnings.value.length,
);

const successTone = STATUS_TONE_CLASSES.success;

function cardClasses(severity: Severity): string[] {
    const tone = STATUS_TONE_CLASSES[toneForSeverity(severity)];

    return [tone.bg, tone.border];
}

function labelClasses(severity: Severity): string {
    return STATUS_TONE_CLASSES[toneForSeverity(severity)].text;
}
</script>

<template>
    <section
        class="rounded-lg border border-border-subtle bg-surface-lowest p-6"
        aria-label="Validation results"
    >
        <header class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-on-surface">Validation</h2>
            <span
                class="rounded-full px-3 py-1 font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                :class="
                    blockingErrors.length > 0
                        ? [STATUS_TONE_CLASSES.error.text, STATUS_TONE_CLASSES.error.bg]
                        : warnings.length > 0
                          ? [STATUS_TONE_CLASSES.warning.text, STATUS_TONE_CLASSES.warning.bg]
                          : [successTone.text, successTone.bg]
                "
            >
                {{ issueCount }}
                {{ issueCount === 1 ? 'issue' : 'issues' }}
            </span>
        </header>

        <!-- Blocking errors -->
        <div v-if="blockingErrors.length > 0" class="mt-4 space-y-3">
            <p
                class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-error"
            >
                Critical errors
            </p>
            <article
                v-for="(error, i) in blockingErrors"
                :key="`error-${i}`"
                class="rounded-lg border-l-4 p-4"
                :class="cardClasses('error')"
            >
                <p
                    class="font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                    :class="labelClasses('error')"
                >
                    {{ error.field }}
                </p>
                <p class="mt-1 text-sm text-on-surface">{{ error.message }}</p>
            </article>
        </div>

        <!-- Warnings -->
        <div v-if="warnings.length > 0" class="mt-4 space-y-3">
            <p
                class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-warning"
            >
                Warnings
            </p>
            <article
                v-for="(warning, i) in warnings"
                :key="`warning-${i}`"
                class="rounded-lg border-l-4 p-4"
                :class="cardClasses('warning')"
            >
                <p
                    class="font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                    :class="labelClasses('warning')"
                >
                    {{ warning.field }}
                </p>
                <p class="mt-1 text-sm text-on-surface">{{ warning.message }}</p>
            </article>
        </div>

        <!-- Notes (info-severity findings) -->
        <div v-if="infoNotes.length > 0" class="mt-4 space-y-3">
            <p
                class="font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                :class="labelClasses('info')"
            >
                Notes
            </p>
            <article
                v-for="(note, i) in infoNotes"
                :key="`info-${i}`"
                class="rounded-lg border-l-4 p-4"
                :class="cardClasses('info')"
            >
                <p
                    class="font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                    :class="labelClasses('info')"
                >
                    {{ note.field }}
                </p>
                <p class="mt-1 text-sm text-on-surface">{{ note.message }}</p>
            </article>
        </div>

        <!-- Passing checks -->
        <div v-if="passingChecks && passingChecks.length > 0" class="mt-6">
            <p
                class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-on-surface-variant"
            >
                Passing checks ({{ passingChecks.length }})
            </p>
            <ul class="mt-3 space-y-2">
                <li
                    v-for="(check, i) in passingChecks"
                    :key="`pass-${i}`"
                    class="flex items-center justify-between rounded-md border border-border-subtle bg-surface-low px-3 py-2"
                >
                    <span class="text-sm text-on-surface">{{ check }}</span>
                    <span
                        class="rounded-full px-2 py-0.5 font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                        :class="[successTone.text, successTone.bg]"
                    >
                        Matched
                    </span>
                </li>
            </ul>
        </div>

        <p
            v-if="errors.length === 0 && (!passingChecks || passingChecks.length === 0)"
            class="mt-4 text-sm text-on-surface-variant"
        >
            No validation findings.
        </p>
    </section>
</template>
