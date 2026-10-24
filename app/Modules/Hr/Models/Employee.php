<?php

namespace App\Modules\Hr\Models;

use App\Models\Concerns\HasBilingualFields;
use App\Modules\Hr\Database\Factories\EmployeeFactory;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\Gender;
use App\Modules\Hr\Enums\IdType;
use App\Modules\Hr\Support\BlindIndex;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Models\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person employed by one company, with a home branch. company_id, branch_id, status,
 * user_id and national_id_hash are set by the actions with forceFill.
 */
#[Table('hr__employees')]
#[Fillable([
    'section_id', 'job_id', 'manager_id', 'employee_number', 'name_ar', 'name_en', 'email', 'phone',
    'id_type', 'national_id', 'nationality', 'gender', 'birth_date', 'hired_on', 'iban', 'notes',
])]
#[Hidden(['national_id', 'iban', 'national_id_hash'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToClient, HasBilingualFields, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'national_id' => 'encrypted',
            'iban' => 'encrypted',
            'gender' => Gender::class,
            'status' => EmployeeStatus::class,
            'birth_date' => 'date',
            'hired_on' => 'date',
            'terminated_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            if ($employee->isDirty('national_id')) {
                $employee->national_id_hash = BlindIndex::of($employee->national_id);
            }
        });
    }

    /**
     * Rows the given branch set reaches (company-wide grants cover every branch of the company).
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function visibleIn(Builder $query, BranchSet $branches): void
    {
        $branches->apply($query, $this->qualifyColumn('company_id'), $this->qualifyColumn('branch_id'));
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('starts_on');
    }

    /**
     * @return HasOne<Contract, $this>
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('status', 'active');
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * @return HasMany<EmployeeMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(EmployeeMovement::class)->orderByDesc('starts_on');
    }

    /**
     * @return HasMany<EmployeePlacement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(EmployeePlacement::class)->orderByDesc('starts_on');
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function maskedIban(): ?string
    {
        return $this->iban ? '****'.substr($this->iban, -4) : null;
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }
}
