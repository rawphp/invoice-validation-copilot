<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Services\Claude\ClaudeClient;
use App\Services\Claude\ClaudeResponse;
use RuntimeException;

/**
 * A fake ClaudeClient that never touches the network.
 *
 * It distinguishes the two pipeline calls by whether a tool schema is passed:
 *  - tool present  → the VISION extraction call → return a canned tool_use block.
 *  - tool null     → the explanation TEXT call → return canned text.
 *
 * Construct with $extraction to control the extracted fields, or set
 * $throwOnVision to simulate a Claude/API outage on the first call.
 */
final class PipelineFakeClaudeClient implements ClaudeClient
{
    public int $visionCalls = 0;

    public int $textCalls = 0;

    /**
     * @param  array<string, mixed>  $extraction  The tool input returned by the vision call.
     */
    public function __construct(
        private array $extraction,
        private readonly bool $throwOnVision = false,
        private readonly bool $throwOnText = false,
    ) {}

    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse {
        if ($tool !== null) {
            $this->visionCalls++;

            if ($this->throwOnVision) {
                throw new RuntimeException('Claude vision API is unreachable.');
            }

            return new ClaudeResponse(
                content: [[
                    'type' => 'tool_use',
                    'id' => 'toolu_fake',
                    'name' => $tool['name'],
                    'input' => $this->extraction,
                ]],
                inputTokens: 1200,
                outputTokens: 340,
                stopReason: 'tool_use',
                model: 'claude-fake-vision',
            );
        }

        $this->textCalls++;

        if ($this->throwOnText) {
            throw new RuntimeException('Claude text API is unreachable.');
        }

        return new ClaudeResponse(
            content: [[
                'type' => 'text',
                'text' => 'The invoice from Acme Pty Ltd looks complete and all checks passed.',
            ]],
            inputTokens: 210,
            outputTokens: 90,
            stopReason: 'end_turn',
            model: 'claude-fake-text',
        );
    }
}
