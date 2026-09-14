<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RoleId;

class Role extends Model
{
    use HasFactory;

    // TODO: consider uncommenting once mass-assigning role names is intended.
    protected $fillable = ['name'];

    // Convenient, self-documenting role references kept in sync with RoleId.
    public const APPLICANT = RoleId::Applicant->value;
    public const ADMIN = RoleId::Admin->value;
    public const DOCUMENT_EVALUATOR = RoleId::DocumentEvaluator->value;
    public const INTERVIEWER = RoleId::Interviewer->value;
    public const MEDICAL = RoleId::Medical->value;
    public const REGISTRAR = RoleId::Registrar->value;
    public const SUPERADMIN = RoleId::SuperAdmin->value;
    public const GRADE_EVALUATOR = RoleId::GradeEvaluator->value;

    /**
     * Role labels keyed by ID. Delegates to the single source of truth.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return RoleId::names();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
