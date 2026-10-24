<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Audit;
use App\Modules\Assets\Models\Category;
use App\Modules\Assets\Models\Custody;
use App\Modules\Assets\Models\Maintenance;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AssetPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-06-15 09:00:00');
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        $this->category = (new Category(['name_ar' => 'مركبات', 'name_en' => 'Vehicles', 'useful_life_months' => 60]))->forceFill(['client_id' => $this->client->id]);
        $this->category->save();
    }

    private function url(string $path): string
    {
        return '/suite/all/assets/'.ltrim($path, '/');
    }

    public function test_the_owner_registers_hands_out_services_and_counts_an_asset(): void
    {
        $driver = $this->employeeAt($this->headOffice);
        (new EmployeePlacement)->forceFill(['client_id' => $this->client->id, 'employee_id' => $driver->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();

        $this->actingAs($this->owner)->post($this->url('/'), [
            'branch_id' => $this->headOffice->id, 'category_id' => $this->category->id, 'name_ar' => 'سيارة توصيل', 'name_en' => 'Delivery van', 'serial' => 'VIN123',
            'purchase_date' => '2026-01-15', 'cost' => '90000', 'salvage_value' => '30000',
        ])->assertRedirect();
        $asset = Asset::query()->sole();
        $this->assertSame('AST-00001', $asset->tag);

        $this->actingAs($this->owner)->post($this->url("assets/{$asset->id}/custody"), ['employee_id' => $driver->id, 'assigned_on' => '2026-06-15', 'condition' => 'New'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url("assets/{$asset->id}/maintenance"), ['kind' => 'preventive', 'description' => 'Oil change', 'opened_on' => '2026-06-15'])->assertSessionHasNoErrors();
        $job = Maintenance::query()->sole();
        $this->actingAs($this->owner)->put($this->url("maintenance/{$job->id}/close"), ['closed_on' => '2026-06-15', 'cost' => '350'])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->url("assets/{$asset->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Assets/Show')->where('asset.status', 'assigned')->where('asset.book_value', '85000.00') // 5 of 60 months of 60 000
            ->has('custodies', 1)->where('maintenance.0.cost', '350.00')->has('schedule', 5)->has('history', 4));

        $this->actingAs($this->owner)->get($this->url('/'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Assets/Index')->where('totals.assigned', 1)->where('totals.cost', '90000.00')->where('assets.data.0.holder', $driver->localized('name')));

        $this->actingAs($this->owner)->put($this->url('custody/'.Custody::query()->value('id').'/return'), ['returned_on' => '2026-06-15'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url('audits'), ['branch_id' => $this->headOffice->id])->assertRedirect();
        $audit = Audit::query()->sole();
        $this->actingAs($this->owner)->post($this->url("audits/{$audit->id}/scan"), ['tag' => 'ast-00001'])->assertInertiaFlash('scanned.status', 'found');
        $this->actingAs($this->owner)->post($this->url("audits/{$audit->id}/scan"), ['tag' => 'AST-99999'])->assertSessionHasErrors('tag');
        $this->actingAs($this->owner)->post($this->url("audits/{$audit->id}/close"))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->get($this->url('audits'))->assertInertia(fn (Assert $page) => $page->where('audits.0.found', 1)->where('audits.0.missing', 0));
    }

    public function test_costs_stay_hidden_without_the_ability_and_branches_stay_scoped(): void
    {
        $asset = (new Asset(['category_id' => $this->category->id, 'name_ar' => 'مكيف', 'name_en' => 'AC', 'cost' => '5000']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'tag' => 'AST-00001']);
        $asset->save();
        $keeper = $this->memberWith(['assets.register.view', 'assets.register.update'], ScopeLevel::Company, $this->company);
        $ruh = $this->memberWith(['assets.register.view'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($keeper)->get($this->url("assets/{$asset->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page->where('asset.cost', null)->where('asset.book_value', null));
        $this->actingAs($keeper)->put($this->url("assets/{$asset->id}"), ['branch_id' => $this->headOffice->id, 'category_id' => $this->category->id, 'name_ar' => 'مكيف', 'name_en' => 'AC', 'cost' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('5000.00', (string) $asset->fresh()->cost); // cost unchanged without the ability

        $this->actingAs($ruh)->get($this->url("assets/{$asset->id}"))->assertForbidden();
        $this->actingAs($ruh)->get($this->url('/'))->assertInertia(fn (Assert $page) => $page->has('assets.data', 0));
        $this->actingAs($keeper)->post($this->url("assets/{$asset->id}/dispose"), ['as' => 'disposed', 'on' => '2026-06-15'])->assertForbidden();
    }
}
