<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;

/**
 * Validates the extracted ABN against the ATO modulus-89 weighted checksum.
 *
 * Algorithm (official ATO specification):
 *  1. Strip all whitespace; require exactly 11 digits (format check).
 *  2. Subtract 1 from the first digit.
 *  3. Multiply each of the 11 digits by weights [10,1,3,5,7,9,11,13,15,17,19].
 *  4. Sum the products; valid iff sum mod 89 == 0.
 *
 * When the ABN is null this validator is silent — presence is REQ-009's concern.
 *
 * @see https://abr.business.gov.au/Help/AbnFormat
 */
final class AbnValidator implements Validator
{
    /**
     * ATO-specified weights for each of the 11 ABN digit positions.
     */
    private const WEIGHTS = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];

    /**
     * @return ValidationError[]
     */
    public function validate(ExtractedInvoice $invoice): array
    {
        if ($invoice->abn === null) {
            // Presence check belongs to RequiredFieldsValidator (REQ-009).
            return [];
        }

        // Normalise: strip all whitespace.
        $digits = preg_replace('/\s+/', '', $invoice->abn);

        // Format check: must be exactly 11 numeric digits.
        if (! preg_match('/^\d{11}$/', $digits)) {
            return [
                new ValidationError(
                    field: 'abn',
                    severity: 'error',
                    message: 'ABN must be exactly 11 digits (got: ' . $digits . ').',
                ),
            ];
        }

        // ATO modulus-89 checksum.
        $digitArray = array_map('intval', str_split($digits));

        // Step: subtract 1 from the first digit.
        $digitArray[0] -= 1;

        $sum = 0;
        foreach (self::WEIGHTS as $position => $weight) {
            $sum += $digitArray[$position] * $weight;
        }

        if ($sum % 89 !== 0) {
            return [
                new ValidationError(
                    field: 'abn',
                    severity: 'error',
                    message: 'ABN ' . $digits . ' failed the ATO modulus-89 checksum.',
                ),
            ];
        }

        return [];
    }
}
