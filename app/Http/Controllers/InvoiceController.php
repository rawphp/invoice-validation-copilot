<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\InvoiceResult;
use App\Http\Requests\ValidateInvoiceRequest;
use App\Services\Invoice\InvoicePipeline;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The single entry point for the invoice-validation flow.
 *
 *   GET  /          → the upload page (REQ-004 'Upload' Inertia page).
 *   POST /validate  → run the {@see InvoicePipeline} over the upload and render
 *                     the 'Result' page (REQ-017) with the {@see InvoiceResult}.
 *
 * Any pipeline failure (e.g. the Claude API being unreachable) is caught and
 * rendered as a FRIENDLY error result — never a 500 / stack trace.
 */
final class InvoiceController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Upload');
    }

    public function validateInvoice(ValidateInvoiceRequest $request, InvoicePipeline $pipeline): Response
    {
        try {
            $result = $pipeline->run($request->file('file'));
        } catch (Throwable $e) {
            // Defensive net: the pipeline already returns a friendly error state
            // for known failures, but any unexpected throwable still degrades to
            // a user-facing message rather than a stack trace.
            $result = InvoiceResult::error(
                'We could not finish validating this invoice. Please try again in a moment.',
            );
        }

        return Inertia::render('Result', [
            'result' => $result->toArray(),
        ]);
    }
}
