<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Program;
use App\Models\Application;

/**
 * Form Request for changing an applicant's course/program
 * 
 * Validates course change data ensuring the new program exists
 * and differs from the current program assignment.
 */
class ChangeCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * 
     * Authorization is handled by ApplicationPolicy, so this returns true.
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
                function ($attribute, $value, $fail) {
                    // Get the applicant's current application
                    $applicantId = $this->route('applicantId');
                    $application = Application::where('user_id', $applicantId)->first();
                    
                    // Check if the new program_id is different from current program_id
                    if ($application && $application->program_id == $value) {
                        $fail('The selected program must be different from the current program.');
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
            'program_id.integer' => 'The program ID must be an integer.',
            'program_id.exists' => 'The selected program does not exist.',
        ];
    }
}
