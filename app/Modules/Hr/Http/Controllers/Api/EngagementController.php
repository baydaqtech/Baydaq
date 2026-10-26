<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Engagement\AnnouncementFlow;
use App\Modules\Hr\Actions\Engagement\RecognitionFlow;
use App\Modules\Hr\Actions\Engagement\SurveyFlow;
use App\Modules\Hr\Adapters\HrCalendar;
use App\Modules\Hr\Http\Controllers\AnnouncementController;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\MessageController;
use App\Modules\Hr\Http\Controllers\MyEngagementController;
use App\Modules\Hr\Http\Controllers\SurveyController;
use App\Modules\Hr\Models\Announcement;
use App\Modules\Hr\Models\EmployeeMessage;
use App\Modules\Hr\Models\Survey;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Posts, surveys and recognition in the employee app.
 */
class EngagementController extends Controller
{
    use ResolvesEmployee;

    public function announcements(Request $request): JsonResponse
    {
        return response()->json(['announcements' => AnnouncementController::feed($this->employee($request))]);
    }

    public function read(Request $request, Announcement $announcement, AnnouncementFlow $flow): JsonResponse
    {
        $employee = $this->employee($request);
        $read = $flow->read(MyEngagementController::visibleAnnouncement($announcement, $employee), $employee, $request->boolean('acknowledge'));

        return response()->json(['read_at' => $read->read_at->toIso8601String(), 'acknowledged_at' => $read->acknowledged_at?->toIso8601String()]);
    }

    public function surveys(Request $request): JsonResponse
    {
        return response()->json(['surveys' => SurveyController::openFor($this->employee($request))]);
    }

    public function answer(Request $request, Survey $survey, SurveyFlow $flow): JsonResponse
    {
        $employee = $this->employee($request);
        $flow->answer(MyEngagementController::visibleSurvey($survey, $employee), $employee, MyEngagementController::validateAnswers($request));

        return response()->json(['answered' => true], 201);
    }

    public function messages(Request $request): JsonResponse
    {
        return response()->json(['messages' => MessageController::inbox($this->employee($request))]);
    }

    public function readMessage(Request $request, EmployeeMessage $message): JsonResponse
    {
        return response()->json(['read_at' => MyEngagementController::markRead($message, $this->employee($request))->read_at?->toIso8601String()]);
    }

    /** My dates between ?from and ?to (default: the next 30 days, at most 92 days). */
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = CarbonImmutable::parse($data['from'] ?? CarbonImmutable::today('Asia/Riyadh'));
        $to = CarbonImmutable::parse($data['to'] ?? $from->addDays(30));
        abort_if($from->diffInDays($to) > 92, 422, __('validation.max.numeric', ['attribute' => 'days', 'max' => 92]));

        return response()->json(['events' => array_map(fn ($event): array => (array) $event, HrCalendar::forEmployee($this->employee($request), $from, $to))]);
    }

    public function recognition(Request $request): JsonResponse
    {
        return response()->json(MyEngagementController::recognition($this->employee($request)));
    }

    public function recognise(Request $request, RecognitionFlow $flow): JsonResponse
    {
        $recognition = MyEngagementController::giveRecognition($request, $this->employee($request), $flow);

        return response()->json(['id' => $recognition->id], 201);
    }
}
