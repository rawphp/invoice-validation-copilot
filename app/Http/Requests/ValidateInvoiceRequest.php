<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateInvoiceRequest extends FormRequest
{
    /**
     * 20 MB max — plenty for phone-camera photos and PDFs, well below
     * the 100 MB HTTP limit but small enough to catch accidental uploads.
     */
    private const MAX_SIZE_KB = 20480;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:application/pdf,image/png,image/jpeg',
                'max:' . self::MAX_SIZE_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'  => 'An invoice file is required.',
            'file.mimetypes' => 'Only PDF, PNG, and JPEG files are accepted.',
            'file.max'       => 'The file must not exceed 20 MB.',
        ];
    }
}
