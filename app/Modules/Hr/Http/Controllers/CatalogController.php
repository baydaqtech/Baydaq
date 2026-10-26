<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Catalog\SaveAllowanceType;
use App\Modules\Hr\Actions\Catalog\SaveDocumentType;
use App\Modules\Hr\Actions\Catalog\SaveJob;
use App\Modules\Hr\Actions\Catalog\SaveSection;
use App\Modules\Hr\Enums\AllowanceCalculation;
use App\Modules\Hr\Enums\DocumentFieldType;
use App\Modules\Hr\Enums\LeaveAccrual;
use App\Modules\Hr\Enums\LeaveYearBasis;
use App\Modules\Hr\Http\Requests\SaveCatalogItemRequest;
use App\Modules\Hr\Http\Resources\CatalogResource;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Section;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Data\CompanyRef;
use App\Modules\Platform\Http\Controllers\Concerns\AuthorizesSuite;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Access;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/**
 * The four HR catalogs on one page: sections, jobs, allowance types, document types.
 * They are owner-wide, so viewing needs the catalog key anywhere; writing needs it account-wide.
 */
class CatalogController extends Controller
{
    use AuthorizesSuite, RendersSuitePages;

    private const MODELS = [
        'sections' => Section::class, 'jobs' => Job::class, 'allowance-types' => AllowanceType::class, 'document-types' => DocumentType::class,
        'bonus-types' => BonusType::class, 'deduction-types' => DeductionType::class, 'leave-types' => LeaveType::class,
    ];

    public function index(OrgDirectory $directory): Response
    {
        return $this->suitePage('Hr/Catalog/Index', [
            'sections' => CatalogResource::collection(Section::query()->orderBy('sort_order')->orderBy('name_en')->get()),
            'jobs' => CatalogResource::collection(Job::query()->with('sections')->orderBy('sort_order')->orderBy('name_en')->get()),
            'allowance_types' => CatalogResource::collection(AllowanceType::query()->orderBy('sort_order')->get()),
            'document_types' => CatalogResource::collection(DocumentType::query()->orderBy('sort_order')->get()),
            'bonus_types' => CatalogResource::collection(BonusType::query()->orderBy('sort_order')->get()),
            'deduction_types' => CatalogResource::collection(DeductionType::query()->orderBy('sort_order')->get()),
            'leave_types' => CatalogResource::collection(LeaveType::query()->orderBy('sort_order')->get()),
            'companies' => collect($directory->companies())->map(fn (CompanyRef $company): array => ['value' => $company->id, 'label' => $company->name])->all(),
            'settings' => app(HrSettings::class)->all(),
            'roles' => collect(app(Access::class)->roles())->map(fn (array $role): array => ['value' => $role['id'], 'label' => $role['name']])->all(),
            'calculations' => AllowanceCalculation::options(),
            'field_types' => DocumentFieldType::options(),
            'leave_accruals' => LeaveAccrual::options(),
            'leave_year_bases' => LeaveYearBasis::options(),
        ], ['hr']);
    }

    public function store(SaveCatalogItemRequest $request, string $catalog): RedirectResponse
    {
        $this->authorizeAccount('hr.catalog.create');
        $this->save($request, $catalog, new (self::MODELS[$catalog] ?? abort(404)));
        $this->toast(__('hr.catalog.saved'));

        return back();
    }

    public function update(SaveCatalogItemRequest $request, string $catalog, int $item): RedirectResponse
    {
        $this->authorizeAccount('hr.catalog.update');
        $model = (self::MODELS[$catalog] ?? abort(404))::query()->findOrFail($item);
        $this->save($request, $catalog, $model);
        $this->toast(__('hr.catalog.saved'));

        return back();
    }

    private function save(SaveCatalogItemRequest $request, string $catalog, Model $model): void
    {
        $data = $request->validated();

        match ($catalog) {
            'sections' => app(SaveSection::class)->handle($model, collect($data)->except('blocked_company_ids')->all(), $data['blocked_company_ids'] ?? []),
            'jobs' => app(SaveJob::class)->handle($model, collect($data)->except(['section_ids', 'blocked_company_ids'])->all(), $data['section_ids'], $data['blocked_company_ids'] ?? []),
            'allowance-types' => app(SaveAllowanceType::class)->handle($model, $data),
            'document-types' => app(SaveDocumentType::class)->handle($model, $data),
            'bonus-types', 'deduction-types', 'leave-types' => $model->fill($data)->save(),
        };
    }
}
