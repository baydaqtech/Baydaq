<?php

namespace Tests\Feature\Assets;

use App\Models\User;
use App\Modules\Assets\Actions\Inventory\DefaultUnits;
use App\Modules\Assets\Actions\Inventory\ReceiveStock;
use App\Modules\Assets\Actions\Inventory\SaveItem;
use App\Modules\Assets\Models\Attachment;
use App\Modules\Assets\Models\Handover;
use App\Modules\Assets\Models\Incident;
use App\Modules\Assets\Models\Item;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\Unit;
use App\Modules\Assets\Models\Warehouse;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeePlacement;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private ItemVariant $cup;

    private Warehouse $hall;

    private Employee $keeper;

    private Employee $waiter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('assets');
        $this->buildSuite();
        $this->travelTo('2026-06-15 09:00:00');
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        app(DefaultUnits::class)->ensure();

        $this->cup = app(SaveItem::class)->handle(new Item, ['name_ar' => 'أكواب', 'name_en' => 'Cups', 'unit_id' => Unit::query()->where('symbol', 'pc')->value('id')], [
            ['name_ar' => 'كوب', 'name_en' => 'Cup', 'barcode' => '6281000000011', 'unit_cost' => '10'],
        ])->variants->first();
        $this->keeper = $this->placed('keeper@example.test');
        $this->waiter = $this->placed('waiter@example.test');
        $this->hall = (new Warehouse(['name_ar' => 'القاعة', 'name_en' => 'Hall', 'keeper_employee_id' => $this->keeper->id]))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'code' => 'HALL']);
        $this->hall->save();
        app(ReceiveStock::class)->handle($this->hall, [['variant_id' => $this->cup->id, 'quantity' => '30']]);
        app(CurrentTenant::class)->clear();
    }

    private function placed(string $email): Employee
    {
        $employee = $this->employeeAt($this->branch('RUH'));
        (new EmployeePlacement)->forceFill(['client_id' => $this->client->id, 'employee_id' => $employee->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'kind' => 'home', 'starts_on' => '2026-01-01'])->save();
        app(LinkEmployeeUser::class)->handle($employee, $email);
        User::query()->where('email', $email)->update(['password' => bcrypt('secret-123'), 'must_change_password' => false]);

        return $employee;
    }

    /** Act as one phone; the guard forgets the previous user between requests in a test. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(string $email, string $device): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'secret-123', 'device_id' => $device, 'platform' => 'android'])->assertOk()->json('token');
    }

    public function test_a_keeper_hands_out_cups_and_the_waiter_returns_some_with_photos_and_explanations(): void
    {
        $keeper = $this->token('keeper@example.test', 'keeper-phone');
        $waiter = $this->token('waiter@example.test', 'waiter-phone');

        $this->as($keeper)->getJson('/api/v1/inventory/lookup?code=6281000000011')->assertOk()->assertJsonPath('type', 'variant')->assertJsonPath('id', $this->cup->id);
        $this->as($keeper)->getJson('/api/v1/inventory/recipients')->assertOk()->assertJsonFragment(['holder' => 'employee:'.$this->waiter->id]);

        $id = $this->as($keeper)->post('/api/v1/inventory/handovers', [
            'from' => 'warehouse:'.$this->hall->id, 'to' => 'employee:'.$this->waiter->id, 'purpose' => 'Wedding', 'latitude' => 24.69, 'longitude' => 46.68,
            'lines' => [['variant_id' => $this->cup->id, 'quantity' => 30]], 'photos' => [UploadedFile::fake()->image('cups.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $this->as($waiter)->getJson('/api/v1/inventory/handovers')->assertOk()->assertJsonPath('handovers.0.to_me', true);
        $this->as($keeper)->postJson("/api/v1/inventory/handovers/{$id}/accept")->assertForbidden(); // only the receiver
        $this->as($waiter)->post("/api/v1/inventory/handovers/{$id}/accept", ['photos' => [UploadedFile::fake()->image('received.jpg')]], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'accepted');
        $this->as($waiter)->getJson('/api/v1/inventory/holdings')->assertOk()->assertJsonPath('stock.0.quantity', '30');

        // Back with 4; the other 26 explained, plus a photo of the broken ones.
        $back = $this->as($waiter)->post('/api/v1/inventory/handovers', [
            'to' => 'warehouse:'.$this->hall->id, 'lines' => [['variant_id' => $this->cup->id, 'quantity' => 4]],
            'write_offs' => [['variant_id' => $this->cup->id, 'kind' => 'broken', 'quantity' => 20], ['variant_id' => $this->cup->id, 'kind' => 'stolen', 'quantity' => 6]],
            'photos' => [UploadedFile::fake()->image('broken.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $this->as($keeper)->getJson("/api/v1/inventory/handovers/{$back}")->assertOk()->assertJsonPath('handover.to_me', true)->assertJsonCount(1, 'photos');
        $this->as($keeper)->postJson("/api/v1/inventory/handovers/{$back}/accept", ['counts' => [Handover::withoutGlobalScopes()->find($back)->lines()->value('id') => 4]])->assertOk()->assertJsonPath('incidents', 2);

        $this->assertSame(3, Attachment::withoutGlobalScopes()->count());
        Storage::disk('assets')->assertExists(Attachment::withoutGlobalScopes()->value('path'));
        $this->assertSame(['broken', 'stolen'], Incident::withoutGlobalScopes()->orderBy('id')->get()->map(fn ($incident) => $incident->kind->value)->all());
    }

    public function test_an_employee_reports_a_breakage_in_their_hands(): void
    {
        $keeper = $this->token('keeper@example.test', 'keeper-phone');
        $waiter = $this->token('waiter@example.test', 'waiter-phone');
        $id = $this->as($keeper)->postJson('/api/v1/inventory/handovers', ['from' => 'warehouse:'.$this->hall->id, 'to' => 'employee:'.$this->waiter->id, 'lines' => [['variant_id' => $this->cup->id, 'quantity' => 5]]])->json('id');
        $this->as($waiter)->postJson("/api/v1/inventory/handovers/{$id}/accept")->assertOk();

        $this->as($waiter)->post('/api/v1/inventory/incidents', ['variant_id' => $this->cup->id, 'quantity' => 2, 'kind' => 'broken', 'description' => 'Dropped', 'photos' => [UploadedFile::fake()->image('a.jpg')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('value', '20.00');
        $this->as($waiter)->getJson('/api/v1/inventory/holdings')->assertJsonPath('stock.0.quantity', '3');
        $this->as($waiter)->postJson('/api/v1/inventory/incidents', ['variant_id' => $this->cup->id, 'quantity' => 9, 'kind' => 'lost'])->assertUnprocessable(); // holds only 3
        $this->as($waiter)->postJson('/api/v1/inventory/handovers', ['from' => 'warehouse:'.$this->hall->id, 'to' => 'employee:'.$this->keeper->id, 'lines' => [['variant_id' => $this->cup->id, 'quantity' => 1]]])->assertForbidden(); // not the keeper
    }
}
