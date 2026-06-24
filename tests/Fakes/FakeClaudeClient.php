<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use App\Services\Claude\DocumentBlock;

/**
 * A test fake standing in for the real ClaudeClient seam.
 *
 * This proves the interface is ergonomic enough that both extraction
 * (vision + tool-use) and explanation (text) callers can drive it, and
 * that the container binding can be swapped for a fake with no network.
 */
final class FakeClaudeClient implements ClaudeClient
{
    public ?string $lastPrompt = null;

    /** @var list<DocumentBlock> */
    public array $lastDocuments = [];

    public ?array $lastTool = null;

    public ?string $lastToolChoice = null;

    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse {
        $this->lastPrompt = $prompt;
        $this->lastDocuments = $documents;
        $this->lastTool = $tool;
        $this->lastToolChoice = $toolChoice;

        return new ClaudeResponse(
            content: [['type' => 'text', 'text' => 'canned reply']],
            inputTokens: 12,
            outputTokens: 7,
            stopReason: 'end_turn',
            model: 'fake-model',
        );
    }
}
