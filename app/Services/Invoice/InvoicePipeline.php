<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\AuditEntry;
use App\DTO\InvoiceResult;
use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use App\Services\Validation\ValidationService;
use Closure;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Orchestrates the full invoice-validation flow over a single uploaded file.
 *
 * Fixed ordering — confidence and explanation MUST run after validation:
 *   1. {@see FileIntake}          — normalise the upload (downscale image / wrap PDF).
 *   2. {@see ExtractionService}   — Claude vision + forced tool call → ExtractedInvoice.
 *   3. {@see ValidationService}   — required-fields + ABN + arithmetic + date validators.
 *   4. {@see ConfidenceScorer}    — aggregate confidence + pass/fail verdict.
 *   5. {@see ExplanationService}  — Claude text → plain-English operator card.
 *
 * Every step appends an {@see AuditEntry} to a fresh {@see AuditLog}
 * (step name, status, duration). The two LLM steps additionally record the
 * model id + input/output token usage taken from the {@see ClaudeResponse}.
 *
 * The result is an {@see InvoiceResult} aggregate. Token usage for the LLM steps
 * is captured by wrapping the injected {@see ClaudeClient} in a small recorder so
 * the extraction/explanation services stay unaware of the audit concern.
 */
final class InvoicePipeline
{
    public function __construct(
        private readonly FileIntake $fileIntake,
        private readonly ValidationService $validation,
        private readonly ConfidenceScorer $scorer,
        private readonly ClaudeClient $claude,
    ) {}

    public function run(UploadedFile $file): InvoiceResult
    {
        $audit = new AuditLog;
        $recorder = new RecordingClaudeClient($this->claude);

        $extraction = new ExtractionService($recorder);
        $explanationService = new ExplanationService($recorder);

        try {
            // Fixed ordering — confidence and explanation MUST run after validation.
            [$intake, $duration] = $this->step($audit, 'intake', fn () => $this->fileIntake->process($file));
            $audit->recordDeterministic('intake', 'ok', $duration, 0);

            [$invoice, $duration] = $this->step($audit, 'extract', fn () => $extraction->extract($intake));
            $this->recordLlmStep($audit, 'extract', $duration, $recorder->lastResponse());

            [$errors, $duration] = $this->step($audit, 'validate', fn () => $this->validation->run($invoice));
            $audit->recordDeterministic('validate', 'ok', $duration, count($errors));

            [$confidence, $duration] = $this->step($audit, 'score', fn () => $this->scorer->score($invoice, $errors));
            $audit->recordDeterministic('score', 'ok', $duration, 0);

            [$explanation, $duration] = $this->step($audit, 'explain', fn () => $explanationService->explain($invoice, $errors));
            $this->recordLlmStep($audit, 'explain', $duration, $recorder->lastResponse());
        } catch (PipelineStepFailed) {
            // The step recorded its own 'failed' audit entry and reported the cause.
            return InvoiceResult::error($this->friendlyMessage(), $audit->entries());
        }

        return InvoiceResult::success(
            invoice: $invoice,
            errors: $errors,
            confidence: $confidence,
            explanation: $explanation,
            auditEntries: $audit->entries(),
        );
    }

    /**
     * Run one timed pipeline step. On failure it records a 'failed' audit entry,
     * reports the cause for observability, and short-circuits the run via
     * {@see PipelineStepFailed}. Success-side audit recording stays with the
     * caller, since it differs per step (deterministic vs LLM, error counts).
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return array{0: TResult, 1: float} The step result and its duration in ms.
     */
    private function step(AuditLog $audit, string $name, Closure $work): array
    {
        $start = microtime(true);

        try {
            $result = $work();
        } catch (Throwable $e) {
            report($e);
            $audit->recordDeterministic($name, 'failed', $this->elapsed($start), 0);

            throw new PipelineStepFailed($name, $e);
        }

        return [$result, $this->elapsed($start)];
    }

    private function recordLlmStep(AuditLog $audit, string $step, float $duration, ?ClaudeResponse $response): void
    {
        $audit->recordLlm(
            step: $step,
            status: 'ok',
            duration: $duration,
            modelId: $response?->model ?? 'unknown',
            inputTokens: $response?->inputTokens ?? 0,
            outputTokens: $response?->outputTokens ?? 0,
        );
    }

    private function elapsed(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function friendlyMessage(): string
    {
        return 'We could not finish validating this invoice. The document service '
            .'is temporarily unavailable — please try again in a moment.';
    }
}
