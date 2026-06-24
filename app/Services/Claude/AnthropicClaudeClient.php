<?php

declare(strict_types=1);

namespace App\Services\Claude;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real {@see ClaudeClient} backed by the Anthropic Messages API.
 *
 * Targets model id `claude-opus-4-8` and authenticates with the key from
 * `config('services.anthropic.key')`. Uses Laravel's HTTP client against
 * https://api.anthropic.com/v1/messages with the required `anthropic-version`
 * and `x-api-key` headers.
 *
 * Tests NEVER instantiate the network path — they bind a fake to the
 * {@see ClaudeClient} interface instead.
 */
final class AnthropicClaudeClient implements ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-4-8',
    ) {}

    public function message(
        string $prompt,
        array $documents = [],
        ?array $tool = null,
        ?string $toolChoice = null,
        ?string $system = null,
        int $maxTokens = 4096,
    ): ClaudeResponse {
        $body = $this->buildBody($prompt, $documents, $tool, $toolChoice, $system, $maxTokens);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            throw new RuntimeException(
                "Anthropic API request failed (HTTP {$response->status()}): {$response->body()}"
            );
        }

        return $this->parse($response->json());
    }

    /**
     * Assemble the Anthropic Messages API request body.
     *
     * @param list<DocumentBlock> $documents
     * @param array<string, mixed>|null $tool
     *
     * @return array<string, mixed>
     */
    private function buildBody(
        string $prompt,
        array $documents,
        ?array $tool,
        ?string $toolChoice,
        ?string $system,
        int $maxTokens,
    ): array {
        $content = [];

        foreach ($documents as $document) {
            $content[] = $document->toAnthropicBlock();
        }

        $content[] = ['type' => 'text', 'text' => $prompt];

        $body = [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($system !== null) {
            $body['system'] = $system;
        }

        if ($tool !== null) {
            $body['tools'] = [$tool];
            $body['tool_choice'] = $toolChoice !== null
                ? ['type' => 'tool', 'name' => $toolChoice]
                : ['type' => 'auto'];
        }

        return $body;
    }

    /**
     * Translate the raw Anthropic JSON response into a {@see ClaudeResponse}.
     *
     * @param array<string, mixed>|null $payload
     */
    private function parse(?array $payload): ClaudeResponse
    {
        $payload ??= [];

        /** @var list<array<string, mixed>> $content */
        $content = is_array($payload['content'] ?? null) ? $payload['content'] : [];

        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];

        return new ClaudeResponse(
            content: $content,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            stopReason: isset($payload['stop_reason']) ? (string) $payload['stop_reason'] : null,
            model: isset($payload['model']) ? (string) $payload['model'] : null,
        );
    }
}
