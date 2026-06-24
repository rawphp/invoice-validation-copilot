<?php

declare(strict_types=1);

namespace App\Services\Claude;

/**
 * The single Claude boundary shared by the whole pipeline.
 *
 * Both vision extraction (with image/PDF document blocks and a forced tool
 * schema for structured output) and the text explanation step drive Claude
 * through this one method. It is THE seam mocked in tests — implementations
 * are bound in the container and may be swapped for a fake that never touches
 * the network.
 *
 * Keep this contract STABLE: REQ-008 (extraction tool-use) and REQ-014
 * (explanation text) both depend on this exact shape.
 */
interface ClaudeClient
{
    /**
     * Send one user message to Claude and return the content plus token usage.
     *
     * @param string $prompt The user text. Document blocks are sent alongside it.
     * @param list<DocumentBlock> $documents Optional image/PDF blocks (base64 + media type).
     * @param array<string, mixed>|null $tool Optional tool schema. When given, the model is
     *        steered toward emitting a `tool_use` block. Shape:
     *        `['name' => string, 'description' => string, 'input_schema' => array]`.
     * @param string|null $toolChoice Optional tool name to FORCE (sets `tool_choice` to that
     *        specific tool). Null lets the model decide. Only meaningful when `$tool` is set.
     * @param string|null $system Optional system prompt.
     * @param int $maxTokens Max output tokens to generate.
     *
     * @return ClaudeResponse Content blocks plus input/output token usage.
     */
    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse;
}
