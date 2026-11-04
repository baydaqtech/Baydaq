<?php

namespace Tests\Feature\Suite;

use App\Http\Controllers\NotificationController;
use App\Modules\Finance\Models\Bill;
use App\Modules\Finance\Models\Supplier;
use App\Modules\Hr\Enums\LeaveStatus;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Notifications\SuiteAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Morning alerts (approvals waiting, bills due, the VAT return) in the notification centre, read
 * there and by email unless the user switched that kind off.
 */
class NotificationCentreTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->buildSuite();
        $this->travelTo('2026-11-02 05:00:00');
        $this->asTenant();
    }

    private function pendingLeave(): void
    {
        $type = (new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21']))->forceFill(['client_id' => $this->client->id]);
        $type->save();
        (new LeaveRequest)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $this->employeeAt()->id, 'leave_type_id' => $type->id,
            'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12', 'days' => '3', 'status' => LeaveStatus::Pending->value,
        ])->save();
    }

    public function test_approvers_hear_what_waits_on_them_and_read_it_in_the_centre(): void
    {
        $this->pendingLeave();

        $this->artisan('suite:notify-approvals')->assertSuccessful();

        $this->actingAs($this->owner)->get($this->suiteUrl('approvals'))->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1)->missing('notification_feed'));

        app()->setLocale('en');
        $feed = NotificationController::feed($this->owner);
        $this->assertSame('Waiting on you', $feed[0]['title']);
        $this->assertSame('Requests waiting for your decision: 1.', $feed[0]['body']);
        $this->assertSame('/approvals', $feed[0]['url']);

        $this->actingAs($this->owner)->post('/notifications/read')->assertRedirect();
        $this->assertSame(0, $this->owner->unreadNotifications()->count());
    }

    public function test_email_can_be_switched_off_per_kind(): void
    {
        $this->actingAs($this->owner)->get('/account/notifications')->assertInertia(fn (Assert $page) => $page->component('Auth/NotificationSettings')->where('kinds.0.mail', true));
        $this->actingAs($this->owner)->put('/account/notifications', ['mail' => ['suite.approvals_waiting' => false, 'finance.bills_due' => true]])->assertSessionHasNoErrors();

        $this->assertSame(['database'], $this->owner->fresh()->notificationChannels('suite.approvals_waiting'));
        $this->assertSame(['database', 'mail'], $this->owner->fresh()->notificationChannels('finance.bills_due'));
    }

    public function test_bill_payers_hear_about_bills_due_and_everyone_about_the_vat_return(): void
    {
        Notification::fake();
        $supplier = (new Supplier(['name' => 'Al Marai', 'payment_days' => 30]))->forceFill(['client_id' => $this->client->id]);
        $supplier->save();
        (new Bill)->forceFill([
            'client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->headOffice->id, 'supplier_id' => $supplier->id,
            'bill_date' => '2026-10-01', 'due_on' => '2026-11-03', 'status' => 'approved', 'subtotal' => '1000', 'vat' => '150', 'total' => '1150', 'paid' => '150',
        ])->save();

        $this->artisan('finance:notify-bills-due')->assertSuccessful();
        Notification::assertSentTo($this->owner, SuiteAlert::class, fn (SuiteAlert $alert) => $alert->kind === 'finance.bills_due' && $alert->params['total'] === '1000.00' && $alert->params['count'] === 1);

        $this->travelTo('2026-10-24 06:00:00'); // a week before the VAT return for July–September is due
        $this->artisan('suite:notify-vat')->assertSuccessful();
        Notification::assertSentTo($this->owner, SuiteAlert::class, fn (SuiteAlert $alert) => $alert->kind === 'gov.vat_due' && $alert->params['days'] === 7);
    }
}
