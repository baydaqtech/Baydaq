<?php

namespace App\Modules\Hr\Actions\Engagement;

use App\Modules\Platform\Access\BranchSet;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Validation\ValidationException;

/**
 * Who a post or survey is for, and whether the author may address them: the whole account needs
 * account-wide reach, a company needs the whole company, a branch needs that branch.
 */
class Audience
{
    public function __construct(private OrgDirectory $directory) {}

    /**
     * @return array{company_id: ?int, branch_id: ?int}
     */
    public function resolve(?int $companyId, ?int $branchId, BranchSet $reach): array
    {
        if ($branchId !== null) {
            $branch = $this->directory->branch($branchId);

            if ($branch === null) {
                throw ValidationException::withMessages(['branch_id' => __('validation.exists', ['attribute' => 'branch'])]);
            }

            abort_unless($reach->allowsBranch($branch->id, $branch->companyId), 403);

            return ['company_id' => $branch->companyId, 'branch_id' => $branch->id];
        }

        if ($companyId !== null) {
            abort_unless($this->directory->company($companyId) !== null && $reach->allowsWholeCompany($companyId), 403);

            return ['company_id' => $companyId, 'branch_id' => null];
        }

        abort_unless($reach->all, 403);

        return ['company_id' => null, 'branch_id' => null];
    }

    /**
     * Places the author may address, for the form.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(BranchSet $reach): array
    {
        $options = $reach->all ? [['value' => 'all', 'label' => __('hr.engagement.everyone')]] : [];

        foreach ($this->directory->companies() as $company) {
            if ($reach->allowsWholeCompany($company->id)) {
                $options[] = ['value' => "c-{$company->id}", 'label' => $company->name];
            }

            foreach ($this->directory->branches($company->id) as $branch) {
                if ($reach->allowsBranch($branch->id, $company->id)) {
                    $options[] = ['value' => "b-{$branch->id}", 'label' => $company->name.' · '.$branch->name];
                }
            }
        }

        return $options;
    }

    /**
     * Turn a form value ("all", "c-3", "b-7") into ids.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function parse(?string $value): array
    {
        return match (true) {
            $value !== null && str_starts_with($value, 'c-') => [(int) substr($value, 2), null],
            $value !== null && str_starts_with($value, 'b-') => [null, (int) substr($value, 2)],
            default => [null, null],
        };
    }

    /** A short label for the audience of a row. */
    public function label(?int $companyId, ?int $branchId): string
    {
        if ($branchId !== null) {
            return $this->directory->branch($branchId)?->name ?? '';
        }

        return $companyId !== null ? ($this->directory->company($companyId)?->name ?? '') : __('hr.engagement.everyone');
    }
}
