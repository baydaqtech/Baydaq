<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Invoice;
use App\Models\Project;
use Inertia\Response;

class DashboardController extends Controller
{
    use RendersAdminPages;

    /**
     * The admin home: headline numbers and the newest requests.
     */
    public function __invoke(): Response
    {
        $user = request()->user();

        return $this->adminPage('Admin/Dashboard', [
            'stats' => [
                'clients' => Client::count(),
                'projects' => Project::count(),
                'total_due' => (string) Invoice::query()->outstanding()->sum('total'),
                'overdue' => (string) Invoice::query()->where('status', InvoiceStatus::Overdue)->sum('total'),
                'paid_this_month' => (string) Invoice::query()
                    ->where('status', InvoiceStatus::Paid)
                    ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('total'),
                'new_requests' => ClientRequest::query()->where('status', RequestStatus::New)->count(),
            ],
            'recentRequests' => $user->can('requests.manage')
                ? ClientRequest::query()->where('status', RequestStatus::New)->latest()->limit(5)
                    ->get(['id', 'type', 'name', 'company', 'created_at'])
                : [],
        ]);
    }
}
