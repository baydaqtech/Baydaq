<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Actions\DisposeAsset;
use App\Modules\Assets\Actions\ManageAudit;
use App\Modules\Assets\Actions\ManageCustody;
use App\Modules\Assets\Actions\ManageMaintenance;
use App\Modules\Assets\Actions\SaveAsset;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Category;
use App\Modules\Hr\Actions\Employees\ChangeEmployeeStatus;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AssetLifecycleTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-06-15 09:00:00');
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        $this->laptops = (new Category(['name_ar' => 'حواسيب', 'name_en' => 'Laptops', 'useful_life_months' => 36]))->forceFill(['client_id' => $this->client->id]);
        $this->laptops->save();
    }

    private function asset(array $attributes = [], ?int $branchId = null): Asset
    {
        return app(SaveAsset::class)->handle(new Asset, ['category_id' => $this->laptops->id, 'name_ar' => 'لابتوب', 'name_en' => 'Laptop', 'purchase_date' => '2025-06-15', 'cost' => '3700.00', 'salvage_value' => '100.00', ...$attributes], $branchId ?? $this->headOffice->id);
    }

    public function test_assets_get_tags_and_depreciate_on_a_straight_line(): void
    {
        $first = $this->asset();
        $second = $this->asset(['cost' => '1000.00', 'salvage_value' => '0']);

        $this->assertSame('AST-00001', $first->tag);
        $this->assertSame('AST-00002', $second->tag);
        $this->assertSame('2500.00', $first->bookValue(CarbonImmutable::parse('2026-06-15')));  // 12 of 36 months: 3700 − 12 × 100
        $this->assertSame('100.00', $first->bookValue(CarbonImmutable::parse('2030-01-01')));   // never below salvage
        $this->assertSame('3700.00', $first->bookValue(CarbonImmutable::parse('2025-05-01')));  // before purchase

        $otherCompany = Company::factory()->for($this->organization)->create(['client_id' => $this->client->id]);
        $otherBranch = Branch::factory()->for($otherCompany)->create(['client_id' => $this->client->id]);
        $this->expectException(ValidationException::class);
        app(SaveAsset::class)->handle($first, ['name_en' => 'Moved'], $otherBranch->id); // assets stay in their company
    }

    public function test_custody_blocks_terminating_the_employee_until_returned(): void
    {
        $asset = $this->asset();
        $employee = $this->employeeAt($this->headOffice);

        $custody = app(ManageCustody::class)->assign($asset, $employee->id, '2026-06-15', 'New');
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status);

        try {
            app(ManageCustody::class)->assign($asset->fresh(), $employee->id, '2026-06-15');
            $this->fail('An asset was handed out twice');
        } catch (ValidationException) {
        }

        try {
            app(ChangeEmployeeStatus::class)->handle($employee, EmployeeStatus::Terminated);
            $this->fail('An employee holding custody was terminated');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('AST-00001', $e->errors()['status'][0]);
        }

        app(ManageCustody::class)->return($custody, '2026-06-20', 'Scratched lid');
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        app(ChangeEmployeeStatus::class)->handle($employee->fresh(), EmployeeStatus::Terminated);
        $this->assertSame(EmployeeStatus::Terminated, $employee->fresh()->status);
    }

    public function test_maintenance_takes_an_asset_out_of_service_and_back_to_its_holder(): void
    {
        $asset = $this->asset();
        $employee = $this->employeeAt($this->headOffice);
        $custody = app(ManageCustody::class)->assign($asset, $employee->id, '2026-06-15');

        $job = app(ManageMaintenance::class)->open($asset->fresh(), ['kind' => 'repair', 'description' => 'Screen flickers', 'opened_on' => '2026-06-16', 'reported_by_employee_id' => $employee->id]);
        $this->assertSame(AssetStatus::Maintenance, $asset->fresh()->status);

        try {
            app(DisposeAsset::class)->handle($asset->fresh(), AssetStatus::Disposed, '2026-06-17');
            $this->fail('An asset in custody and maintenance was written off');
        } catch (ValidationException) {
        }

        app(ManageMaintenance::class)->close($job, '2026-06-18', '250.00', 'Replaced cable');
        $this->assertSame(AssetStatus::Assigned, $asset->fresh()->status); // still with the employee

        app(ManageCustody::class)->return($custody, '2026-06-19');
        app(DisposeAsset::class)->handle($asset->fresh(), AssetStatus::Disposed, '2026-06-20', '800.00', 'Sold');
        $this->assertSame(AssetStatus::Disposed, $asset->fresh()->status);
    }

    public function test_a_stock_take_finds_missing_and_unexpected_assets(): void
    {
        $present = $this->asset();
        $missing = $this->asset();
        $elsewhere = $this->asset([], $this->branch('RUH')->id);
        $this->asset()->forceFill(['status' => 'disposed'])->save(); // written off: not expected

        $audit = app(ManageAudit::class)->start($this->headOffice->id);
        $this->assertSame(2, $audit->items()->count());

        try {
            app(ManageAudit::class)->start($this->headOffice->id);
            $this->fail('Two stock-takes at one branch');
        } catch (ValidationException) {
        }

        app(ManageAudit::class)->scan($audit, strtolower($present->tag));
        $this->assertSame('unexpected', app(ManageAudit::class)->scan($audit, $elsewhere->tag)->status->value);

        app(ManageAudit::class)->close($audit, markMissingLost: true);
        $this->assertSame(['found', 'missing', 'unexpected'], $audit->items()->orderBy('asset_id')->get()->map(fn ($item) => $item->status->value)->all());
        $this->assertSame(AssetStatus::Lost, $missing->fresh()->status);
        $this->assertSame(AssetStatus::Available, $elsewhere->fresh()->status);
    }
}
