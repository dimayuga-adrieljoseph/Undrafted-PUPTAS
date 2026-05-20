<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for checking public admission status
 *
 * Validates the reference number, first name, and last name submitted by an applicant
 * on the public status checker endpoint.
 */
class CheckStatusRequest extends FormRequest
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
            'referenceNumber' => [
                'required',
                'string',
                'max:55',
                'regex:/^[\d\-]+$/',
            ],
            'firstName' => [
                'required',
                'string',
                'max:55',
            ],
            'lastName' => [
                'required',
                'string',
                'max:55',
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
            'referenceNumber.required' => 'Reference number is required.',
            'referenceNumber.max'      => 'Reference number must not exceed 55 characters.',
            'referenceNumber.regex'    => 'Reference number may only contain digits and hyphens.',
            'firstName.required'       => 'First name is required.',
            'firstName.max'            => 'First name must not exceed 55 characters.',
            'lastName.required'        => 'Last name is required.',
            'lastName.max'             => 'Last name must not exceed 55 characters.',
        ];
    }
}
