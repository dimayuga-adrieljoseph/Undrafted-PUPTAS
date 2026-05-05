<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for checking public admission status
 *
 * Validates the reference number and email submitted by an applicant
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
            'email'           => ['required', 'string', 'email', 'max:255'],
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
            'email.required'           => 'Email address is required.',
            'email.email'              => 'Please enter a valid email address.',
        ];
    }
}
