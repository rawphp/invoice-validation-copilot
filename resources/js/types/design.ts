/**
 * Precision Ledger — shared design TS types.
 *
 * The design tokens themselves are the single source of truth in
 * `resources/css/app.css` (`@theme`). These types describe the *semantic
 * roles* components reference so token usage stays consistent and typed —
 * e.g. a status pill picks a `StatusTone`, never a raw hex.
 */

/** Semantic status tones mapped to Precision Ledger color roles. */
export type StatusTone = 'success' | 'warning' | 'error' | 'info' | 'neutral';

/** Severity used by validation results, ordered by escalation. */
export type Severity = 'info' | 'warning' | 'error';

/** Tailwind utility class sets for each status tone (token-backed, no hex). */
export interface StatusToneClasses {
    /** Text color utility, e.g. `text-success`. */
    text: string;
    /** Background utility, e.g. `bg-success-50`. */
    bg: string;
    /** Border/accent utility, e.g. `border-success`. */
    border: string;
}

/**
 * Precision Ledger status pill palette — uppercase chips per DESIGN.md.
 * Centralised so every component renders the same token-backed classes.
 */
export const STATUS_TONE_CLASSES: Record<StatusTone, StatusToneClasses> = {
    success: { text: 'text-success', bg: 'bg-success-50', border: 'border-success' },
    warning: { text: 'text-warning', bg: 'bg-warning-50', border: 'border-warning' },
    error: { text: 'text-error', bg: 'bg-error-container', border: 'border-error' },
    info: { text: 'text-secondary', bg: 'bg-indigo-50', border: 'border-secondary' },
    neutral: {
        text: 'text-on-surface-variant',
        bg: 'bg-surface-container',
        border: 'border-outline-variant',
    },
};

/** Map a validation severity to its status tone. */
export function toneForSeverity(severity: Severity): StatusTone {
    switch (severity) {
        case 'error':
            return 'error';
        case 'warning':
            return 'warning';
        case 'info':
        default:
            return 'info';
    }
}
