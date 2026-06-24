<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use App\Services\Claude\DocumentBlock;

/**
 * A canned-response fake of the ClaudeClient seam. It records the call
 * arguments (so we can assert the forced-tool wiring) and returns a tool_use
 * payload supplied at construction. NO network.
 */
final class CannedClaudeClient implements ClaudeClient
{
    public ?string $lastPrompt = null;

    /** @var list<DocumentBlock> */
    public array $lastDocuments = [];

    public ?array $lastTool = null;

    public ?string $lastToolChoice = null;

    public ?string $lastSystem = null;

    /** @param array<string, mixed>|null $toolInput */
    public function __construct(private readonly ?array $toolInput) {}

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
        $this->lastSystem = $system;

        $content = $this->toolInput === null
            ? [['type' => 'text', 'text' => 'this is not an invoice']]
            : [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'record_invoice', 'input' => $this->toolInput]];

        return new ClaudeResponse(
            content: $content,
            inputTokens: 100,
            outputTokens: 50,
            stopReason: $this->toolInput === null ? 'end_turn' : 'tool_use',
            model: 'fake-model',
        );
    }
}
