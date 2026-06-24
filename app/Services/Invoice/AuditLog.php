<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\AuditEntry;

final class AuditLog
{
    /** @var AuditEntry[] */
    private array $records = [];

    /**
     * Record a deterministic pipeline step (e.g. validation, file intake, confidence scoring).
     */
    public function recordDeterministic(
        string $step,
        string $status,
        float $duration,
        int $errorCount,
    ): void {
        $this->records[] = new AuditEntry(
            step: $step,
            status: $status,
            duration: $duration,
            errorCount: $errorCount,
        );
    }

    /**
     * Record an LLM-backed pipeline step (e.g. extraction, explanation).
     */
    public function recordLlm(
        string $step,
        string $status,
        float $duration,
        string $modelId,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $this->records[] = new AuditEntry(
            step: $step,
            status: $status,
            duration: $duration,
            modelId: $modelId,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    /**
     * Return all entries in insertion order as JSON-friendly arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        return array_map(fn (AuditEntry $e) => $e->toArray(), $this->records);
    }
}
