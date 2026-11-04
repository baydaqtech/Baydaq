<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Actions\Inventory\DefaultUnits;
use App\Modules\Assets\Actions\Inventory\ManageHandover;
use App\Modules\Assets\Actions\Inventory\ManageTransfer;
use App\Modules\Assets\Actions\Inventory\ReceiveStock;
use App\Modules\Assets\Actions\Inventory\ResolveIncident;
use App\Modules\Assets\Actions\Inventory\SaveItem;
use App\Modules\Assets\Actions\Inventory\StockLedger;
use App\Modules\Assets\Actions\SaveAsset;
use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\IncidentResolution;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Category;
use App\Modules\Assets\Models\Incident;
use App\Modules\Assets\Models\Item;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Unit;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Assets\Support\Holder;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private ItemVariant $cup;

    private ItemVariant $mango;

    private Warehouse $main;

    private Warehouse $event;

    private Employee $waiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-06-15 09:00:00');
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        app(DefaultUnits::class)->ensure();

        $cups = app(SaveItem::class)->handle(new Item, ['name_ar' => 'أكواب زجاج', 'name_en' => 'Glass cups', 'unit_id' => $this->unit('pc')->id], [
            ['name_ar' => 'كوب 250 مل', 'name_en' => 'Cup 250 ml', 'barcode' => '6281000000011', 'unit_cost' => '12'],
        ], [['name_ar' => 'كرتون', 'name_en' => 'Box', 'factor' => '24']]);
        $juice = app(SaveItem::class)->handle(new Item, ['name_ar' => 'عصير', 'name_en' => 'Juice', 'unit_id' => $this->unit('ml')->id], [
            ['name_ar' => 'مانجو', 'name_en' => 'Mango', 'content' => '330', 'content_unit_id' => $this->unit('ml')->id, 'unit_cost' => '0.02'],
            ['name_ar' => 'حليب', 'name_en' => 'Milk', 'content' => '400', 'content_unit_id' => $this->unit('ml')->id, 'unit_cost' => '0.015'],
        ]);
        $this->cup = $cups->variants->first();
        $this->mango = $juice->variants->firstWhere('name_en', 'Mango');

        $this->main = $this->warehouse('MAIN', $this->headOffice->id);
        $this->event = $this->warehouse('HALL', $this->branch('RUH')->id);
        $this->waiter = $this->employeeAt($this->branch('RUH'));
        $this->event->forceFill(['keeper_employee_id' => $this->employeeAt($this->branch('RUH'))->id])->save();
    }

    private function unit(string $symbol): Unit
    {
        return Unit::query()->where('symbol', $symbol)->firstOrFail();
    }

    private function warehouse(string $code, int $branchId): Warehouse
    {
        $warehouse = (new Warehouse(['name_ar' => $code, 'name_en' => $code]))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $branchId, 'code' => $code]);
        $warehouse->save();

        return $warehouse;
    }

    private function onHand(Holder $holder, ItemVariant $variant): string
    {
        return (string) (float) app(StockLedger::class)->onHand($holder, $variant->id);
    }

    public function test_stock_is_received_in_any_unit_of_the_same_kind_or_in_packs(): void
    {
        app(ReceiveStock::class)->handle($this->main, [
            ['variant_id' => $this->cup->id, 'quantity' => '2', 'unit' => 'pack:'.$this->cup->item->packs->first()->id],
            ['variant_id' => $this->mango->id, 'quantity' => '1.5', 'unit' => 'unit:'.$this->unit('L')->id, 'unit_cost' => '0.03'],
        ]);

        $this->assertSame('48', $this->onHand(Holder::warehouse($this->main->id), $this->cup));
        $this->assertSame('1500', $this->onHand(Holder::warehouse($this->main->id), $this->mango));
        $this->assertSame('0.0300', (string) $this->mango->fresh()->unit_cost);

        $this->expectException(ValidationException::class);
        app(ReceiveStock::class)->handle($this->main, [['variant_id' => $this->mango->id, 'quantity' => '1', 'unit' => 'unit:'.$this->unit('kg')->id]]);
    }

    public function test_a_transfer_travels_and_shortfalls_become_incidents(): void
    {
        app(ReceiveStock::class)->handle($this->main, [['variant_id' => $this->cup->id, 'quantity' => '48']]);
        $transfer = app(ManageTransfer::class)->create($this->main, $this->event, [['variant_id' => $this->cup->id, 'quantity' => '30']]);
        $this->assertSame('48', $this->onHand(Holder::warehouse($this->main->id), $this->cup)); // drafts move nothing

        app(ManageTransfer::class)->send($transfer);
        $this->assertSame('18', $this->onHand(Holder::warehouse($this->main->id), $this->cup));
        $this->assertSame('30', $this->onHand(Holder::of('transfer', $transfer->id), $this->cup));

        $line = $transfer->lines()->first();
        app(ManageTransfer::class)->receive($transfer, [$line->id => '28'], [$line->id => [['kind' => 'broken', 'quantity' => '1', 'description' => 'Box crushed']]]);

        $this->assertSame('28', $this->onHand(Holder::warehouse($this->event->id), $this->cup));
        $this->assertSame('0', $this->onHand(Holder::of('transfer', $transfer->id), $this->cup));
        $this->assertSame(['broken', 'missing'], Incident::query()->orderBy('id')->get()->map(fn ($incident) => $incident->kind->value)->all());
        $this->assertSame('24.00', (string) Incident::query()->sum('value')); // 2 × 12

        $again = app(ManageTransfer::class)->create($this->main, $this->event, [['variant_id' => $this->cup->id, 'quantity' => '10']]);
        app(ManageTransfer::class)->send($again);
        app(ManageTransfer::class)->cancel($again);
        $this->assertSame('18', $this->onHand(Holder::warehouse($this->main->id), $this->cup));
    }

    public function test_thirty_cups_go_out_four_come_back_and_the_rest_is_explained(): void
    {
        app(ReceiveStock::class)->handle($this->event, [['variant_id' => $this->cup->id, 'quantity' => '30']]);
        $handovers = app(ManageHandover::class);

        $out = $handovers->create(Holder::warehouse($this->event->id), Holder::employee($this->waiter->id), [['variant_id' => $this->cup->id, 'quantity' => '30']], details: ['purpose' => 'Wedding']);
        $this->assertSame('0', $this->onHand(Holder::warehouse($this->event->id), $this->cup));
        $handovers->accept($out);
        $this->assertSame('30', $this->onHand(Holder::employee($this->waiter->id), $this->cup));

        // The waiter returns 4 and says what happened to the other 26.
        $back = $handovers->create(Holder::employee($this->waiter->id), Holder::warehouse($this->event->id), [['variant_id' => $this->cup->id, 'quantity' => '4']], [
            ['variant_id' => $this->cup->id, 'kind' => 'broken', 'quantity' => '12', 'description' => 'Tray fell'],
            ['variant_id' => $this->cup->id, 'kind' => 'lost', 'quantity' => '8'],
            ['variant_id' => $this->cup->id, 'kind' => 'stolen', 'quantity' => '6'],
        ]);
        $this->assertSame('0', $this->onHand(Holder::employee($this->waiter->id), $this->cup));

        // The keeper counts only 3.
        $handovers->accept($back, [$back->lines->first()->id => '3']);
        $this->assertSame('3', $this->onHand(Holder::warehouse($this->event->id), $this->cup));

        $incidents = Incident::query()->orderBy('id')->get();
        $this->assertSame(['broken' => '12', 'lost' => '8', 'stolen' => '6', 'missing' => '1'], $incidents->mapWithKeys(fn ($incident) => [$incident->kind->value => (string) (float) $incident->quantity])->all());
        $this->assertTrue($incidents->every(fn ($incident) => $incident->responsible_employee_id === $this->waiter->id));
        $this->assertSame('324.00', (string) Incident::query()->sum('value')); // 27 cups × 12

        try {
            $handovers->create(Holder::employee($this->waiter->id), Holder::warehouse($this->event->id), [['variant_id' => $this->cup->id, 'quantity' => '1']]);
            $this->fail('Handed over stock the employee does not hold');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }
    }

    public function test_settling_incidents_charges_hr_or_puts_stock_back(): void
    {
        Subscription::factory()->module(Module::Hr)->create(['client_id' => $this->client->id, 'quantity' => 50]);
        app(ReceiveStock::class)->handle($this->event, [['variant_id' => $this->cup->id, 'quantity' => '10']]);
        $handovers = app(ManageHandover::class);
        $handovers->accept($handovers->create(Holder::warehouse($this->event->id), Holder::employee($this->waiter->id), [['variant_id' => $this->cup->id, 'quantity' => '10']]));
        $back = $handovers->create(Holder::employee($this->waiter->id), Holder::warehouse($this->event->id), [['variant_id' => $this->cup->id, 'quantity' => '5']], [
            ['variant_id' => $this->cup->id, 'kind' => 'broken', 'quantity' => '3'], ['variant_id' => $this->cup->id, 'kind' => 'lost', 'quantity' => '2'],
        ]);
        $handovers->accept($back);
        [$broken, $lost] = Incident::query()->orderBy('id')->get()->all();

        app(ResolveIncident::class)->handle($broken, IncidentResolution::Charged, 'Careless', null, null, $this->owner);
        $deduction = Deduction::query()->sole();
        $this->assertSame(['36.00', 'pending', 'assets:'.$broken->number], [(string) $deduction->amount, $deduction->status->value, $deduction->origin]);

        app(ResolveIncident::class)->handle($lost, IncidentResolution::Recovered, 'Found in the van', null, $this->event);
        $this->assertSame('7', $this->onHand(Holder::warehouse($this->event->id), $this->cup));

        $this->expectException(ValidationException::class);
        app(ResolveIncident::class)->handle($lost->fresh(), IncidentResolution::WrittenOff);
    }

    public function test_tagged_assets_change_custody_through_handovers(): void
    {
        $category = (new Category(['name_ar' => 'أجهزة', 'name_en' => 'Devices', 'useful_life_months' => 36]))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $tablet = app(SaveAsset::class)->handle(new Asset, ['category_id' => $category->id, 'name_ar' => 'جهاز لوحي', 'name_en' => 'Tablet', 'warehouse_id' => $this->event->id, 'cost' => '1200'], $this->branch('RUH')->id);
        $handovers = app(ManageHandover::class);

        $handovers->accept($handovers->create(Holder::warehouse($this->event->id), Holder::employee($this->waiter->id), [['asset_id' => $tablet->id]]));
        $this->assertSame([AssetStatus::Assigned, $this->waiter->id], [$tablet->fresh()->status, $tablet->fresh()->openCustody->employee_id]);

        $back = $handovers->create(Holder::employee($this->waiter->id), Holder::warehouse($this->event->id), [['asset_id' => $tablet->id, 'condition' => 'Screen cracked']]);
        $handovers->accept($back, [$back->lines->first()->id => '0']); // never arrived
        $this->assertSame(AssetStatus::Lost, $tablet->fresh()->status);
        $incident = Incident::query()->sole();
        $this->assertSame(['missing', $tablet->id, '1200.00'], [$incident->kind->value, $incident->asset_id, (string) $incident->value]);

        app(ResolveIncident::class)->handle($incident, IncidentResolution::WrittenOff);
        $this->assertNull($tablet->fresh()->openCustody); // custody closed once settled
        $this->assertSame(AssetStatus::Lost, $tablet->fresh()->status);
    }

    public function test_a_refused_handover_returns_everything_to_the_sender(): void
    {
        app(ReceiveStock::class)->handle($this->main, [['variant_id' => $this->mango->id, 'quantity' => '990']]);
        $handover = app(ManageHandover::class)->create(Holder::warehouse($this->main->id), Holder::employee($this->employeeAt($this->headOffice)->id), [['variant_id' => $this->mango->id, 'quantity' => '660']]);

        app(ManageHandover::class)->reject($handover, 'Wrong flavour');
        $this->assertSame('990', $this->onHand(Holder::warehouse($this->main->id), $this->mango));
        $this->assertSame('rejected', $handover->fresh()->status->value);
    }
}
