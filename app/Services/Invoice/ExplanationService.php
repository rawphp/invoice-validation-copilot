<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\DTO\ExtractedInvoice;
use App\DTO\ValidationError;
use App\Services\Claude\ClaudeClient;

/**
 * Produces a warm, plain-English, operator-facing explanation of what an
 * invoice is missing or why it failed deterministic checks, so the operator
 * knows exactly what to tell the supplier.
 *
 * This is the SECOND Claude call in the pipeline (text only — no document
 * blocks, no tool schema). It runs AFTER validation so the prompt can
 * reference concrete deterministic findings (e.g. ABN checksum) that the
 * extraction model cannot compute.
 *
 * Findings are partitioned by severity before building the prompt:
 * - Blocking findings (error/warning) → "what the supplier must fix" path.
 * - Info findings → contextual notes only; never framed as supplier errors.
 */
final class ExplanationService
{
    public function __construct(private readonly ClaudeClient $claude) {}

    /**
     * Generate a plain-English explanation card for the operator.
     *
     * @param  ExtractedInvoice  $invoice  The structured extraction result.
     * @param  list<ValidationError>  $errors  Deterministic validation findings.
     * @return string The operator-facing explanation (what to tell the supplier).
     */
    public function explain(ExtractedInvoice $invoice, array $errors): string
    {
        $prompt = $this->buildPrompt($invoice, $errors);

        $response = $this->claude->message(
            prompt: $prompt,
            documents: [],
            tool: null,
            toolChoice: null,
            system: $this->buildSystem(),
        );

        return $response->text();
    }

    private function buildSystem(): string
    {
        return <<<'SYSTEM'
        You are an accounts-payable assistant helping an operator review supplier invoices.
        Your role is to draft a clear, concise, and professional explanation of what the
        operator should tell the supplier about their invoice. Write in plain English.
        Be warm but direct. Do not address the supplier directly — write for the operator.
        SYSTEM;
    }

    private function buildPrompt(ExtractedInvoice $invoice, array $errors): string
    {
        $invoiceSummary = $this->buildInvoiceSummary($invoice);

        /** @var list<ValidationError> $blockingFindings */
        $blockingFindings = array_values(array_filter(
            $errors,
            static fn (ValidationError $e): bool => $e->severity === 'error' || $e->severity === 'warning',
        ));

        /** @var list<ValidationError> $infoFindings */
        $infoFindings = array_values(array_filter(
            $errors,
            static fn (ValidationError $e): bool => $e->severity === 'info',
        ));

        if ($blockingFindings === []) {
            // All-clear path: no blocking findings. Include any info findings as context.
            $infoContext = $this->buildInfoContext($infoFindings);

            return <<<PROMPT
            The following invoice has been reviewed and all validation checks have passed
            — no errors or warnings were found.

            Invoice summary:
            {$invoiceSummary}
            {$infoContext}
            Write a brief, positive message (2–3 sentences) telling the operator that
            no issues were found and the invoice looks complete and correct. If contextual
            notes are present above, mention them neutrally (e.g. note that a payment or
            credit has been applied and state the balance due). Do not tell the operator
            to ask the supplier to correct anything.
            PROMPT;
        }

        // Blocking path: one or more error/warning findings. Info findings become context.
        $errorList = $this->buildErrorList($blockingFindings);
        $infoContext = $this->buildInfoContext($infoFindings);

        return <<<PROMPT
        The following invoice has been reviewed and the deterministic validation checks
        produced the errors listed below. Please write a warm, plain-English explanation
        for the operator — telling them what to tell the supplier about the problems and
        what the supplier needs to fix before the invoice can be approved.

        Invoice summary:
        {$invoiceSummary}

        Validation errors found:
        {$errorList}
        {$infoContext}
        Write a clear, actionable explanation (3–6 sentences) for the operator. Reference
        each failed check by field name. Be specific about what is wrong and what the
        supplier must do to correct it. If contextual notes are present above, mention them
        neutrally — they are not errors the supplier must fix.
        PROMPT;
    }

    private function buildInvoiceSummary(ExtractedInvoice $invoice): string
    {
        $lines = [];

        if ($invoice->supplier !== null) {
            $lines[] = "Supplier: {$invoice->supplier}";
        }

        if ($invoice->abn !== null) {
            $lines[] = "ABN: {$invoice->abn}";
        }

        if ($invoice->invoiceDate !== null) {
            $lines[] = "Invoice date: {$invoice->invoiceDate}";
        }

        if ($invoice->dueDate !== null) {
            $lines[] = "Due date: {$invoice->dueDate}";
        }

        if ($invoice->total !== null) {
            $lines[] = 'Total: AUD '.number_format($invoice->total, 2);
        }

        $lines[] = 'Service category: '.$invoice->serviceCategory->label();

        return implode("\n", $lines);
    }

    /**
     * @param  list<ValidationError>  $errors
     */
    private function buildErrorList(array $errors): string
    {
        $lines = [];

        foreach ($errors as $error) {
            $lines[] = "- [{$error->severity}] Field: {$error->field} — {$error->message}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a contextual notes section for info-severity findings.
     * Returns an empty string when there are no info findings.
     *
     * @param  list<ValidationError>  $infoFindings
     */
    private function buildInfoContext(array $infoFindings): string
    {
        if ($infoFindings === []) {
            return '';
        }

        $lines = [];

        foreach ($infoFindings as $finding) {
            $lines[] = "- {$finding->message}";
        }

        $notesList = implode("\n", $lines);

        return <<<CONTEXT

        Contextual notes (informational only — no action required from the supplier):
        {$notesList}

        CONTEXT;
    }
}
