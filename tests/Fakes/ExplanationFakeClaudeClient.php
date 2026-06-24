<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;

/**
 * A text-only fake of ClaudeClient. Records what the service sends, returns
 * the canned text string. NO network.
 */
final class ExplanationFakeClaudeClient implements ClaudeClient
{
    public ?string $lastPrompt = null;

    public ?string $lastSystem = null;

    public ?array $lastTool = null;

    public function __construct(private readonly string $cannedText) {}

    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse {
        $this->lastPrompt = $prompt;
        $this->lastSystem = $system;
        $this->lastTool = $tool;

        return new ClaudeResponse(
            content: [['type' => 'text', 'text' => $this->cannedText]],
            inputTokens: 80,
            outputTokens: 120,
            stopReason: 'end_turn',
            model: 'fake-model',
        );
    }
}
