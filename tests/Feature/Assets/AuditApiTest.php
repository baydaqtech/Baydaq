<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Actions\ManageAudit;
use App\Modules\Assets\Actions\SaveAsset;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Category;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    public function test_a_counter_scans_qr_tags_into_an_open_stock_take_from_the_app(): void
    {
        $this->buildSuite();
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $this->client->id, 'quantity' => 1]);
        $category = (new Category(['name_ar' => 'أجهزة', 'name_en' => 'Devices', 'useful_life_months' => 36]))->forceFill(['client_id' => $this->client->id]);
        $category->save();
        $asset = app(SaveAsset::class)->handle(new Asset, ['category_id' => $category->id, 'name_ar' => 'طابعة', 'name_en' => 'Printer', 'cost' => '900'], $this->headOffice->id);
        $audit = app(ManageAudit::class)->start($this->headOffice->id);

        $counter = $this->memberWith(['assets.audits.create'], ScopeLevel::Branch, $this->headOffice);
        $outsider = $this->memberWith(['assets.audits.create'], ScopeLevel::Branch, $this->branch('JED'));

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/audits')->assertOk()->assertJsonCount(0, 'audits');
        $this->postJson("/api/v1/audits/{$audit->id}/scan", ['tag' => $asset->tag])->assertForbidden();

        Sanctum::actingAs($counter);
        $this->getJson('/api/v1/audits')->assertOk()->assertJsonPath('audits.0.expected', 1)->assertJsonPath('audits.0.found', 0);
        $this->postJson("/api/v1/audits/{$audit->id}/scan", ['tag' => strtolower($asset->tag)])->assertOk()->assertJsonPath('status', 'found');
        $this->getJson('/api/v1/audits')->assertJsonPath('audits.0.found', 1);
    }
}
