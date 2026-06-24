<script setup lang="ts">
/**
 * Result.vue — the single-page validation result for one uploaded invoice.
 *
 * Renders the full `InvoiceResult::toArray()` payload (Inertia-rendered by
 * InvoiceController, REQ-016) using Precision Ledger components: overall
 * confidence + pass/fail badge, extracted fields with per-field confidence
 * chips, a line-items table, the validation panel, the supplier explanation
 * card, a dark copyable JSON viewer, and the audit timeline.
 *
 * When the pipeline returns a friendly error (`has_error`), only the error
 * message is shown — never a stack trace.
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { STATUS_TONE_CLASSES } from '@/types/design';
import ConfidenceChip from '@/Components/ConfidenceChip.vue';
import ValidationPanel from '@/Components/ValidationPanel.vue';
import JsonViewer from '@/Components/JsonViewer.vue';
import AuditTimeline from '@/Components/AuditTimeline.vue';
import type {
    InvoiceResultPayload,
    ResultFieldConfidence,
} from '@/types/result';

const props = defineProps<{
    result: InvoiceResultPayload;
}>();

/** Label + payload key for each scalar field shown with a confidence chip. */
const scalarFields: { key: string; label: string }[] = [
    { key: 'supplier', label: 'Supplier' },
    { key: 'abn', label: 'ABN' },
    { key: 'invoice_date', label: 'Invoice date' },
    { key: 'due_date', label: 'Due date' },
    { key: 'subtotal', label: 'Subtotal' },
    { key: 'gst', label: 'GST' },
    { key: 'total', label: 'Total' },
];

const moneyFields = new Set(['subtotal', 'gst', 'total']);

const passed = computed<boolean>(() => props.result.confidence?.passed ?? false);

const overallPercent = computed<number>(() =>
    Math.round((props.result.confidence?.overall ?? 0) * 100),
);

const statusTone = computed(() =>
    passed.value ? STATUS_TONE_CLASSES.success : STATUS_TONE_CLASSES.error,
);

/** Field names that carry a blocking/warning finding — used to derive passing checks. */
const flaggedFields = computed<Set<string>>(
    () => new Set(props.result.errors.map((e) => e.field)),
);

/**
 * Passing checks: the validation severity-bearing fields that produced NO
 * finding. Field names are camelCase on errors; we surface the human label.
 */
const passingChecks = computed<string[]>(() => {
    const checks: string[] = [];
    const checkable: Record<string, string> = {
        abn: 'ABN format',
        invoiceDate: 'Invoice date present',
        dueDate: 'Due date present',
        total: 'Arithmetic total match',
        gst: 'GST calculation',
        lineItems: 'Line items present',
    };

    for (const [field, label] of Object.entries(checkable)) {
        if (!flaggedFields.value.has(field)) {
            checks.push(label);
        }
    }

    return checks;
});

function fieldValue(key: string): string {
    const invoice = props.result.invoice;

    if (!invoice) {
        return '—';
    }

    const raw = (invoice as unknown as Record<string, unknown>)[key];

    if (raw === null || raw === undefined || raw === '') {
        return '—';
    }

    if (moneyFields.has(key) && typeof raw === 'number') {
        return formatMoney(raw);
    }

    return String(raw);
}

function confidenceFor(key: string): ResultFieldConfidence | null {
    return props.result.field_confidence[key] ?? null;
}

function formatMoney(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('en-AU', {
        style: 'currency',
        currency: 'AUD',
    }).format(value);
}

function formatNumber(value: number | null): string {
    return value === null ? '—' : String(value);
}
</script>

