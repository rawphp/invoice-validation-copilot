<?php

declare(strict_types=1);

namespace App\Services\Invoice;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Converts an uploaded invoice file into a normalised payload ready for the
 * Claude vision/document call.
 *
 * Returned array shape:
 *   [
 *     'mime'    => string,          // e.g. 'image/jpeg' or 'application/pdf'
 *     'content' => string,          // base64-encoded raw bytes
 *     'kind'    => 'image'|'pdf',
 *   ]
 *
 * Images that exceed Claude's per-image limits are downscaled / recompressed
 * in-memory using PHP's bundled GD extension (no external dependencies).
 *
 * Claude vision limits (as of 2025):
 *   - Long edge  : 1 568 px
 *   - Encoded cap: ~5 MB (we use 4.9 MB to give a small buffer)
 */
final class FileIntake
{
    /** Maximum long edge accepted by Claude vision. */
    private const MAX_LONG_EDGE = 1568;

    /** Soft byte cap (just under the 5 MB hard limit). */
    private const MAX_BYTES = 4_900_000;

    /** Starting JPEG quality when recompressing. */
    private const JPEG_QUALITY_START = 85;

    /** Minimum quality we'll drop to before giving up. */
    private const JPEG_QUALITY_MIN = 40;

    /**
     * @return array{mime: string, content: string, kind: 'image'|'pdf'}
     */
    public function process(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?? $file->getClientMimeType();

        if ($mime === 'application/pdf') {
            return $this->processPdf($file, $mime);
        }

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return $this->processImage($file, $mime);
        }

        throw new RuntimeException("Unsupported mime type: {$mime}");
    }

    /** @return array{mime: string, content: string, kind: 'pdf'} */
    private function processPdf(UploadedFile $file, string $mime): array
    {
        return [
            'mime'    => $mime,
            'content' => base64_encode(file_get_contents($file->getRealPath())),
            'kind'    => 'pdf',
        ];
    }

    /** @return array{mime: string, content: string, kind: 'image'} */
    private function processImage(UploadedFile $file, string $mime): array
    {
        $rawBytes = file_get_contents($file->getRealPath());
        $img = @imagecreatefromstring($rawBytes);

        if ($img === false) {
            throw new RuntimeException('Could not decode image data with GD.');
        }

        $width  = imagesx($img);
        $height = imagesy($img);
        $longEdge = max($width, $height);

        // Only downscale if necessary.
        if ($longEdge > self::MAX_LONG_EDGE) {
            $scale  = self::MAX_LONG_EDGE / $longEdge;
            $newW   = (int) round($width  * $scale);
            $newH   = (int) round($height * $scale);

            $resized = imagecreatetruecolor($newW, $newH);

            // Preserve transparency for PNG/GIF.
            if (in_array($mime, ['image/png', 'image/gif'], true)) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
            }

            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);
            $img = $resized;
        }

        // Encode to JPEG (always output JPEG for downscaled images to keep size predictable).
        $outputMime = 'image/jpeg';
        $encoded    = $this->encodeToJpeg($img, self::JPEG_QUALITY_START);

        // Progressively reduce quality if still over byte cap.
        $quality = self::JPEG_QUALITY_START;
        while (strlen($encoded) > self::MAX_BYTES && $quality > self::JPEG_QUALITY_MIN) {
            $quality -= 10;
            $encoded  = $this->encodeToJpeg($img, $quality);
        }

        if (strlen($encoded) > self::MAX_BYTES) {
            throw new RuntimeException('Image could not be compressed below the Claude byte cap.');
        }

        return [
            'mime'    => $outputMime,
            'content' => base64_encode($encoded),
            'kind'    => 'image',
        ];
    }

    private function encodeToJpeg(\GdImage $img, int $quality): string
    {
        ob_start();
        imagejpeg($img, null, $quality);
        $data = ob_get_clean();

        if ($data === false) {
            throw new RuntimeException('GD imagejpeg() failed to produce output.');
        }

        return $data;
    }
}
