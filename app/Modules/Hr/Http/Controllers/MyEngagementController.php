<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Engagement\AnnouncementFlow;
use App\Modules\Hr\Actions\Engagement\RecognitionFlow;
use App\Modules\Hr\Actions\Engagement\SurveyFlow;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Models\Announcement;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMessage;
use App\Modules\Hr\Models\Recognition;
use App\Modules\Hr\Models\Survey;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The employee's side of engagement on /me: reading and acknowledging posts, answering surveys,
 * and thanking colleagues. The app API reuses the static helpers.
 */
class MyEngagementController extends Controller
{
    use RendersSuitePages, ResolvesEmployee;

    public function read(Request $request, Announcement $announcement, AnnouncementFlow $flow): RedirectResponse
    {
        $flow->read(self::visibleAnnouncement($announcement, $employee = $this->employee($request)), $employee, $request->boolean('acknowledge'));

        return back();
    }

    public function file(Request $request, Announcement $announcement): StreamedResponse
    {
        self::visibleAnnouncement($announcement, $this->employee($request));
        abort_if($announcement->file_path === null, 404);

        return Storage::disk(config('baydaq.hr.disk'))->download($announcement->file_path, $announcement->file_name);
    }

    public function answer(Request $request, Survey $survey, SurveyFlow $flow): RedirectResponse
    {
        $employee = $this->employee($request);
        $flow->answer(self::visibleSurvey($survey, $employee), $employee, self::validateAnswers($request));
        $this->toast(__('hr.surveys.thanks'));

        return back();
    }

    public function recognise(Request $request, RecognitionFlow $flow): RedirectResponse
    {
        self::giveRecognition($request, $this->employee($request), $flow);
        $this->toast(__('hr.recognition.sent'));

        return back();
    }

    public function readMessage(Request $request, EmployeeMessage $message): RedirectResponse
    {
        self::markRead($message, $this->employee($request));

        return back();
    }

    public static function markRead(EmployeeMessage $message, Employee $employee): EmployeeMessage
    {
        abort_unless($message->employee_id === $employee->id, 404);

        if ($message->read_at === null) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return $message;
    }

    public static function visibleAnnouncement(Announcement $announcement, Employee $employee): Announcement
    {
        abort_unless(Announcement::query()->whereKey($announcement->id)->forEmployee($employee)->exists(), 404);

        return $announcement;
    }

    public static function visibleSurvey(Survey $survey, Employee $employee): Survey
    {
        abort_unless(Survey::query()->whereKey($survey->id)->forEmployee($employee)->exists(), 404);

        return $survey;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validateAnswers(Request $request): array
    {
        return $request->validate(['answers' => ['required', 'array', 'max:30']])['answers'];
    }

    public static function giveRecognition(Request $request, Employee $giver, RecognitionFlow $flow): Recognition
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'badge' => ['required', Rule::in(RecognitionFlow::BADGES)],
            'message' => ['required', 'string', 'max:500'],
        ]);
        $colleague = Employee::query()->whereIn('status', EmployeeStatus::seated())->where('company_id', $giver->company_id)->findOrFail($data['employee_id']);

        return $flow->give($colleague, $data['badge'], $data['message'], $giver, $request->user());
    }

    /**
     * Recognition on /me: the company's wall and the colleagues one can thank.
     *
     * @return array<string, mixed>
     */
    public static function recognition(Employee $employee): array
    {
        return [
            ...RecognitionController::wall(Recognition::query()->where('company_id', $employee->company_id)),
            'colleagues' => Employee::query()->where('company_id', $employee->company_id)->whereKeyNot($employee->id)->whereIn('status', EmployeeStatus::seated())
                ->orderBy('name_en')->limit(500)->get()->map(fn (Employee $colleague): array => ['value' => $colleague->id, 'label' => $colleague->localized('name')])->all(),
            'badges' => RecognitionFlow::BADGES,
        ];
    }
}
