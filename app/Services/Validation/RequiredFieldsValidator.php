<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;

/**
 * Checks that all mandatory invoice fields are present.
 *
 * Mandatory fields (AU invoices):
 *   - supplier    — business name of the issuer
 *   - abn         — Australian Business Number
 *   - invoiceDate — date the invoice was issued
 *   - lineItems   — at least one line item
 *   - subtotal    — pre-tax amount
 *   - gst         — GST component
 *   - total       — total payable
 *
 * Returns one error-severity {@see ValidationError} per missing field;
 * returns an empty array when all mandatory fields are present.
 */
final class RequiredFieldsValidator implements Validator
{
    /**
     * @return ValidationError[]
     */
    public function validate(ExtractedInvoice $invoice): array
    {
        $errors = [];

        if (empty($invoice->supplier)) {
            $errors[] = new ValidationError(
                field: 'supplier',
                severity: 'error',
                message: 'Supplier name is missing.',
            );
        }

        if (empty($invoice->abn)) {
            $errors[] = new ValidationError(
                field: 'abn',
                severity: 'error',
                message: 'ABN is missing.',
            );
        }

        if (empty($invoice->invoiceDate)) {
            $errors[] = new ValidationError(
                field: 'invoiceDate',
                severity: 'error',
                message: 'Invoice date is missing.',
            );
        }

        if (empty($invoice->lineItems)) {
            $errors[] = new ValidationError(
                field: 'lineItems',
                severity: 'error',
                message: 'At least one line item is required.',
            );
        }

        if ($invoice->subtotal === null) {
            $errors[] = new ValidationError(
                field: 'subtotal',
                severity: 'error',
                message: 'Subtotal is missing.',
            );
        }

        if ($invoice->gst === null) {
            $errors[] = new ValidationError(
                field: 'gst',
                severity: 'error',
                message: 'GST amount is missing.',
            );
        }

        if ($invoice->total === null) {
            $errors[] = new ValidationError(
                field: 'total',
                severity: 'error',
                message: 'Total is missing.',
            );
        }

        return $errors;
    }
}
