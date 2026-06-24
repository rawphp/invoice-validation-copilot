<script setup lang="ts">
/**
 * ConfidenceChip — a small uppercase status chip showing a per-field confidence
 * score as a percentage, toned by how trustworthy the read is.
 *
 *   high (>= 0.85)  → success (Emerald)
 *   medium (>= 0.5) → warning (Amber)
 *   low (< 0.5)     → error
 *
 * Token-backed via STATUS_TONE_CLASSES — never a raw hex.
 */
import { computed } from 'vue';
import { STATUS_TONE_CLASSES, type StatusTone } from '@/types/design';

const props = withDefaults(
    defineProps<{
        /** Confidence score in [0, 1]. */
        score: number;
        /** High-confidence threshold (inclusive). */
        highThreshold?: number;
        /** Medium-confidence threshold (inclusive). */
        mediumThreshold?: number;
    }>(),
    {
        highThreshold: 0.85,
        mediumThreshold: 0.5,
    },
);

const tone = computed<StatusTone>(() => {
    if (props.score >= props.highThreshold) {
        return 'success';
    }

    if (props.score >= props.mediumThreshold) {
        return 'warning';
    }

    return 'error';
});

const percent = computed<number>(() =>
    Math.round(Math.max(0, Math.min(1, props.score)) * 100),
);

const toneClasses = computed(() => STATUS_TONE_CLASSES[tone.value]);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
        :class="[toneClasses.text, toneClasses.bg]"
        :title="`Confidence ${percent}%`"
    >
        {{ percent }}%
    </span>
</template>
