<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The client portal home.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Portal/Home', [
            'copy' => ['portal' => trans('portal')],
        ]);
    }
}
