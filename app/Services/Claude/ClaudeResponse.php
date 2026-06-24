<?php

declare(strict_types=1);

namespace App\Services\Claude;

/**
 * Typed result of a single Claude message exchange.
 *
 * Carries BOTH the raw response content blocks AND token usage so callers can
 * surface cost/latency telemetry alongside the answer. This shape is STABLE —
 * downstream services (extraction tool-use, explanation text) depend on it.
 */
final readonly class ClaudeResponse
{
    /**
     * @param list<array<string, mixed>> $content Raw Anthropic content blocks. Each block has a
     *        `type` (e.g. `text`, `tool_use`) plus type-specific fields. Use {@see self::text()}
     *        for text output or {@see self::toolInput()} for forced structured tool output.
     * @param int $inputTokens Prompt (input) tokens billed.
     * @param int $outputTokens Completion (output) tokens billed.
     * @param string|null $stopReason Why generation stopped (e.g. `end_turn`, `tool_use`, `max_tokens`).
     * @param string|null $model The model id that produced this response.
     */
    public function __construct(
        public array $content,
        public int $inputTokens,
        public int $outputTokens,
        public ?string $stopReason = null,
        public ?string $model = null,
    ) {}

    /**
     * Total tokens billed for this exchange (input + output).
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Concatenated text from all `text` content blocks (empty string if none).
     */
    public function text(): string
    {
        $parts = [];

        foreach ($this->content as $block) {
            if (($block['type'] ?? null) === 'text' && isset($block['text'])) {
                $parts[] = (string) $block['text'];
            }
        }

        return implode('', $parts);
    }

    /**
     * Decoded input of the first `tool_use` block, or null if the model did not
     * call a tool. This is the structured output produced when a tool is forced.
     *
     * @return array<string, mixed>|null
     */
    public function toolInput(): ?array
    {
        foreach ($this->content as $block) {
            if (($block['type'] ?? null) === 'tool_use' && isset($block['input']) && is_array($block['input'])) {
                return $block['input'];
            }
        }

        return null;
    }
}
