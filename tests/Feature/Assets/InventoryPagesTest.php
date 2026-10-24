<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Models\Handover;
use App\Modules\Assets\Models\Incident;
use App\Modules\Assets\Models\Item;
use App\Modules\Assets\Models\Transfer;
use App\Modules\Assets\Models\Unit;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class InventoryPagesTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-06-15 09:00:00');
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $this->client->id, 'quantity' => 1]);
    }

    private function url(string $path): string
    {
        return '/suite/all/assets/inventory/'.ltrim($path, '/');
    }

    public function test_the_owner_sets_up_items_and_warehouses_and_moves_stock(): void
    {
        $this->actingAs($this->owner)->get($this->url('items'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Assets/Inventory/Items')->has('units', 10));
        $pc = Unit::query()->where('symbol', 'pc')->value('id');

        $this->actingAs($this->owner)->post($this->url('items'), [
            'name_ar' => 'أكواب', 'name_en' => 'Cups', 'unit_id' => $pc, 'min_stock' => 20, 'is_active' => true,
            'variants' => [['name_ar' => 'كوب', 'name_en' => 'Cup', 'barcode' => '111', 'unit_cost' => '10', 'is_active' => true]],
            'packs' => [['name_ar' => 'كرتون', 'name_en' => 'Box', 'factor' => 24]],
        ])->assertSessionHasNoErrors();
        $variant = Item::query()->sole()->variants()->sole();

        foreach ([['MAIN', $this->headOffice->id], ['HALL', $this->branch('RUH')->id]] as [$code, $branch]) {
            $this->actingAs($this->owner)->post($this->url('warehouses'), ['branch_id' => $branch, 'code' => $code, 'name_ar' => $code, 'name_en' => $code, 'kind' => 'store', 'is_active' => true])->assertSessionHasNoErrors();
        }
        [$main, $hall] = Warehouse::query()->orderBy('id')->get()->all();

        $this->actingAs($this->owner)->post($this->url("warehouses/{$main->id}/receive"), ['lines' => [['variant_id' => $variant->id, 'quantity' => 2, 'unit' => 'pack:'.$variant->item->packs()->value('id')]]])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->get($this->url("warehouses/{$main->id}"))->assertInertia(fn (Assert $page) => $page->component('Assets/Inventory/Warehouse')->where('stock.0.quantity', '48')->where('stock.0.value', '480.00'));

        $this->actingAs($this->owner)->post($this->url('transfers'), ['from_warehouse_id' => $main->id, 'to_warehouse_id' => $hall->id, 'lines' => [['variant_id' => $variant->id, 'quantity' => 40]]])->assertRedirect();
        $transfer = Transfer::query()->sole();
        $this->actingAs($this->owner)->post($this->url("transfers/{$transfer->id}/send"), ['photos' => [UploadedFile::fake()->image('load.jpg')]])->assertSessionHasNoErrors();
        $line = $transfer->lines()->value('id');
        $this->actingAs($this->owner)->post($this->url("transfers/{$transfer->id}/receive"), ['counts' => [$line => 38], 'explanations' => [$line => [['kind' => 'broken', 'quantity' => 2, 'description' => 'Box crushed']]]])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get($this->url("transfers/{$transfer->id}"))->assertInertia(fn (Assert $page) => $page
            ->component('Assets/Inventory/Transfer')->where('transfer.status', 'received')->where('lines.0.received', '38')->has('incidents', 1)->has('photos', 1));
        $this->actingAs($this->owner)->get($this->url('warehouses'))->assertInertia(fn (Assert $page) => $page->component('Assets/Inventory/Warehouses')->has('warehouses', 2)->where('warehouses.1.code', 'MAIN')->where('warehouses.1.low', 1)); // 8 left < 20
        $this->actingAs($this->owner)->get($this->url('transfers'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('transfers.data', 1));
    }

    public function test_handovers_and_incidents_from_the_web(): void
    {
        $this->actingAs($this->owner)->get($this->url('items')); // gives the owner the default units
        $pc = Unit::query()->where('symbol', 'pc')->value('id');
        $this->actingAs($this->owner)->post($this->url('items'), ['name_ar' => 'صحون', 'name_en' => 'Plates', 'unit_id' => $pc, 'variants' => [['name_ar' => 'صحن', 'name_en' => 'Plate', 'unit_cost' => '15']]])->assertSessionHasNoErrors();
        $variant = Item::query()->sole()->variants()->sole();
        $waiter = $this->employeeAt($this->branch('RUH'));
        (new EmployeePlacement)->forceFill(['client_id' => $this->client->id, 'employee_id' => $waiter->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();
        $this->actingAs($this->owner)->post($this->url('warehouses'), ['branch_id' => $this->branch('RUH')->id, 'code' => 'HALL', 'name_ar' => 'القاعة', 'name_en' => 'Hall', 'kind' => 'event'])->assertSessionHasNoErrors();
        $hall = Warehouse::query()->sole();
        $this->actingAs($this->owner)->post($this->url("warehouses/{$hall->id}/receive"), ['lines' => [['variant_id' => $variant->id, 'quantity' => 30]]]);

        $this->actingAs($this->owner)->post($this->url('handovers'), ['from' => "warehouse:{$hall->id}", 'to' => "employee:{$waiter->id}", 'purpose' => 'Gala', 'lines' => [['variant_id' => $variant->id, 'quantity' => 30]], 'photos' => [UploadedFile::fake()->image('out.jpg')]])->assertRedirect();
        $out = Handover::query()->sole();
        $this->actingAs($this->owner)->get($this->url("handovers/{$out->id}"))->assertInertia(fn (Assert $page) => $page->component('Assets/Inventory/Handover')->where('can.accept', true)->has('photos', 1)); // owner may accept for others
        $this->actingAs($this->owner)->post($this->url("handovers/{$out->id}/accept"))->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post($this->url('handovers'), [
            'from' => "employee:{$waiter->id}", 'to' => "warehouse:{$hall->id}", 'lines' => [['variant_id' => $variant->id, 'quantity' => 4]],
            'write_offs' => [['variant_id' => $variant->id, 'kind' => 'broken', 'quantity' => 26, 'description' => 'Tables collapsed']],
        ])->assertRedirect();
        $this->actingAs($this->owner)->post($this->url('handovers/'.Handover::query()->latest('id')->value('id').'/accept'))->assertSessionHasNoErrors();

        $incident = Incident::query()->sole();
        $this->actingAs($this->owner)->get($this->url('incidents'))->assertInertia(fn (Assert $page) => $page
            ->component('Assets/Inventory/Incidents')->where('totals.value', '390.00')->where('incidents.data.0.responsible_employee_id', $waiter->id)->where('can.resolve', true));
        $this->actingAs($this->owner)->put($this->url("incidents/{$incident->id}/resolve"), ['resolution' => 'written_off', 'note' => 'Venue accident'])->assertSessionHasNoErrors();
        $this->assertSame('resolved', $incident->fresh()->status->value);
        $this->actingAs($this->owner)->get($this->url('handovers'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('handovers.data', 2));
    }

    public function test_inventory_respects_branches_and_abilities(): void
    {
        $this->actingAs($this->owner)->get($this->url('items'));
        $this->actingAs($this->owner)->post($this->url('warehouses'), ['branch_id' => $this->headOffice->id, 'code' => 'MAIN', 'name_ar' => 'رئيسي', 'name_en' => 'Main', 'kind' => 'main'])->assertSessionHasNoErrors();
        $main = Warehouse::query()->sole();
        $ruh = $this->memberWith(['assets.inventory.view', 'assets.inventory.create', 'assets.transfers.view'], ScopeLevel::Branch, $this->branch('RUH'));

        $this->actingAs($ruh)->get($this->url("warehouses/{$main->id}"))->assertForbidden();
        $this->actingAs($ruh)->post($this->url("warehouses/{$main->id}/receive"), ['lines' => [['variant_id' => 1, 'quantity' => 1]]])->assertForbidden();
        $this->actingAs($ruh)->post($this->url('items'), ['name_ar' => 'س', 'name_en' => 'S', 'unit_id' => Unit::query()->value('id'), 'variants' => [['name_ar' => 'س', 'name_en' => 'S']]])->assertForbidden(); // items are owner-wide
        $this->actingAs($ruh)->get($this->url('incidents'))->assertForbidden();
    }
}
