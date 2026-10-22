<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The admin home.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'copy' => ['admin' => trans('admin')],
        ]);
    }
}
