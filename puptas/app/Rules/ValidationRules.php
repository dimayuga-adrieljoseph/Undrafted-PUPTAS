<?php

namespace App\Rules;

use Illuminate\Validation\Rule;

/**
 * Validation Rules for PUPTAS
 * 
 * Centralized validation rules for all models to ensure consistency
 */
class ValidationRules
{
    /**
     * User validation rules
     */
    public static function userStore()
    {
        return [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extension_name' => 'nullable|string|in:Jr.,Sr.,II,III,IV', // Added
            'email' => 'required|string|email|max:255|unique:users|regex:/^[a-z0-9._%+\-]+@gmail\.com$/', // Added Gmail validation
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}$/',
            'role_id' => 'required|integer|in:1,2,3,4,5,6', // Changed from exists:roles,id
            'program' => 'nullable|string|exists:programs,code', // Added for evaluators & interviewers
            'applicant_program' => 'nullable|string|exists:programs,code', // Added for applicants
            // These fields might not be in your form anymore, but keeping for compatibility
            'birthday' => 'nullable|date',
            'sex' => 'nullable|in:M,F',
            'contactnumber' => 'required|string|regex:/^\d{10}$/', // Changed to 10-digit validation
            'address' => 'nullable|string|max:500',
            'salutation' => 'nullable|in:Mr.,Mrs.,Ms.,Dr.,Prof.', // Keeping if you still use it
        ];
    }

    public static function userUpdate($userId)
    {
        return [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'middlename' => 'nullable|string|max:255',
            'extension_name' => 'nullable|string|in:Jr.,Sr.,II,III,IV', // Added
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
                'regex:/^[a-z0-9._%+\-]+@gmail\.com$/'
            ], // Added Gmail validation with proper Rule syntax
            'password' => 'nullable|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}$/',
            'role_id' => 'required|integer|in:1,2,3,4,5,6', // Changed from exists:roles,id
            'program' => 'nullable|string|exists:programs,code', // Added for evaluators & interviewers
            'applicant_program' => 'nullable|string|exists:programs,code', // Added for applicants

            'birthday' => 'nullable|date',
            'sex' => 'nullable|in:M,F',
            'contactnumber' => 'required|string|regex:/^\d{10}$/', // Changed to 10-digit validation
            'address' => 'nullable|string|max:500',
            'salutation' => 'nullable|in:Mr.,Mrs.,Ms.,Dr.,Prof.', // Keeping if you still use it
        ];
    }

    /**
     * Program validation rules
     */
    public static function programStore()
    {
        return [
            'code' => 'required|string|unique:programs,code',
            'name' => 'required|string|max:255',
            'strand' => 'nullable|string|max:100',
            'math' => 'nullable|numeric|min:0|max:100',
            'science' => 'nullable|numeric|min:0|max:100',
            'english' => 'nullable|numeric|min:0|max:100',
            'gwa' => 'nullable|numeric|min:1|max:100',
            'pupcet' => 'nullable|numeric|min:0',
            'slots' => 'required|integer|min:1',
        ];
    }

    public static function programUpdate($programId)
    {
        return [
            'code' => "required|string|unique:programs,code,{$programId}",
            'name' => 'required|string|max:255',
            'strand' => 'nullable|string|max:100',
            'math' => 'nullable|numeric|min:0|max:100',
            'science' => 'nullable|numeric|min:0|max:100',
            'english' => 'nullable|numeric|min:0|max:100',
            'gwa' => 'nullable|numeric|min:1|max:100',
            'pupcet' => 'nullable|numeric|min:0',
            'slots' => 'required|integer|min:1',
        ];
    }

    /**
     * Grade validation rules
     */
    public static function gradeStore()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'english' => 'required|numeric|min:0|max:100',
            'mathematics' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
        ];
    }

    public static function gradeImport()
    {
        return [
            'email' => 'required|email|exists:users,email',
            'english' => 'required|numeric|min:0|max:100',
            'mathematics' => 'required|numeric|min:0|max:100',
            'science' => 'required|numeric|min:0|max:100',
            'g12_first_sem' => 'required|numeric|min:0|max:100',
            'g12_second_sem' => 'required|numeric|min:0|max:100',
        ];
    }

    /**
     * Application validation rules
     */
    public static function applicationStore()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'program_id' => 'required|exists:programs,id',
            'second_choice_id' => 'nullable|exists:programs,id|different:program_id',
            'status' => 'nullable|in:draft,submitted,endorsed,accepted,returned,rejected',
            'enrollment_status' => 'nullable|in:temporary,officially_enrolled',
            'enrollment_position' => 'nullable|integer|min:1',
        ];
    }

    public static function applicationUpdate($applicationId)
    {
        return [
            'program_id' => 'required|exists:programs,id',
            'second_choice_id' => 'nullable|exists:programs,id|different:program_id',
            'status' => 'nullable|in:draft,submitted,endorsed,accepted,returned,rejected',
            'enrollment_status' => 'nullable|in:temporary,officially_enrolled',
            'enrollment_position' => 'nullable|integer|min:1',
        ];
    }

    /**
     * ApplicationProcess validation rules
     */
    public static function applicationProcessStore()
    {
        return [
            'application_id' => 'required|exists:applications,id',
            'stage' => 'required|in:submitted,evaluator,interview,medical,record',
            'status' => 'required|in:pending,reviewed,endorsed,returned,accepted,rejected',
            'action' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'decision_reason' => 'nullable|string|max:500',
            'reviewer_notes' => 'nullable|string|max:1000',
            'files_affected' => 'nullable|array',
            'ip_address' => 'nullable|ip',
        ];
    }

    /**
     * UserFile validation rules
     */
    public static function userFileUpload()
    {
        return [
            'email' => 'required|email|exists:users,email',
            'application_id' => 'nullable|exists:applications,id',
            'file11' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'file12' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'file11Front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'file12Front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'fileId' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'fileNonEnroll' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'filePSA' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'fileGoodMoral' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'fileUnderOath' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'filePhoto2x2' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];
    }

    public static function userFileStore()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'application_id' => 'nullable|exists:applications,id',
            'application_process_id' => 'nullable|exists:application_processes,id',
            'type' => 'required|string|max:50',
            'file_path' => 'required|string',
            'original_name' => 'required|string|max:255',
            'status' => 'nullable|in:pending,approved,returned,rejected',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Schedule validation rules
     */
    public static function scheduleStore()
    {
        return [
            'name' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'type' => 'required|in:application,interview,medical,announcement,other',
            'description' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'affected_programs' => 'nullable|array',
        ];
    }

    public static function scheduleUpdate($scheduleId)
    {
        return [
            'name' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
            'type' => 'required|in:application,interview,medical,announcement,other',
            'description' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'affected_programs' => 'nullable|array',
        ];
    }

    /**
     * TestPasser validation rules
     */
    public static function testPasserStore()
    {
        return [
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'school_address' => 'nullable|string|max:500',
            'shs_school' => 'required|string|max:255',
            'strand' => 'required|string|max:100',
            'year_graduated' => 'required|digits:4|integer|min:1900|max:' . (date('Y') + 1),
            'email' => 'required|email|unique:test_passers,email',
            'reference_number' => 'required|string|max:100|unique:test_passers,reference_number',
            'batch_number' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,registered,application_completed',
        ];
    }

    public static function testPasserUpdate($testPasserId)
    {
        return [
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'school_address' => 'nullable|string|max:500',
            'shs_school' => 'required|string|max:255',
            'strand' => 'required|string|max:100',
            'year_graduated' => 'required|digits:4|integer|min:1900|max:' . date('Y'),
            'email' => "required|email|unique:test_passers,email,{$testPasserId},test_passer_id",
            'reference_number' => "required|string|max:100|unique:test_passers,reference_number,{$testPasserId},test_passer_id",
            'batch_number' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,registered,application_completed',
        ];
    }
}
