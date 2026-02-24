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
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
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
        return [
            'field' => [
                'required',
                'string',
                'in:' . FileMapper::getValidFileFields(),
            ],
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:2048',
            ],
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
            'field.in' => 'The specified field is not a valid file upload field.',
            'file.required' => 'Please select a file to upload.',
            'file.mimes' => 'Only JPG, JPEG, PNG, and PDF files are allowed.',
            'file.max' => 'The file size must not exceed 2MB.',
        ];
    }
}