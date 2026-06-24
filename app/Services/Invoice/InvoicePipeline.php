<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\AuditEntry;
use App\DTO\InvoiceResult;
use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use App\Services\Validation\ValidationService;
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

        // Step 1 — File intake (deterministic).
        $start = microtime(true);
        try {
            $intake = $this->fileIntake->process($file);
        } catch (Throwable $e) {
            $audit->recordDeterministic('intake', 'failed', $this->elapsed($start), 0);

            return InvoiceResult::error($this->friendlyMessage($e), $audit->entries());
        }
        $audit->recordDeterministic('intake', 'ok', $this->elapsed($start), 0);

        // Step 2 — Extraction (LLM vision + tool call).
        $start = microtime(true);
        try {
            $invoice = $extraction->extract($intake);
        } catch (Throwable $e) {
            $audit->recordDeterministic('extract', 'failed', $this->elapsed($start), 0);

            return InvoiceResult::error($this->friendlyMessage($e), $audit->entries());
        }
        $this->recordLlmStep($audit, 'extract', $start, $recorder->lastResponse());

        // Step 3 — Validation (deterministic; all validators registered).
        $start = microtime(true);
        try {
            $errors = $this->validation->run($invoice);
        } catch (Throwable $e) {
            $audit->recordDeterministic('validate', 'failed', $this->elapsed($start), 0);

            return InvoiceResult::error($this->friendlyMessage($e), $audit->entries());
        }
        $audit->recordDeterministic('validate', 'ok', $this->elapsed($start), count($errors));

        // Step 4 — Confidence scoring (deterministic) — AFTER validation.
        $start = microtime(true);
        try {
            $confidence = $this->scorer->score($invoice, $errors);
        } catch (Throwable $e) {
            $audit->recordDeterministic('score', 'failed', $this->elapsed($start), 0);

            return InvoiceResult::error($this->friendlyMessage($e), $audit->entries());
        }
        $audit->recordDeterministic('score', 'ok', $this->elapsed($start), 0);

        // Step 5 — Explanation (LLM text) — AFTER validation.
        $start = microtime(true);
        try {
            $explanation = $explanationService->explain($invoice, $errors);
        } catch (Throwable $e) {
            $audit->recordDeterministic('explain', 'failed', $this->elapsed($start), 0);

            return InvoiceResult::error($this->friendlyMessage($e), $audit->entries());
        }
        $this->recordLlmStep($audit, 'explain', $start, $recorder->lastResponse());

        return InvoiceResult::success(
            invoice: $invoice,
            errors: $errors,
            confidence: $confidence,
            explanation: $explanation,
            auditEntries: $audit->entries(),
        );
    }

    private function recordLlmStep(AuditLog $audit, string $step, float $start, ?ClaudeResponse $response): void
    {
        $audit->recordLlm(
            step: $step,
            status: 'ok',
            duration: $this->elapsed($start),
            modelId: $response?->model ?? 'unknown',
            inputTokens: $response?->inputTokens ?? 0,
            outputTokens: $response?->outputTokens ?? 0,
        );
    }

    private function elapsed(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function friendlyMessage(Throwable $e): string
    {
        return 'We could not finish validating this invoice. The document service '
            .'is temporarily unavailable — please try again in a moment.';
    }
}
