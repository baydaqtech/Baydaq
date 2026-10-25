<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Offboarding\SeparationFlow;
use App\Modules\Hr\Actions\Reviews\ReviewFlow;
use App\Modules\Hr\Actions\Violations\RecordViolation;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\ReviewController;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use App\Modules\Hr\Http\Controllers\SeparationController;
use App\Modules\Hr\Http\Controllers\ViolationController;
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
