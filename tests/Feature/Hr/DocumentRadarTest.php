<?php

namespace Tests\Feature\Hr;

use App\Modules\Hr\Actions\Documents\SaveEmployeeDocument;
use App\Modules\Hr\Models\DocumentRenewal;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class DocumentRadarTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private DocumentType $iqama;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSuite();
        $this->travelTo('2026-11-01');
        $this->iqama = DocumentType::factory()->create(['client_id' => $this->client->id, 'has_expiry' => true, 'alert_days' => [60, 30], 'renewal_cost' => '650']);
    }

    private function document(string $expiresOn, ?string $branch = null): EmployeeDocument
    {
        $employee = $this->employeeAt($branch ? $this->branch($branch) : null);

        return app(SaveEmployeeDocument::class)->handle($employee, $this->iqama, new EmployeeDocument, ['number' => '2'.random_int(100000000, 999999999), 'expires_on' => $expiresOn]);
    }

    public function test_the_radar_buckets_documents_and_adds_up_renewal_costs(): void
    {
        $this->document('2026-10-20');            // expired
        $this->document('2026-11-20');            // within 30
        $this->document('2026-12-20', 'JED');     // 31–60
        $this->document('2027-01-20');            // 61–90
        $this->document('2027-06-01');            // beyond the radar

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/documents/radar'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hr/Documents/Radar')
                ->has('rows', 4)
                ->where('buckets.expired.count', 1)
                ->where('buckets.30.cost', '650.00')
                ->where('buckets.60.count', 1)
                ->where('buckets.90.count', 1));

        $jeddah = $this->memberWith(['hr.documents.view'], ScopeLevel::Branch, $this->branch('JED'));
        $this->actingAs($jeddah)->get($this->suiteUrl('hr/documents/radar'))
            ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.bucket', '60'));
    }

    public function test_the_first_alert_opens_a_renewal_task_and_a_new_expiry_closes_it(): void
    {
        $document = $this->document('2026-11-25');

        $this->artisan('hr:notify-expiring-documents')->assertSuccessful();
        $renewal = DocumentRenewal::query()->sole();
        $this->assertSame('open', $renewal->status);
        $this->assertSame('650.00', (string) $renewal->cost);

        // Running again does not duplicate the task.
        $this->travelTo('2026-11-02');
        $this->artisan('hr:notify-expiring-documents')->assertSuccessful();
        $this->assertSame(1, DocumentRenewal::query()->count());

        app(SaveEmployeeDocument::class)->handle($document->employee, $this->iqama, $document->fresh(), ['expires_on' => '2027-11-25'], [], $this->owner);
        $this->assertSame('done', $renewal->fresh()->status);
    }

    public function test_a_task_can_be_closed_by_hand(): void
    {
        $this->document('2026-11-25');
        $this->artisan('hr:notify-expiring-documents');
        $renewal = DocumentRenewal::query()->sole();

        $this->actingAs($this->owner)->put($this->suiteUrl("hr/renewals/{$renewal->id}"), ['status' => 'cancelled'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $renewal->fresh()->status);
    }
}
