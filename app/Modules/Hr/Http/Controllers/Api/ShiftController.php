<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Shifts\ClaimOpenShift;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use App\Modules\Hr\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shifts in the employee app: my coming shifts, open shifts I may pick up, and picking one up.
 */
class ShiftController extends Controller
{
    use ResolvesEmployee;

    public function index(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::shifts($this->employee($request)));
    }

    public function claim(Request $request, Shift $shift, ClaimOpenShift $claim): JsonResponse
    {
        $shift = $claim->handle($shift, $this->employee($request), $request->user());

        return response()->json(['id' => $shift->id, 'secondment' => $shift->movement_id !== null]);
    }
}
