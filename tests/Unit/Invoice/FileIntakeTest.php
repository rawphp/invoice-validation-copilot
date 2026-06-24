<?php

declare(strict_types=1);

use App\Http\Requests\ValidateInvoiceRequest;
use App\Services\Invoice\FileIntake;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

// Boot the full Laravel container so facades (Validator) are available.
uses(TestCase::class);

// Claude image limits
const MAX_LONG_EDGE = 1568;
const MAX_IMAGE_BYTES = 5 * 1024 * 1024; // 5 MB

/** Create an UploadedFile backed by a real temp file with known content. */
function makeFakePdf(): UploadedFile
{
    // Minimal valid-looking PDF bytes (enough for the intake to base64-encode).
    $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\nxref\n0 1\n0000000000 65535 f\ntrailer\n<< /Size 1 >>\n%%EOF";
    $tmp = tempnam(sys_get_temp_dir(), 'pdf_test_');
    file_put_contents($tmp, $pdfContent);
    return new UploadedFile($tmp, 'invoice.pdf', 'application/pdf', null, true);
}

describe('FileIntake', function () {
    it('passes a PDF through as kind=pdf', function () {
        $file = makeFakePdf();

        $intake = new FileIntake();
        $payload = $intake->process($file);

        expect($payload['kind'])->toBe('pdf');
        expect($payload['mime'])->toBe('application/pdf');
        // content is base64-encoded; must be a non-empty string
        expect($payload['content'])->toBeString()->not->toBeEmpty();
    });

    it('accepts a small image and keeps it as kind=image', function () {
        // 100x100 should be well under limits
        $file = UploadedFile::fake()->image('invoice.jpg', 100, 100);

        $intake = new FileIntake();
        $payload = $intake->process($file);

        expect($payload['kind'])->toBe('image');
        expect($payload['mime'])->toBeIn(['image/jpeg', 'image/png']);
        expect($payload['content'])->toBeString()->not->toBeEmpty();
    });

    it('downscales an oversized image to under the Claude dimension and byte caps', function () {
        // Create a large image (3000x3000) that exceeds the 1568px long-edge limit
        $file = UploadedFile::fake()->image('large-invoice.jpg', 3000, 3000);

        $intake = new FileIntake();
        $payload = $intake->process($file);

        expect($payload['kind'])->toBe('image');

        // Decode the returned content to verify dimensions
        $imageData = base64_decode($payload['content']);
        $img = imagecreatefromstring($imageData);
        expect($img)->not->toBeFalse('GD should be able to parse the returned image');

        $width = imagesx($img);
        $height = imagesy($img);

        $longEdge = max($width, $height);
        expect($longEdge)->toBeLessThanOrEqual(MAX_LONG_EDGE, "Long edge {$longEdge} exceeds 1568px limit");


        // Byte cap check
        expect(strlen($imageData))->toBeLessThanOrEqual(MAX_IMAGE_BYTES, 'Encoded image exceeds 5MB byte cap');
    });

    it('payload exposes mime, content, and kind keys', function () {
        $file = UploadedFile::fake()->image('invoice.png', 200, 200);

        $intake = new FileIntake();
        $payload = $intake->process($file);

        expect($payload)->toHaveKeys(['mime', 'content', 'kind']);
    });
});

describe('ValidateInvoiceRequest', function () {
    it('rejects an unsupported file type with validation failure', function () {
        $file = UploadedFile::fake()->create('invoice.txt', 100, 'text/plain');

        $rules = (new ValidateInvoiceRequest())->rules();
        $validator = validator(['file' => $file], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('file'))->toBeTrue();
    });

    it('rejects an oversized file with validation failure', function () {
        // Create a file over 20MB (20481 KB)
        $file = UploadedFile::fake()->create('invoice.pdf', 20481, 'application/pdf');

        $rules = (new ValidateInvoiceRequest())->rules();
        $validator = validator(['file' => $file], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('file'))->toBeTrue();
    });

    it('accepts a valid PDF upload', function () {
        $file = UploadedFile::fake()->create('invoice.pdf', 1000, 'application/pdf');

        $rules = (new ValidateInvoiceRequest())->rules();
        $validator = validator(['file' => $file], $rules);

        expect($validator->passes())->toBeTrue();
    });

    it('accepts a valid JPEG upload', function () {
        $file = UploadedFile::fake()->image('invoice.jpg', 800, 600);

        $rules = (new ValidateInvoiceRequest())->rules();
        $validator = validator(['file' => $file], $rules);

        expect($validator->passes())->toBeTrue();
    });

    it('accepts a valid PNG upload', function () {
        $file = UploadedFile::fake()->image('invoice.png', 800, 600);

        $rules = (new ValidateInvoiceRequest())->rules();
        $validator = validator(['file' => $file], $rules);

        expect($validator->passes())->toBeTrue();
    });

    it('rejects when no file is submitted', function () {
        $rules = (new ValidateInvoiceRequest())->rules();
        $validator = validator([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('file'))->toBeTrue();
    });
});
