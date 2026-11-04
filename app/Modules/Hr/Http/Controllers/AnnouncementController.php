<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Engagement\AnnouncementFlow;
use App\Modules\Hr\Actions\Engagement\Audience;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Announcement;
use App\Modules\Hr\Models\AnnouncementRead;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Policies, circulars and news for staff, with who has read and acknowledged each one.
 */
class AnnouncementController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Audience $audience): Response
    {
        $reach = $this->reach(HrFeature::Announcements, FeatureAction::View);
        $visible = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated());

        $announcements = Announcement::query()
            ->when(! $reach->all, fn ($query) => $query->forPlaces((clone $visible)->distinct()->pluck('company_id')->all(), (clone $visible)->distinct()->pluck('branch_id')->all()))
            ->latest('published_at')->limit(50)->get();

        return $this->suitePage('Hr/Announcements/Index', [
            'announcements' => $announcements->map(function (Announcement $announcement) use ($visible, $audience): array {
                $people = $announcement->audienceEmployees()->whereIn('id', (clone $visible)->select('id'))->get();
                $reads = AnnouncementRead::query()->where('announcement_id', $announcement->id)->whereIn('employee_id', $people->pluck('id'))->get()->keyBy('employee_id');

                return [
                    ...self::announcementArray($announcement), 'audience' => $audience->label($announcement->company_id, $announcement->branch_id),
                    'people' => $people->count(), 'read' => $reads->count(), 'acknowledged' => $reads->whereNotNull('acknowledged_at')->count(),
                    'waiting' => $announcement->requires_ack ? $people->reject(fn (Employee $employee): bool => $reads->get($employee->id)?->acknowledged_at !== null)->take(50)->map(fn (Employee $employee): string => $employee->localized('name'))->values()->all() : [],
                ];
            })->all(),
            'audiences' => $audience->options($this->reach(HrFeature::Announcements, FeatureAction::Create)),
            'kinds' => AnnouncementFlow::KINDS,
        ], ['hr']);
    }

    public function store(Request $request, AnnouncementFlow $flow, Audience $audience): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(AnnouncementFlow::KINDS)],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'requires_ack' => ['boolean'],
            'audience' => ['required', 'string', 'max:30'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);
        [$companyId, $branchId] = Audience::parse($data['audience']);
        $target = $audience->resolve($companyId, $branchId, $this->reach(HrFeature::Announcements, FeatureAction::Create));

        $flow->publish((int) $request->user()->client_id, ['kind' => $data['kind'], 'title' => $data['title'], 'body' => $data['body'], 'requires_ack' => (bool) ($data['requires_ack'] ?? false)], $target, $request->file('file'), $request->user());
        $this->toast(__('hr.announcements.published'));

        return back();
    }

    public function file(Announcement $announcement): StreamedResponse
    {
        $reach = $this->reach(HrFeature::Announcements, FeatureAction::View);
        $visible = Employee::query()->visibleIn($reach);
        abort_unless($reach->all || Announcement::query()->whereKey($announcement->id)->forPlaces((clone $visible)->distinct()->pluck('company_id')->all(), (clone $visible)->distinct()->pluck('branch_id')->all())->exists(), 404);
        abort_if($announcement->file_path === null, 404);

        return Storage::disk(config('baydaq.hr.disk'))->download($announcement->file_path, $announcement->file_name);
    }

    /**
     * @return array<string, mixed>
     */
    public static function announcementArray(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id, 'kind' => $announcement->kind, 'title' => $announcement->title, 'body' => $announcement->body,
            'requires_ack' => $announcement->requires_ack, 'published_at' => $announcement->published_at->toIso8601String(),
            'file_name' => $announcement->file_name,
        ];
    }

    /**
     * The employee's feed, newest first, with what they have read and acknowledged.
     *
     * @return list<array<string, mixed>>
     */
    public static function feed(Employee $employee): array
    {
        $items = Announcement::query()->forEmployee($employee)->where('published_at', '<=', now())->latest('published_at')->limit(30)->get();
        $reads = AnnouncementRead::query()->where('employee_id', $employee->id)->whereIn('announcement_id', $items->pluck('id'))->get()->keyBy('announcement_id');

        return $items->map(fn (Announcement $announcement): array => [
            ...self::announcementArray($announcement),
            'read' => $reads->has($announcement->id), 'acknowledged_at' => $reads->get($announcement->id)?->acknowledged_at?->toIso8601String(),
        ])->all();
    }
}
