<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Pay\AdvanceCalculator;
use App\Modules\Hr\Actions\Pay\RequestAdvance;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Salary advances in the employee app: the ceiling, a plan preview, and the request itself.
 */
class AdvanceController extends Controller
{
    use ResolvesEmployee;

    public function index(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::advances($this->employee($request)));
    }

    public function preview(Request $request, AdvanceCalculator $calculator): JsonResponse
    {
        $data = SelfServiceController::validateAdvance($request);

        return response()->json($calculator->preview($this->employee($request), (string) $data['amount'], (int) $data['installments'], $data['first_installment_month']));
    }

    public function store(Request $request, RequestAdvance $requestAdvance): JsonResponse
    {
        $advance = $requestAdvance->handle($this->employee($request), SelfServiceController::validateAdvance($request), $request->user());

        return response()->json(['id' => $advance->id, 'status' => $advance->status->value, 'schedule' => $advance->schedule->map(fn ($line): array => ['due_month' => $line->due_month->format('Y-m'), 'amount' => (string) $line->amount])->all()], 201);
    }
}
