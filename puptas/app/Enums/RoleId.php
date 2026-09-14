<?php

namespace App\Enums;

/**
 * Authoritative mapping of PUPTAS role IDs.
 *
 * These numeric IDs mirror the `roles` table seeded in
 * `database/migrations/2025_02_02_073206_create_roles_table.php`.
 *
 * Any code that compares or assigns user roles should reference these
 * constants instead of hardcoding raw integers, so that changing an ID
 * requires a single edit.
 */
enum RoleId: int
{
    case Applicant = 1;
    case Admin = 2;
    case DocumentEvaluator = 3;
    case Interviewer = 4;
    case Medical = 5;
    case Registrar = 6;
    case SuperAdmin = 7;
    case GradeEvaluator = 8;

    /**
     * Human-readable labels keyed by role ID.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            self::Applicant->value => 'Applicant',
            self::Admin->value => 'Admin',
            self::DocumentEvaluator->value => 'Document Evaluator',
            self::Interviewer->value => 'Interviewer',
            self::Medical->value => 'Medical',
            self::Registrar->value => 'Registrar',
            self::SuperAdmin->value => 'Superadmin',
            self::GradeEvaluator->value => 'Grade Evaluator',
        ];
    }

    /**
     * @return int[]
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    /**
     * Resolve a role ID to its label, with a safe fallback.
     */
    public static function label(int $id): string
    {
        return self::names()[$id] ?? "Role {$id}";
    }
}