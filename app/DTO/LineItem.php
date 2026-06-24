<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * A single line on an extracted invoice.
 *
 * Amounts are AUD. `qty`, `rate`, and `amount` are nullable because a real
 * invoice line may omit a column (e.g. a flat-fee line with no unit rate);
 * downstream validators (REQ-009+) decide how to treat the gaps.
 */
final readonly class LineItem
{
    public function __construct(
        public string $description,
        public ?float $qty,
        public ?float $rate,
        public ?float $amount,
    ) {}

    /**
     * Build a LineItem from a raw tool-call array element.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            description: isset($raw['description']) ? (string) $raw['description'] : '',
            qty: self::toFloat($raw['qty'] ?? null),
            rate: self::toFloat($raw['rate'] ?? null),
            amount: self::toFloat($raw['amount'] ?? null),
        );
    }

    private static function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
