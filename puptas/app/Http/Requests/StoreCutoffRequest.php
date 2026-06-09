<?php

namespace App\Http\Requests;

use App\Rules\FutureDatetimeRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for storing a new application submission cutoff datetime.
 *
 * Authorization is delegated to the EnsureSuperAdmin middleware on the route,
 * so authorize() always returns true here.
 */
class StoreCutoffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route is already protected by EnsureSuperAdmin middleware.
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
            'cutoff_at' => ['required', 'date', new FutureDatetimeRule(minimumMinutes: 1)],
        ];
    }
}
