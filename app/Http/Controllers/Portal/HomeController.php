<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\InvoiceResource;
use App\Http\Resources\Portal\ProjectResource;
use App\Models\Invoice;
use App\Models\Project;
use App\Modules\Platform\Models\Access\AccessAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The client portal home: the client's projects and their latest invoices.
     */
    public function __invoke(Request $request): Response
    {
        // A client account always belongs to a client; without one there is nothing to show.
        $clientId = $request->user()->client_id ?? 0;

        $invoices = Invoice::query()->where('client_id', $clientId)->issued();

        return Inertia::render('Portal/Home', [
            'projects' => ProjectResource::collection(
                Project::query()->where('client_id', $clientId)->withCount('projectServices')->latest()->get(),
            ),
            'invoices' => InvoiceResource::collection((clone $invoices)->with('project')->latest('issue_date')->limit(5)->get()),
            'billing' => [
                'due' => (string) (clone $invoices)->outstanding()->sum('total'),
                'overdue' => (string) (clone $invoices)->where('status', InvoiceStatus::Overdue)->sum('total'),
            ],
            // Owners (and anyone granted a suite role) get a door into the business suite.
            'suite_url' => AccessAssignment::forClient($clientId)->where('user_id', $request->user()->id)->exists() ? route('suite.entry') : null,
            'copy' => ['portal' => trans('portal')],
        ]);
    }
}
