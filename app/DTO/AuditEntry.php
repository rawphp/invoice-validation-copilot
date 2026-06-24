<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class AuditEntry implements \JsonSerializable
{
    public function __construct(
        public string $step,
        public string $status,
        public float $duration,
        public ?string $modelId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $errorCount = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'step'     => $this->step,
            'status'   => $this->status,
            'duration' => $this->duration,
        ];

        if ($this->modelId !== null) {
            $data['model_id'] = $this->modelId;
        }

        if ($this->inputTokens !== null) {
            $data['input_tokens'] = $this->inputTokens;
        }

        if ($this->outputTokens !== null) {
            $data['output_tokens'] = $this->outputTokens;
        }

        if ($this->errorCount !== null) {
            $data['error_count'] = $this->errorCount;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
