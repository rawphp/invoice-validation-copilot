<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;

/**
 * Contract for all deterministic invoice validators.
 *
 * Each implementation inspects one concern (required fields, ABN format,
 * arithmetic, date logic) and returns zero or more {@see ValidationError}s.
 * The {@see ValidationService} aggregates results from all registered validators.
 *
 * Implementors:
 *  - REQ-009: {@see RequiredFieldsValidator}
 *  - REQ-010: AbnValidator
 *  - REQ-011: ArithmeticValidator
 *  - REQ-012: DateValidator
 *
 * STABLE CONTRACT — do not change the method signature without updating all
 * downstream validators.
 */
interface Validator
{
    /**
     * Validate the given invoice and return any findings.
     *
     * @return ValidationError[]  Empty array means no issues found.
     */
    public function validate(ExtractedInvoice $invoice): array;
}
