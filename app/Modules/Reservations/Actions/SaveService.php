<?php

namespace App\Modules\Reservations\Actions;

use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Reservations\Models\Resource;
use App\Modules\Reservations\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveService
{
    public function __construct(private CurrentTenant $tenant, private StaffDirectory $staff, private Entitlements $entitlements) {}

    /**
     * Save a service with the resources that can host it and the employees who can perform it,
     * all of the service's company.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $resourceIds
     * @param  list<int>  $employeeIds
     */
    public function handle(Service $service, array $data, array $resourceIds = [], array $employeeIds = []): Service
    {
        $companyId = (int) ($data['company_id'] ?? $service->company_id);
        $resourceIds = array_values(array_unique(array_map('intval', $resourceIds)));
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if (Resource::query()->where('company_id', $companyId)->whereIn('id', $resourceIds)->count() !== count($resourceIds)) {
            throw ValidationException::withMessages(['resource_ids' => __('reservations.errors.company_mismatch')]);
        }

        if ($employeeIds !== [] && $this->entitlements->isActive(Module::Hr)) {
            foreach ($employeeIds as $employeeId) {
                if ($this->staff->employee($employeeId)?->companyId !== $companyId) {
                    throw ValidationException::withMessages(['employee_ids' => __('reservations.errors.company_mismatch')]);
                }
            }
        }

        return DB::transaction(function () use ($service, $data, $companyId, $resourceIds, $employeeIds): Service {
            $service->fill([...$data, 'company_id' => $companyId]);

            if (! $service->exists && empty($service->client_id)) {
                $service->client_id = $this->tenant->client()->id;
            }

            $service->save();
            $service->resources()->sync($resourceIds);
            $service->getConnection()->table('res__service_staff')->where('service_id', $service->id)->delete();
            $service->getConnection()->table('res__service_staff')->insert(array_map(fn (int $id): array => ['service_id' => $service->id, 'employee_id' => $id], $employeeIds));

            return $service->load('resources');
        });
    }
}
