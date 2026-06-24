<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\LineItem;
use App\DTO\ValidationError;

/**
 * Validates arithmetic consistency for standard-rated (10% GST) AU invoices.
 *
 * Three checks are performed within a small money tolerance (TOLERANCE_AUD)
 * to absorb legitimate per-line cent rounding:
 *
 *   (a) Line item amounts sum to the declared subtotal.
 *   (b) GST = 10% of subtotal.
 *   (c) Subtotal + GST = total.
 *
 * A discrepancy within {@see self::TOLERANCE_AUD} is silently absorbed.
 * Each failed check emits one error-severity {@see ValidationError} naming
 * the offending figure ('subtotal', 'gst', or 'total').
 *
 * Skips checks for fields that are null (missing fields are handled by
 * {@see RequiredFieldsValidator}).
 *
 * Check (a) is adjustment-aware: payment, credit, late fee, and other
 * adjustment memo rows are excluded from the charge-only sum. The invoice
 * passes check (a) when ANY of {full sum, charge-only sum} reconciles against
 * {subtotal, total}. When only the charge-only sum reconciles (and at least
 * one adjustment row exists), an info-severity note is emitted explaining the
 * gap (mirrors check (c) payment-allocated info note from REQ-021).
 */
final class ArithmeticValidator implements Validator
{
    /**
     * Maximum discrepancy (AUD) that is treated as acceptable rounding, not an error.
     *
     * Set to $0.02 — enough to absorb a 1c rounding on each of up to two
     * per-line cents, as commonly seen with AU GST calculations.
     */
    private const TOLERANCE_AUD = 0.02;

    /**
     * Standard AU GST rate.
     */
    private const GST_RATE = 0.10;

    /**
     * Adjustment keywords (case-insensitive substring match against description).
     * A line item whose description contains any of these is classified as an
     * adjustment (payment / credit / refund / post-subtotal fee) and excluded
     * from the charge-only sum used in check (a).
     *
     * @var string[]
     */
    private const ADJUSTMENT_KEYWORDS = [
        'payment',
        'paid',
        'credit',
        'refund',
        'balance',
        'deposit',
        'late fee',
        'late payment',
        'overpayment',
    ];

