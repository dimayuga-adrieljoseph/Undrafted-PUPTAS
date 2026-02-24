<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Program;

/**
 * Form Request for submitting an application
 * 
 * Validates application submission data including program choices.
 */
class SubmitApplicationRequest extends FormRequest
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
            'program_id' => [
                'required',
                'integer',
                Rule::exists(Program::class, 'id'),
            ],
            'second_choice_id' => [
                'nullable',
                'integer',
                Rule::exists(Program::class, 'id'),
                function ($attribute, $value, $fail) {
                    if ($value && $value == $this->input('program_id')) {
                        $fail('Second choice must be different from the first choice.');
                    }
                },
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
            'program_id.required' => 'Please select a program.',
            'program_id.exists' => 'The selected program does not exist.',
            'second_choice_id.exists' => 'The selected second choice program does not exist.',
        ];
    }
}
