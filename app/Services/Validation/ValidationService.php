<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;

/**
 * Aggregates validation errors from a set of {@see Validator} implementations.
 *
 * Validators are injected at construction time — register ABN, arithmetic,
 * date, and required-fields validators here. Each runs over the same
 * {@see ExtractedInvoice} and the results are merged into a single flat list.
 *
 * Consumed by the pipeline orchestrator (REQ-016) after extraction.
 * Produces {@see ValidationError[]} consumed by:
 *  - Confidence scorer  (REQ-013)
 *  - Explanation engine (REQ-014)
 *  - Result page        (REQ-017)
 */
final class ValidationService
{
    /** @var Validator[] */
    private readonly array $validators;

    /**
     * @param  Validator[]  $validators  Ordered list of validators to run.
     */
    public function __construct(array $validators)
    {
        $this->validators = $validators;
    }

    /**
     * Run all validators over the invoice and return the merged error list.
     *
     * @return ValidationError[]
     */
    public function run(ExtractedInvoice $invoice): array
    {
        $errors = [];

        foreach ($this->validators as $validator) {
            foreach ($validator->validate($invoice) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }
}
