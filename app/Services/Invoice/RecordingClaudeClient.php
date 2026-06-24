<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;

/**
 * A pass-through {@see ClaudeClient} that remembers the last {@see ClaudeResponse}.
 *
 * The pipeline wraps the real (or faked) client in this recorder so it can read
 * the model id + token usage of each LLM call for the audit trail — without the
 * extraction/explanation services having to expose the raw response.
 */
final class RecordingClaudeClient implements ClaudeClient
{
    private ?ClaudeResponse $lastResponse = null;

    public function __construct(private readonly ClaudeClient $inner) {}

    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse {
        $response = $this->inner->message(
            prompt: $prompt,
            documents: $documents,
            tool: $tool,
            toolChoice: $toolChoice,
            system: $system,
            maxTokens: $maxTokens,
        );

        $this->lastResponse = $response;

        return $response;
    }

    public function lastResponse(): ?ClaudeResponse
    {
        return $this->lastResponse;
    }
}
