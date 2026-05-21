<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\FileMapper;

/**
 * Form Request for reuploading a file
 *
 * Validates file reupload requests including field names and file types.
 */
class ReuploadFileRequest extends FormRequest
{
    /** Grade card fields are image-only (they go through compression + OCR). */
    private const IMAGE_ONLY_FIELDS = [
        'file10', 'file10Front',
        'file11', 'file11Front',
        'file12', 'file12Front',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $field = $this->input('field');
        $isImageOnly = in_array($field, self::IMAGE_ONLY_FIELDS, true);

        $fileRules = $isImageOnly
            ? ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120']
            : ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:10240'];

        return [
            'field' => [
                'required',
                'string',
                'in:' . FileMapper::getValidFileFields(),
            ],
            'file' => $fileRules,
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'field.required' => 'The file field name is required.',
            'field.in'       => 'The specified field is not a valid file upload field.',
            'file.required'  => 'Please select a file to upload.',
            'file.image'     => 'Please upload an image file.',
            'file.mimes'     => 'Only JPG, JPEG, PNG, WebP, GIF, and PDF files are allowed.',
            'file.max'       => 'The file size must not exceed the allowed limit.',
        ];
    }
}