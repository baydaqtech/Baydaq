<?php

namespace Tests\Feature\Pos;

use App\Modules\Hr\Actions\Attendance\RecordPunch;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Models\Bonus;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Deduction;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Actions\Suite\SaveBrand;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Brand;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Models\WorkLocation;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\PayOrder;
use App\Modules\Pos\Actions\PlaceOrder;
use App\Modules\Pos\Actions\Reports\MenuEngineering;
use App\Modules\Pos\Actions\SaveCashier;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\Order;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class SuiteLinksTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $employee;

    private Register $register;

    private Cashier $cashier;

    private Product $burger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01 09:00:00');
        Subscription::factory()->module(Module::Pos)->create(['client_id' => $this->client->id, 'quantity' => 1]);

        $this->employee = $this->employeeAt(attributes: ['pin_hash' => Hash::make('4321')]);
        app(SaveContract::class)->handle($this->employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => '2026-01-01', 'basic_salary' => '6000'], []);
        $this->register = (new Register(['name' => 'Till 1']))->forceFill(['client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'menu_token' => Str::random(32)]);
        $this->register->save();
        $this->cashier = app(SaveCashier::class)->handle(new Cashier, ['company_id' => $this->company->id, 'employee_id' => $this->employee->id, 'name' => 'Sara', 'commission_rate' => '2'], '1111');
        $this->burger = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'name_ar' => 'برجر', 'name_en' => 'Burger', 'price' => '23.00', 'cost' => '8.00', 'calories' => 600], []);

        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $this->client->id]), ['name_ar' => 'المقر', 'name_en' => 'HQ', 'latitude' => 24.7, 'longitude' => 46.7, 'radius_m' => 100], [$this->headOffice->id]);
    }

    private function checkIn(): void
    {
        app(RecordPunch::class)->handle($this->employee, PunchType::In, ['latitude' => 24.7, 'longitude' => 46.7, 'accuracy_m' => 10, 'mocked' => false, 'device_id' => 'p']);
    }

    public function test_an_employee_cashier_opens_the_drawer_with_their_hr_pin_only_once_checked_in(): void
    {
        try {
            app(ManageShift::class)->open($this->register, $this->cashier, '4321', '200');
            $this->fail('Opened without checking in');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(__('pos.errors.not_checked_in'), implode(' ', $e->errors()['pin']));
        }

        $this->checkIn();

        try {
            app(ManageShift::class)->open($this->register, $this->cashier, '1111', '200'); // the till PIN no longer counts
            $this->fail('Opened with the cashier PIN');
        } catch (ValidationException) {
        }

        $this->assertNotNull(app(ManageShift::class)->open($this->register, $this->cashier, '4321', '200')->id);
    }

    public function test_closing_short_sends_a_deduction_and_the_commission_to_payroll(): void
    {
        $this->checkIn();
        $shift = app(ManageShift::class)->open($this->register, $this->cashier, '4321', '200');
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, $shift, [], [['product_id' => $this->burger->id, 'quantity' => 10]]);
        app(PayOrder::class)->handle($order, [['method' => 'cash', 'amount' => '230.00']]);

        $shift = app(ManageShift::class)->close($shift->fresh(), '400.00'); // expected 430 → 30 short

        $this->assertTrue($shift->shortage_sent);
        $deduction = Deduction::query()->sole();
        $this->assertSame('30.00', (string) $deduction->amount);
        $this->assertSame("pos:shift:{$shift->id}", $deduction->origin);
        $this->assertSame('4.60', (string) Bonus::query()->findOrFail($shift->commission_bonus_id)->amount); // 2% of 230
    }

    public function test_a_staff_meal_is_charged_to_the_employees_pay(): void
    {
        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, [], [['product_id' => $this->burger->id, 'quantity' => 1]]);

        try {
            app(PayOrder::class)->handle($order, [['method' => 'staff_account', 'amount' => '23.00']]);
            $this->fail('Staff meal without an employee');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('staff_employee_id', $e->errors());
        }

        app(PayOrder::class)->handle($order->fresh(), [['method' => 'staff_account', 'amount' => '23.00']], null, $this->employee->id);

        $this->assertSame($this->employee->id, $order->fresh()->staff_employee_id);
        $this->assertSame('23.00', (string) Deduction::query()->sole()->amount);
    }

    public function test_orders_of_one_brand_are_tagged_for_the_kitchen(): void
    {
        $brand = app(SaveBrand::class)->handle(new Brand, ['company_id' => $this->company->id, 'name_ar' => 'برجر هاوس', 'name_en' => 'Burger House'], [$this->headOffice->id]);
        $category = (new Category(['company_id' => $this->company->id, 'brand_id' => $brand->id, 'name_ar' => 'برجر', 'name_en' => 'Burgers']))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $this->burger->forceFill(['category_id' => $category->id])->save();

        $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, [], [['product_id' => $this->burger->id, 'quantity' => 1]]);
        $this->assertSame($brand->id, $order->brand_id);

        $this->actingAs($this->owner)->get($this->suiteUrl("pos/kitchen?brand={$brand->id}"))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Pos/Kitchen')->has('orders', 1)->where('orders.0.brand', 'برجر هاوس'));
    }

    public function test_menu_engineering_sorts_products_into_stars_plowhorses_puzzles_and_dogs(): void
    {
        $fries = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'name_ar' => 'بطاطس', 'name_en' => 'Fries', 'price' => '11.50', 'cost' => '9.00'], []);
        $steak = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'name_ar' => 'ستيك', 'name_en' => 'Steak', 'price' => '115.00', 'cost' => '30.00'], []);
        $salad = app(SaveProduct::class)->handle(new Product, ['company_id' => $this->company->id, 'name_ar' => 'سلطة', 'name_en' => 'Salad', 'price' => '11.50', 'cost' => '9.50'], []);

        foreach ([[$this->burger, 20], [$fries, 20], [$steak, 2], [$salad, 1]] as [$product, $quantity]) {
            $order = app(PlaceOrder::class)->handle(new Order, $this->headOffice->id, $this->register, null, [], [['product_id' => $product->id, 'quantity' => $quantity]]);
            app(PayOrder::class)->handle($order, [['method' => 'card', 'amount' => (string) $order->total]]);
        }

        $classes = collect(app(MenuEngineering::class)->handle([$this->headOffice->id], today(), today())['items'])->pluck('class', 'name');

        $this->assertSame('star', $classes['برجر']);      // 20 sold, 12.00 margin
        $this->assertSame('plowhorse', $classes['بطاطس']); // 20 sold, 1.00 margin
        $this->assertSame('puzzle', $classes['ستيك']);     // 2 sold, 70.00 margin
        $this->assertSame('dog', $classes['سلطة']);
        $this->assertSame(150, $this->burger->burnMinutes());
    }
}
