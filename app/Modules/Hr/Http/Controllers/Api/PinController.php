<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The employee sets their till PIN from the app. */
class PinController extends Controller
{
    use ResolvesEmployee;

    public function __invoke(Request $request): Response
    {
        SelfServiceController::savePin($this->employee($request), $request);

        return response()->noContent();
    }
}
