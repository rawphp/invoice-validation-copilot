/**
 * Precision Ledger — shared invoice TS types.
 *
 * These mirror the PHP DTOs (`app/DTO/`) that Inertia serialises to the
 * frontend. Keep them in sync when the PHP shapes change.
 *
 * Used by: Upload.vue (form shape), Result.vue (result page props).
 */

/** A single extracted line item from the invoice. */
export interface LineItem {
    description: string | null;
    quantity: number | null;
    unitPrice: number | null;
    amount: number | null;
}

/** Per-field extraction confidence score in [0, 1]. */
export interface FieldConfidence {
    field: string;
    score: number;
}

/** A single validation finding (error or warning). */
export interface ValidationError {
    field: string;
    severity: 'error' | 'warning';
    message: string;
}

/** Aggregate confidence score from the confidence scorer step. */
export interface ConfidenceResult {
    overall: number;
    passed: boolean;
}

/** The structured invoice extraction — mirrors `ExtractedInvoice` PHP DTO. */
export interface ExtractedInvoice {
    supplier: string | null;
    abn: string | null;
    invoiceDate: string | null;
    dueDate: string | null;
    lineItems: LineItem[];
    subtotal: number | null;
    gst: number | null;
    total: number | null;
    serviceCategory: string;
    /** Per-field confidence keyed by scalar field name. */
    confidence: Record<string, FieldConfidence>;
}

/** A single pipeline audit entry (one per step). */
export interface AuditEntry {
    step: string;
    durationMs: number;
    [key: string]: unknown;
}

/**
 * The full pipeline result payload Inertia passes to the Result page.
 * Mirrors `InvoiceResult::toArray()` in `app/DTO/InvoiceResult.php`.
 *
 * When `hasError` is `true`, only `errorMessage` is meaningful — all other
 * fields will be `null` / empty.
 */
export interface InvoiceResult {
    invoice: ExtractedInvoice | null;
    errors: ValidationError[];
    confidence: ConfidenceResult | null;
    fieldConfidence: Record<string, FieldConfidence>;
    category: string | null;
    explanation: string | null;
    auditEntries: AuditEntry[];
    hasError: boolean;
    errorMessage: string | null;
}

/**
 * Props accepted by the Result Inertia page (REQ-017).
 * The `result` key matches `Inertia::render('Result', ['result' => …])`.
 */
export interface ResultPageProps {
    result: InvoiceResult;
}

/** Accepted MIME types for invoice upload. */
export const ACCEPTED_MIME_TYPES = ['application/pdf', 'image/png', 'image/jpeg'] as const;

/** Accepted file extensions (for the HTML `accept` attribute). */
export const ACCEPTED_EXTENSIONS = '.pdf,.png,.jpg,.jpeg';

/** Maximum upload size in bytes (20 MB — matches server-side `ValidateInvoiceRequest`). */
export const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;

/** Human-readable maximum upload size label. */
export const MAX_FILE_SIZE_LABEL = '20 MB';
