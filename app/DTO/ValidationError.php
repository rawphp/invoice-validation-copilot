<?php

declare(strict_types=1);

namespace App\DTO;

use JsonSerializable;

/**
 * A single validation finding produced by a {@see \App\Services\Validation\Validator}.
 *
 * Immutable value object. JSON-serializable for API responses and audit logs.
 */
final readonly class ValidationError implements JsonSerializable
{
    /**
     * @param  string  $field     The invoice field this finding relates to (camelCase, e.g. 'abn', 'invoiceDate').
     * @param  string  $severity  Either 'error' (blocking) or 'warning' (advisory).
     * @param  string  $message   Human-readable description of the problem.
     */
    public function __construct(
        public string $field,
        public string $severity,
        public string $message,
    ) {}

    /**
     * @return array{field: string, severity: string, message: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'field'    => $this->field,
            'severity' => $this->severity,
            'message'  => $this->message,
        ];
    }
}
