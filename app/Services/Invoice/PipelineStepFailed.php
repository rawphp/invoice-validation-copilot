<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\InvoiceResult;
use RuntimeException;
use Throwable;

/**
 * Internal control-flow signal raised when an {@see InvoicePipeline} step throws.
 *
 * The pipeline catches this once at the top of {@see InvoicePipeline::run()} to
 * short-circuit into a friendly {@see InvoiceResult::error()} state. The
 * original cause is preserved (and already reported for observability) so the
 * failure is diagnosable; this wrapper only carries the failing step name.
 */
final class PipelineStepFailed extends RuntimeException
{
    public function __construct(public readonly string $step, Throwable $previous)
    {
        parent::__construct("Pipeline step [{$step}] failed: {$previous->getMessage()}", previous: $previous);
    }
}
