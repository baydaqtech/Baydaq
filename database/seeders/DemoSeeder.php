<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectResource;
use App\Models\ProjectService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Sample clients, projects and invoices for local development only.
     * Sign in as client@baydaq.test / password to see the portal.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'client@baydaq.test')->exists()) {
            return;
        }

        $client = Client::factory()->create(['name_ar' => 'شركة المثال', 'name_en' => 'Example Co.']);
        User::factory()->client($client)->create(['name' => 'Demo Client', 'email' => 'client@baydaq.test']);

        $services = Service::query()->listed()->get();

        $projects = [
            Project::factory()->partnership()->for($client)->create(['name_ar' => 'تطبيق التوصيل', 'name_en' => 'Delivery App', 'slug' => 'delivery-app', 'progress' => 60]),
            Project::factory()->product()->live()->for($client)->create(['name_ar' => 'نظام الحجوزات', 'name_en' => 'Bookings', 'slug' => 'bookings']),
            Project::factory()->clientOwned()->live()->for($client)->create(['name_ar' => 'متجر المثال', 'name_en' => 'Example Store', 'slug' => 'example-store']),
        ];

        foreach ($projects as $project) {
            Milestone::factory()->done()->for($project)->create(['title' => 'Discovery', 'sort_order' => 1]);
            Milestone::factory()->for($project)->create(['title' => 'First release', 'sort_order' => 2]);
            ProjectResource::factory()->count(2)->for($project)->create();
            ProjectFile::factory()->count(3)->for($project)->create();

            foreach ($services->random(min(2, $services->count())) as $service) {
                ProjectService::factory()->for($project)->for($service)->create(['pricing_model' => $service->pricing_model]);
            }
        }

        foreach ([InvoiceStatus::Paid, InvoiceStatus::Sent, InvoiceStatus::Overdue] as $status) {
            $invoice = Invoice::factory()->{$status->value}()->for($client)->for($projects[0])->create();
            InvoiceItem::factory()->for($invoice)->create([
                'description' => 'Development work',
                'quantity' => 1,
                'unit_price' => $invoice->subtotal,
                'total' => $invoice->subtotal,
            ]);
        }

        ClientRequest::factory()->count(2)->create();
        ClientRequest::factory()->forServices()->create();
    }
}
