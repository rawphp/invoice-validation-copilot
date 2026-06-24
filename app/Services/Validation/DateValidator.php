<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;
use Carbon\Carbon;
use DateTimeInterface;
use Throwable;

/**
 * Validates invoice date and due date fields.
 *
 * Parsing contract: dates are expected in Australian locale DD/MM/YYYY format.
 * Also accepts ISO 8601 YYYY-MM-DD strings defensively (e.g. already-normalised values).
 *
 * Checks performed:
 *  1. Both dates parse — a format error is emitted for each unparseable value.
 *  2. Invoice date is not in the future relative to the injected clock.
 *  3. Due date is on or after the invoice date.
 *
 * The clock is constructor-injected so unit tests can fix "now" to a known date,
 * making the "not future" check deterministic.
 */
final class DateValidator implements Validator
{
    /**
     * @param  Carbon|DateTimeInterface  $now  The reference clock ("today"). Inject a fixed
     *                                         Carbon instance in tests for determinism.
     */
    public function __construct(
        private readonly Carbon|DateTimeInterface $now,
    ) {}

    /**
     * @return ValidationError[]
     */
    public function validate(ExtractedInvoice $invoice): array
    {
        $errors = [];

        $invoiceDate = $this->parseDate($invoice->invoiceDate);
        $dueDate = $this->parseDate($invoice->dueDate);

        // --- (a) Parse checks ---

        if ($invoice->invoiceDate !== null && $invoiceDate === null) {
            $errors[] = new ValidationError(
                field: 'invoice_date',
                severity: 'error',
                message: sprintf(
                    'Invoice date "%s" could not be parsed. Expected DD/MM/YYYY format.',
                    $invoice->invoiceDate,
                ),
            );
        }

        if ($invoice->dueDate !== null && $dueDate === null) {
            $errors[] = new ValidationError(
                field: 'due_date',
                severity: 'error',
                message: sprintf(
                    'Due date "%s" could not be parsed. Expected DD/MM/YYYY format.',
                    $invoice->dueDate,
                ),
            );
        }

        // Null date fields (missing) are handled by RequiredFieldsValidator — skip further checks.
        if ($invoiceDate === null || $dueDate === null) {
            return $errors;
        }

        // Normalise "now" to a Carbon instance for comparison; strip time component.
        $today = Carbon::instance($this->now)->startOfDay();

        // --- (b) Invoice date must not be in the future ---

        if ($invoiceDate->startOfDay()->isAfter($today)) {
            $errors[] = new ValidationError(
                field: 'invoice_date',
                severity: 'error',
                message: sprintf(
                    'Invoice date %s is in the future.',
                    $invoiceDate->format('d/m/Y'),
                ),
            );
        }

        // --- (c) Due date must be on or after invoice date ---

        if ($dueDate->startOfDay()->isBefore($invoiceDate->startOfDay())) {
            $errors[] = new ValidationError(
                field: 'due_date',
                severity: 'error',
                message: sprintf(
                    'Due date %s is before invoice date %s.',
                    $dueDate->format('d/m/Y'),
                    $invoiceDate->format('d/m/Y'),
                ),
            );
        }

        return $errors;
    }

    /**
     * Attempt to parse a date string.
     *
     * Tries AU locale DD/MM/YYYY first; falls back to ISO 8601 YYYY-MM-DD for
     * values that have already been normalised upstream. Returns null when neither
     * format matches, rather than throwing.
     */
    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // --- Primary: AU locale DD/MM/YYYY ---
        // Use createFromFormat so we get strict parsing (no fall-through guessing).
        try {
            $parsed = Carbon::createFromFormat('d/m/Y', $value);
            if ($parsed !== false && $parsed->format('d/m/Y') === $value) {
                return $parsed;
            }
        } catch (Throwable) {
            // fall through to next format
        }

        // --- Fallback: ISO 8601 YYYY-MM-DD (normalised values) ---
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $value);
            if ($parsed !== false && $parsed->format('Y-m-d') === $value) {
                return $parsed;
            }
        } catch (Throwable) {
            // fall through
        }

        return null;
    }
}
