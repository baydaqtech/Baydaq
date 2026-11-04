<?php

namespace App\Modules\Hr\Actions\Assistant;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Ports\Intelligence;
use App\Modules\Platform\Ports\OrgDirectory;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

/**
 * The HR assistant (H15): answers questions in Arabic or English from HR data, through tools
 * that each check the asker's own permission and scope. Salaries, IBANs and ID numbers are
 * never exposed to it.
 */
class AnswerHrQuestion
{
    private const LIMIT = 50;

    /** @var array<int, string> */
    private array $branchNames = [];

    public function __construct(private Intelligence $intelligence, private OrgDirectory $directory) {}

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $conversation
     * @param  Closure(string): BranchSet  $reach  the branches a permission key reaches for the asker
     */
    public function handle(array $conversation, Closure $reach): string
    {
        $this->branchNames = collect($this->directory->branches())->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name])->all();
        $today = CarbonImmutable::now('Asia/Riyadh');

        $system = "You are the HR assistant of a Saudi business in the Baydaq suite. Today is {$today->toDateString()} (Asia/Riyadh). "
            .'Answer in the language of the question (Arabic or English), briefly, with names and numbers. '
            .'Use only what the tools return. Every tool only sees the branches the asker is allowed to see; when a tool says access is denied or returns nothing, '
            .'say plainly that you cannot see that data rather than guessing. You cannot change anything and you never see salaries, IBANs or ID numbers.';

        return $this->intelligence->converse($system, $conversation, $this->tools(), function (string $name, array $input) use ($reach, $today): mixed {
            try {
                return match ($name) {
                    'find_employees' => $this->findEmployees($input, $reach),
                    'headcount' => $this->headcount($input, $reach),
                    'expiring_documents' => $this->expiringDocuments($input, $reach, $today),
                    'on_leave' => $this->onLeave($input, $reach, $today),
                    'attendance_today' => $this->attendanceToday($reach, $today),
                    default => ['error' => 'unknown tool'],
                };
            } catch (AccessDenied) {
                return ['error' => 'access denied: the asker\'s role does not cover this data'];
            }
        });
    }

    /**
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    private function tools(): array
    {
        $object = fn (array $properties): array => ['type' => 'object', 'properties' => $properties ?: new stdClass, 'additionalProperties' => false];

        return [
            ['name' => 'find_employees', 'description' => 'Search employees by name, employee number or job, optionally in one branch or with one status. Returns at most 50.',
                'input_schema' => $object(['query' => ['type' => 'string'], 'branch' => ['type' => 'string', 'description' => 'Branch name or code'], 'status' => ['type' => 'string', 'enum' => array_column(EmployeeStatus::cases(), 'value')], 'nationality' => ['type' => 'string', 'description' => 'ISO country code, e.g. SA']])],
            ['name' => 'headcount', 'description' => 'Count active employees grouped by branch, section, job, nationality or gender.',
                'input_schema' => $object(['group_by' => ['type' => 'string', 'enum' => ['branch', 'section', 'job', 'nationality', 'gender']]])],
            ['name' => 'expiring_documents', 'description' => 'Documents (iqama, passport, insurance...) expiring within N days, including already expired ones.',
                'input_schema' => $object(['within_days' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 365], 'branch' => ['type' => 'string']])],
            ['name' => 'on_leave', 'description' => 'Employees on approved leave on a date (default today).',
                'input_schema' => $object(['date' => ['type' => 'string', 'description' => 'YYYY-MM-DD']])],
            ['name' => 'attendance_today', 'description' => 'Per branch: active employees and how many punched in today.', 'input_schema' => $object([])],
        ];
    }

    /**
     * @param  Closure(string): BranchSet  $reach
     * @return Builder<Employee>|null
     */
    private function employees(Closure $reach, HrFeature $feature): ?Builder
    {
        $set = $reach($feature->key(FeatureAction::View));

        return $set->isEmpty() ? null : Employee::query()->visibleIn($set);
    }

    private function branchId(?string $branch): ?int
    {
        if ($branch === null || trim($branch) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($branch));

        foreach ($this->directory->branches() as $ref) {
            if (in_array($needle, [mb_strtolower($ref->code), mb_strtolower($ref->nameAr), mb_strtolower($ref->nameEn)], true) || str_contains(mb_strtolower($ref->nameEn).' '.$ref->nameAr, $needle)) {
                return $ref->id;
            }
        }

        return -1;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Closure(string): BranchSet  $reach
     * @return array<string, mixed>
     */
    private function findEmployees(array $input, Closure $reach): array
    {
        $query = $this->employees($reach, HrFeature::Employees) ?? throw new AccessDenied;
        $branchId = $this->branchId($input['branch'] ?? null);
        $term = trim((string) ($input['query'] ?? ''));

        $rows = $query->with('job')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereAny(['name_ar', 'name_en', 'employee_number'], 'like', "%{$term}%")->orWhereHas('job', fn (Builder $job) => $job->whereAny(['name_ar', 'name_en'], 'like', "%{$term}%"))))
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($input['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($input['nationality'] ?? null, fn (Builder $query, string $code) => $query->where('nationality', strtoupper($code)))
            ->orderBy('name_en')->limit(self::LIMIT)->get();

        return ['employees' => $rows->map(fn (Employee $employee): array => [
            'name' => $employee->localized('name'), 'number' => $employee->employee_number, 'job' => $employee->job?->localized('name'),
            'branch' => $this->branchNames[$employee->branch_id] ?? null, 'status' => $employee->status->value, 'nationality' => $employee->nationality,
            'hired_on' => $employee->hired_on?->toDateString(),
        ])->all(), 'truncated' => $rows->count() === self::LIMIT];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Closure(string): BranchSet  $reach
     * @return array<string, mixed>
     */
    private function headcount(array $input, Closure $reach): array
    {
        $query = $this->employees($reach, HrFeature::Employees) ?? throw new AccessDenied;
        $groupBy = $input['group_by'] ?? 'branch';
        $employees = $query->with(['section', 'job'])->where('status', EmployeeStatus::Active)->get();

        $groups = $employees->groupBy(fn (Employee $employee): string => match ($groupBy) {
            'section' => (string) ($employee->section?->localized('name') ?? '—'),
            'job' => (string) ($employee->job?->localized('name') ?? '—'),
            'nationality' => (string) ($employee->nationality ?? '—'),
            'gender' => (string) ($employee->gender ?? '—'),
            default => $this->branchNames[$employee->branch_id] ?? '—',
        })->map->count()->sortDesc();

        return ['group_by' => $groupBy, 'total' => $employees->count(), 'groups' => $groups->all()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Closure(string): BranchSet  $reach
     * @return array<string, mixed>
     */
    private function expiringDocuments(array $input, Closure $reach, CarbonImmutable $today): array
    {
        $employees = $this->employees($reach, HrFeature::Documents) ?? throw new AccessDenied;
        $branchId = $this->branchId($input['branch'] ?? null);
        $until = $today->addDays(min(365, max(0, (int) ($input['within_days'] ?? 30))));

        $documents = EmployeeDocument::query()->with(['type', 'employee'])
            ->whereIn('employee_id', $employees->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))->whereIn('status', [EmployeeStatus::Active, EmployeeStatus::OnLeave])->select('id'))
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $until->toDateString())
            ->orderBy('expires_on')->limit(self::LIMIT)->get();

        return ['until' => $until->toDateString(), 'documents' => $documents->map(fn (EmployeeDocument $document): array => [
            'employee' => $document->employee?->localized('name'), 'branch' => $this->branchNames[$document->employee?->branch_id] ?? null,
            'type' => $document->type?->localized('name'), 'expires_on' => $document->expires_on->toDateString(),
            'days_left' => (int) $today->diffInDays($document->expires_on, false),
        ])->all()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  Closure(string): BranchSet  $reach
     * @return array<string, mixed>
     */
    private function onLeave(array $input, Closure $reach, CarbonImmutable $today): array
    {
        $employees = $this->employees($reach, HrFeature::Leave) ?? throw new AccessDenied;
        $date = is_string($input['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date']) ? $input['date'] : $today->toDateString();

        $leaves = LeaveRequest::query()->with(['employee', 'type'])->where('status', LeaveStatus::Approved)
            ->whereIn('employee_id', $employees->select('id'))
            ->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->limit(self::LIMIT)->get();

        return ['date' => $date, 'on_leave' => $leaves->map(fn (LeaveRequest $leave): array => [
            'employee' => $leave->employee?->localized('name'), 'branch' => $this->branchNames[$leave->employee?->branch_id] ?? null,
            'type' => $leave->type?->localized('name'), 'from' => $leave->starts_on->toDateString(), 'to' => $leave->ends_on->toDateString(),
        ])->all()];
    }

    /**
     * @param  Closure(string): BranchSet  $reach
     * @return array<string, mixed>
     */
    private function attendanceToday(Closure $reach, CarbonImmutable $today): array
    {
        $employees = $this->employees($reach, HrFeature::Attendance) ?? throw new AccessDenied;
        $active = $employees->where('status', EmployeeStatus::Active)->get(['id', 'branch_id']);
        $present = AttendancePunch::query()->whereIn('employee_id', $active->pluck('id'))->whereDate('work_date', $today->toDateString())->where('type', 'in')->distinct()->pluck('employee_id')->flip();

        return ['date' => $today->toDateString(), 'branches' => $active->groupBy('branch_id')->map(fn ($group, $branchId): array => [
            'branch' => $this->branchNames[$branchId] ?? null, 'active' => $group->count(), 'present' => $group->filter(fn (Employee $employee): bool => $present->has($employee->id))->count(),
        ])->values()->all()];
    }
}
