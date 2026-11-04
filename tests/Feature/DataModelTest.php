<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProjectService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_api_keys_are_encrypted_and_never_serialized(): void
    {
        $projectService = ProjectService::factory()->create();
        $projectService->forceFill(['api_key' => 'bdq_live_secret'])->save();

        $stored = DB::table('project_services')->where('id', $projectService->id)->value('api_key');

        $this->assertNotSame('bdq_live_secret', $stored);
        $this->assertSame('bdq_live_secret', $projectService->fresh()->api_key);
        $this->assertArrayNotHasKey('api_key', $projectService->fresh()->toArray());
    }

    public function test_bilingual_fields_follow_the_language_and_fall_back(): void
    {
        $product = Product::factory()->create(['name_ar' => 'نزل', 'name_en' => 'Nuzol', 'description_en' => null]);

        app()->setLocale('ar');
        $this->assertSame('نزل', $product->localized('name'));

        app()->setLocale('en');
        $this->assertSame('Nuzol', $product->localized('name'));
        $this->assertSame($product->description_ar, $product->localized('description'));
    }

    public function test_client_accounts_belong_to_their_company(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->client($client)->create();

        $this->assertTrue($user->client->is($client));
        $this->assertTrue($client->users->contains($user));
    }

    public function test_only_sent_and_overdue_invoices_are_outstanding(): void
    {
        Invoice::factory()->create();
        $sent = Invoice::factory()->sent()->create();
        $overdue = Invoice::factory()->overdue()->create();
        Invoice::factory()->paid()->create();

        $this->assertEqualsCanonicalizing([$sent->id, $overdue->id], Invoice::outstanding()->pluck('id')->all());
        $this->assertSame(InvoiceStatus::Overdue, $overdue->fresh()->status);
    }

    public function test_settings_return_null_for_missing_keys(): void
    {
        Setting::store(['company_name_en' => 'Baydaq Tech']);

        $this->assertSame(
            ['company_name_en' => 'Baydaq Tech', 'vat_number' => null],
            Setting::valuesFor(['company_name_en', 'vat_number']),
        );
    }
}
