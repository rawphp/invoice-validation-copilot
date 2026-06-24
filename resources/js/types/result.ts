/**
 * Precision Ledger — InvoiceResult payload types.
 *
 * These mirror `App\DTO\InvoiceResult::toArray()` EXACTLY (snake_case keys) so
 * the result page (REQ-017) renders the Inertia payload without any reshaping.
 * Kept local to the result feature — does NOT depend on the parallel invoice.ts.
 */
import type { Severity } from '@/types/design';

/** A single extracted invoice line (mirrors LineItem). */
export interface ResultLineItem {
    description: string;
    qty: number | null;
    rate: number | null;
    amount: number | null;
}

/** The structured extraction (mirrors InvoiceResult::invoiceToArray()). */
export interface ResultInvoice {
    supplier: string | null;
    abn: string | null;
    invoice_date: string | null;
    due_date: string | null;
    line_items: ResultLineItem[];
    subtotal: number | null;
    gst: number | null;
    total: number | null;
    service_category: string;
}

/** A deterministic validation finding (mirrors ValidationError). */
export interface ResultValidationError {
    field: string;
    severity: Severity;
    message: string;
}

/** Aggregate confidence verdict (mirrors ConfidenceResult). */
export interface ResultConfidence {
    overall: number;
    passed: boolean;
}

/** Per-field confidence entry (mirrors FieldConfidence). */
export interface ResultFieldConfidence {
    field: string;
    score: number;
}

/** Classified service category (mirrors ServiceCategory). */
export interface ResultCategory {
    value: string;
    label: string;
}

/** A single audit-trail step (mirrors AuditEntry::toArray()). */
export interface ResultAuditEntry {
    step: string;
    status: string;
    duration: number;
    model_id?: string;
    input_tokens?: number;
    output_tokens?: number;
    error_count?: number;
}

/** The full payload handed to Result.vue (mirrors InvoiceResult::toArray()). */
export interface InvoiceResultPayload {
    has_error: boolean;
    error_message: string | null;
    invoice: ResultInvoice | null;
    errors: ResultValidationError[];
    confidence: ResultConfidence | null;
    field_confidence: Record<string, ResultFieldConfidence>;
    category: ResultCategory | null;
    explanation: string | null;
    audit_entries: ResultAuditEntry[];
}
