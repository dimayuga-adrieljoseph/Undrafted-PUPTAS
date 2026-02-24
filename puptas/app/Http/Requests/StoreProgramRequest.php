<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Program;

/**
 * Form Request for storing a new program
 * 
 * Validates program creation data including strand associations.
 */
class StoreProgramRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Program::class, 'name'),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Program::class, 'code'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'english' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'math' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'science' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'strand_ids' => ['nullable', 'array'],
            'strand_ids.*' => ['exists:strands,id'],
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
            'name.required' => 'The program name is required.',
            'name.unique' => 'A program with this name already exists.',
            'code.required' => 'The program code is required.',
            'code.unique' => 'A program with this code already exists.',
            'strand_ids.*.exists' => 'One or more selected strands do not exist.',
        ];
    }
}
