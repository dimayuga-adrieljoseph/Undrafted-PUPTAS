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
            'referenceNumber' => ['required', 'string', 'max:255'],
            'firstName'       => ['required', 'string', 'max:255'],
            'lastName'        => ['required', 'string', 'max:255'],
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
            'firstName.required'       => 'First name is required.',
            'lastName.required'        => 'Last name is required.',
        ];
    }
}
