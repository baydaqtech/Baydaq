<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Expenses\ExpenseFlow;
use App\Modules\Hr\Actions\Offboarding\SeparationFlow;
use App\Modules\Hr\Actions\Overtime\OvertimeFlow;
use App\Modules\Hr\Actions\Remote\RemoteWork;
use App\Modules\Hr\Actions\Reviews\ReviewFlow;
use App\Modules\Hr\Actions\Violations\RecordViolation;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\ExpenseController;
use App\Modules\Hr\Http\Controllers\OvertimeController;
use App\Modules\Hr\Http\Controllers\RemoteController;
use App\Modules\Hr\Http\Controllers\ReviewController;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use App\Modules\Hr\Http\Controllers\SeparationController;
use App\Modules\Hr\Http\Controllers\ViolationController;
use App\Modules\Hr\Models\Overtime;
use App\Modules\Hr\Models\Review;
use App\Modules\Hr\Models\Separation;
use App\Modules\Hr\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * My record in the employee app: resign (and withdraw within the window) and answer violations.
 */
class RecordController extends Controller
{
    use ResolvesEmployee;

    public function show(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::record($this->employee($request)));
    }

    public function expenses(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::expenses($this->employee($request)));
    }

    public function claimExpense(Request $request, ExpenseFlow $flow): JsonResponse
    {
        $claim = $flow->submit($this->employee($request), ExpenseController::validateClaim($request), $request->file('receipt'));

        return response()->json(ExpenseController::claimArray($claim), 201);
    }

    public function overtime(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::overtime($this->employee($request)));
    }

    public function requestOvertime(Request $request, OvertimeFlow $flow): JsonResponse
    {
        $overtime = $flow->request($this->employee($request), OvertimeController::validateOvertime($request), 'employee', $request->user());

        return response()->json(OvertimeController::overtimeArray($overtime), 201);
    }

    public function cancelOvertime(Request $request, Overtime $overtime, OvertimeFlow $flow): JsonResponse
    {
        abort_unless($overtime->employee_id === $this->employee($request)->id, 404);

        return response()->json(OvertimeController::overtimeArray($flow->cancel($overtime)));
    }

    public function insurance(Request $request): JsonResponse
    {
        return response()->json(['members' => SelfServiceController::insurance($this->employee($request))]);
    }

    public function trainings(Request $request): JsonResponse
    {
        return response()->json(['trainings' => SelfServiceController::trainings($this->employee($request))]);
    }

    public function remote(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::remote($this->employee($request)));
    }

    public function requestRemote(Request $request, RemoteWork $remote): JsonResponse
    {
        $data = SelfServiceController::validateRemoteRequest($request);

        return response()->json(RemoteController::requestArray($remote->request($this->employee($request), $data['work_date'], $data['reason'] ?? null)), 201);
    }

    public function reviews(Request $request): JsonResponse
    {
        return response()->json(SelfServiceController::reviews($this->employee($request)));
    }

    public function selfReview(Request $request, Review $review, ReviewFlow $flow): JsonResponse
    {
        abort_unless($review->employee_id === $this->employee($request)->id, 404);
        $data = $request->validate(['scores' => ['required', 'array'], 'comment' => ['nullable', 'string', 'max:3000']]);

        return response()->json(ReviewController::reviewArray($flow->selfAssess($review, $data['scores'], $data['comment'] ?? null)));
    }

    public function resign(Request $request, SeparationFlow $flow): JsonResponse
    {
        $separation = $flow->submit($this->employee($request), [...SelfServiceController::validateResignation($request), 'kind' => 'resignation'], 'employee', $request->user());

        return response()->json(SeparationController::separationArray($separation), 201);
    }

    public function withdraw(Request $request, Separation $separation, SeparationFlow $flow): JsonResponse
    {
        abort_unless($separation->employee_id === $this->employee($request)->id, 404);

        return response()->json(SeparationController::separationArray($flow->withdraw($separation, $request->user())));
    }

    public function respond(Request $request, Violation $violation, RecordViolation $record): JsonResponse
    {
        abort_unless($violation->employee_id === $this->employee($request)->id, 404);
        $response = $request->validate(['response' => ['required', 'string', 'max:2000']])['response'];

        return response()->json(ViolationController::violationArray($record->respond($violation, $response, $request->user())));
    }
}