<template>
    <Head title="Validation result" />

    <AppLayout
        title="Validation result"
        subtitle="Extraction, validation, confidence & explanation for this invoice."
    >
        <!-- Friendly error state -->
        <section
            v-if="result.has_error"
            class="rounded-lg border-l-4 border-error bg-error-container p-6"
            aria-label="Processing error"
        >
            <p
                class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-error"
            >
                Could not process this document
            </p>
            <p class="mt-2 text-sm text-on-surface">
                {{ result.error_message }}
            </p>
        </section>

        <div v-else class="space-y-section">
            <!-- Overall confidence + pass/fail badge -->
            <section
                class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border-subtle bg-surface-lowest p-6"
            >
                <div>
                    <p
                        class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-secondary"
                    >
                        Overall confidence
                    </p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-on-surface">
                        {{ overallPercent }}%
                    </p>
                    <p
                        v-if="result.category"
                        class="mt-1 text-sm text-on-surface-variant"
                    >
                        {{ result.category.label }}
                    </p>
                </div>
                <span
                    class="rounded-full px-4 py-1.5 font-mono text-[12px] font-bold uppercase tracking-[0.05em]"
                    :class="[statusTone.text, statusTone.bg]"
                >
                    {{ passed ? 'Passed' : 'Failed' }}
                </span>
            </section>

            <div class="grid gap-section lg:grid-cols-2">
                <!-- Extracted fields with confidence chips -->
                <section
                    class="rounded-lg border border-border-subtle bg-surface-lowest p-6"
                    aria-label="Extracted fields"
                >
                    <h2 class="text-base font-semibold text-on-surface">
                        Extracted fields
                    </h2>
                    <dl class="mt-4 divide-y divide-border-subtle">
                        <div
                            v-for="field in scalarFields"
                            :key="field.key"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <dt class="text-sm text-on-surface-variant">
                                {{ field.label }}
                            </dt>
                            <dd class="flex items-center gap-2">
                                <span
                                    class="font-mono text-[13px] text-on-surface"
                                >
                                    {{ fieldValue(field.key) }}
                                </span>
                                <ConfidenceChip
                                    v-if="confidenceFor(field.key)"
                                    :score="confidenceFor(field.key)!.score"
                                />
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Validation panel -->
                <ValidationPanel
                    :errors="result.errors"
                    :passing-checks="passingChecks"
                />
            </div>

            <!-- Line-items table -->
            <section
                class="overflow-hidden rounded-lg border border-border-subtle bg-surface-lowest"
                aria-label="Line items"
            >
                <h2 class="px-6 pt-6 text-base font-semibold text-on-surface">
                    Line items
                </h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-y border-border-subtle bg-surface-low font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-on-surface-variant"
                        >
                            <tr>
                                <th class="px-6 py-3 font-bold">Description</th>
                                <th class="px-6 py-3 text-right font-bold">Qty</th>
                                <th class="px-6 py-3 text-right font-bold">Rate</th>
                                <th class="px-6 py-3 text-right font-bold">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            <tr
                                v-for="(item, i) in result.invoice?.line_items ?? []"
                                :key="`line-${i}`"
                                class="transition-colors hover:bg-surface-low"
                            >
                                <td class="px-6 py-3 text-on-surface">
                                    {{ item.description }}
                                </td>
                                <td
                                    class="px-6 py-3 text-right font-mono text-on-surface-variant"
                                >
                                    {{ formatNumber(item.qty) }}
                                </td>
                                <td
                                    class="px-6 py-3 text-right font-mono text-on-surface-variant"
                                >
                                    {{ formatMoney(item.rate) }}
                                </td>
                                <td
                                    class="px-6 py-3 text-right font-mono text-on-surface"
                                >
                                    {{ formatMoney(item.amount) }}
                                </td>
                            </tr>
                            <tr v-if="(result.invoice?.line_items.length ?? 0) === 0">
                                <td
                                    class="px-6 py-4 text-sm text-on-surface-variant"
                                    colspan="4"
                                >
                                    No line items extracted.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Supplier explanation card -->
            <section
                v-if="result.explanation"
                class="rounded-lg border border-secondary/30 bg-indigo-50 p-6"
                aria-label="Explanation"
            >
                <p
                    class="font-mono text-[12px] font-bold uppercase tracking-[0.05em] text-secondary"
                >
                    Explanation
                </p>
                <p class="mt-2 text-sm leading-relaxed text-on-surface">
                    {{ result.explanation }}
                </p>
            </section>

            <!-- Audit timeline -->
            <AuditTimeline :entries="result.audit_entries" />

            <!-- Structured JSON (dark, copyable) -->
            <JsonViewer :value="result" label="Structured JSON" />
        </div>
    </AppLayout>
</template>