    /**
     * Returns true when the line item is an adjustment (memo row that should not
     * count toward the charge subtotal / total reconciliation in check (a)).
     *
     * A line is an adjustment when EITHER:
     *  - its amount is negative (e.g. "Payment received -$150.00"), OR
     *  - its description contains an adjustment keyword (e.g. "Late Fee +$6.00").
     */
    private function isAdjustment(LineItem $item): bool
    {
        if (($item->amount ?? 0.0) < 0.0) {
            return true;
        }

        $description = strtolower($item->description ?? '');
        foreach (self::ADJUSTMENT_KEYWORDS as $keyword) {
            if (str_contains($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ValidationError[]
     */
    public function validate(ExtractedInvoice $invoice): array
    {
        $errors = [];

        // Guard: nothing to check if key amounts are missing.
        if ($invoice->subtotal === null || $invoice->gst === null || $invoice->total === null) {
            return $errors;
        }

        // (a) Line items sum to subtotal (GST-exclusive) OR total (GST-inclusive).
        //
        // AU invoices may present line items either way; accept either reconciliation.
        // Adjustment memo rows (payments, credits, late fees) are excluded from the
        // charge-only sum — they should not be counted toward the charge reconciliation.
        //
        // Additive logic: the invoice passes when ANY of these reconcile within TOLERANCE_AUD:
        //   full sum vs subtotal | full sum vs total | charge-only sum vs subtotal | charge-only sum vs total
        //
        // When only the charge-only sum reconciles (and adjustment lines are present):
        //   suppress the error, emit one info-severity note explaining the balance-due gap.
        // When neither sum reconciles: emit the existing error-severity subtotal finding.
        if (! empty($invoice->lineItems)) {
            $lineSum = array_reduce(
                $invoice->lineItems,
                fn (float $carry, LineItem $item) => $carry + ($item->amount ?? 0.0),
                0.0,
            );

            $adjustmentLines = array_filter($invoice->lineItems, fn (LineItem $item) => $this->isAdjustment($item));
            $hasAdjustments = ! empty($adjustmentLines);

            $chargeSum = array_reduce(
                array_filter($invoice->lineItems, fn (LineItem $item) => ! $this->isAdjustment($item)),
                fn (float $carry, LineItem $item) => $carry + ($item->amount ?? 0.0),
                0.0,
            );

            $reconcilesToSubtotal = abs($lineSum - $invoice->subtotal) <= self::TOLERANCE_AUD;
            $reconcilesToTotal = abs($lineSum - $invoice->total) <= self::TOLERANCE_AUD;
            $chargeReconcilesToSubtotal = abs($chargeSum - $invoice->subtotal) <= self::TOLERANCE_AUD;
            $chargeReconcilesToTotal = abs($chargeSum - $invoice->total) <= self::TOLERANCE_AUD;

            $fullSumReconciles = $reconcilesToSubtotal || $reconcilesToTotal;
            $chargeOnlyReconciles = $chargeReconcilesToSubtotal || $chargeReconcilesToTotal;

            if ($fullSumReconciles || $chargeOnlyReconciles) {
                // At least one reconciliation path passes — no error.
                // When only the charge-only path reconciles and adjustments are present,
                // emit an informational note explaining the gap (mirrors check (c) pattern).
                if (! $fullSumReconciles && $chargeOnlyReconciles && $hasAdjustments) {
                    $errors[] = new ValidationError(
                        field: 'subtotal',
                        severity: 'info',
                        message: sprintf(
                            'Charges total %s; after payments/credits the outstanding balance is %s.',
                            number_format($chargeSum, 2),
                            number_format($lineSum, 2),
                        ),
                    );
                }
            } else {
                $errors[] = new ValidationError(
                    field: 'subtotal',
                    severity: 'error',
                    message: sprintf(
                        'Line items sum to %s but subtotal is %s (difference: %s).',
                        number_format($lineSum, 2),
                        number_format($invoice->subtotal, 2),
                        number_format(abs($lineSum - $invoice->subtotal), 2),
                    ),
                );
            }
        }

        // (b) GST = 10% of subtotal.
        $expectedGst = round($invoice->subtotal * self::GST_RATE, 2);
        if (abs($invoice->gst - $expectedGst) > self::TOLERANCE_AUD) {
            $errors[] = new ValidationError(
                field: 'gst',
                severity: 'error',
                message: sprintf(
                    'GST is %s but expected %s (10%% of subtotal %s).',
                    number_format($invoice->gst, 2),
                    number_format($expectedGst, 2),
                    number_format($invoice->subtotal, 2),
                ),
            );
        }

        // (c) Subtotal + GST = total.
        //
        // On a payment-allocated invoice, the Total is the net balance due after
        // payments/credits — not the charge total. In that case the line items
        // (which include the payment/credit rows) corroborate the Total even though
        // subtotal + GST does not equal it.
        //
        // Three-way decision:
        //  1. subtotal + GST ≈ total             → pass (standard charge invoice)
        //  2. subtotal + GST ≠ total AND
        //     line items corroborate total       → suppress error, emit info note
        //  3. subtotal + GST ≠ total AND
        //     line items do NOT corroborate total → genuine typo, emit error
        //
        // When no line items are present, only path 1 or 3 can apply.
        $expectedTotal = $invoice->subtotal + $invoice->gst;
        $checkCFails = abs($invoice->total - $expectedTotal) > self::TOLERANCE_AUD;

        if ($checkCFails) {
            // Determine whether the already-computed $reconcilesToTotal flag can
            // serve as independent corroboration (only meaningful when line items exist).
            $lineItemsCorroborateTotal = isset($reconcilesToTotal) && $reconcilesToTotal;

            if ($lineItemsCorroborateTotal) {
                // Suppress the error — line items confirm the Total is the net balance due.
                // Emit a single informational note so operators understand why the numbers differ.
                $errors[] = new ValidationError(
                    field: 'total',
                    severity: 'info',
                    message: sprintf(
                        'Total of %s reflects payments/credits already applied; charges before payment were %s (subtotal + GST).',
                        number_format($invoice->total, 2),
                        number_format($expectedTotal, 2),
                    ),
                );
            } else {
                // No corroboration from line items (either none present, or they also
                // disagree with the Total) — this is a genuine arithmetic discrepancy.
                $errors[] = new ValidationError(
                    field: 'total',
                    severity: 'error',
                    message: sprintf(
                        'Total is %s but subtotal + GST = %s.',
                        number_format($invoice->total, 2),
                        number_format($expectedTotal, 2),
                    ),
                );
            }
        }

        return $errors;
    }
}
