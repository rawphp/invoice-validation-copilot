<?php

declare(strict_types=1);

namespace App\Services\Claude;

/**
 * A base64-encoded document/image content block to attach to a Claude message.
 *
 * Two kinds are supported by the Anthropic Messages API:
 *  - `image` — rendered as an `image` content block (PNG/JPEG/GIF/WebP).
 *  - `pdf`   — rendered as a `document` content block (application/pdf).
 *
 * Construct via the {@see self::image()} / {@see self::pdf()} factories so the
 * media type and kind always agree.
 */
final readonly class DocumentBlock
{
    /**
     * @param 'image'|'pdf' $kind
     * @param string $mediaType MIME type, e.g. `image/png` or `application/pdf`.
     * @param string $data Base64-encoded file contents (no data: URI prefix).
     */
    public function __construct(
        public string $kind,
        public string $mediaType,
        public string $data,
    ) {}

    /**
     * Build an image content block from base64 data and its media type.
     */
    public static function image(string $base64, string $mediaType): self
    {
        return new self('image', $mediaType, $base64);
    }

    /**
     * Build a PDF document content block from base64 data.
     */
    public static function pdf(string $base64): self
    {
        return new self('pdf', 'application/pdf', $base64);
    }

    /**
     * Render this block in the shape the Anthropic Messages API expects.
     *
     * @return array{type: string, source: array{type: string, media_type: string, data: string}}
     */
    public function toAnthropicBlock(): array
    {
        return [
            'type' => $this->kind === 'pdf' ? 'document' : 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => $this->mediaType,
                'data' => $this->data,
            ],
        ];
    }
}
