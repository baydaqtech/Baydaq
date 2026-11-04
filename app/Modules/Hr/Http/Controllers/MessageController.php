<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Engagement\Audience;
use App\Modules\Hr\Actions\Messages\MessageBuilder;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMessage;
use App\Modules\Hr\Models\MessageTemplate;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * The message builder: templates sent by hand, weekly, monthly or on employee events.
 */
class MessageController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function index(Audience $audience): Response
    {
        $reach = $this->reach(HrFeature::Messages, FeatureAction::View);
        $visible = Employee::query()->visibleIn($reach);
        $templates = MessageTemplate::query()
            ->when(! $reach->all, fn ($query) => $query->forPlaces((clone $visible)->distinct()->pluck('company_id')->all(), (clone $visible)->distinct()->pluck('branch_id')->all()))
            ->latest()->get();
        $sent = EmployeeMessage::query()->whereIn('template_id', $templates->pluck('id'))->selectRaw('template_id, count(*) as total, max(sent_at) as last_sent')->groupBy('template_id')->get()->keyBy('template_id');

        return $this->suitePage('Hr/Messages/Index', [
            'templates' => $templates->map(fn (MessageTemplate $template): array => [
                ...self::templateArray($template), 'audience_label' => $audience->label($template->company_id, $template->branch_id),
                'sent' => (int) ($sent->get($template->id)?->total ?? 0), 'last_sent' => $sent->get($template->id)?->last_sent,
                'can_update' => $this->canAddress($template),
            ])->all(),
            'audiences' => $audience->options($this->reach(HrFeature::Messages, FeatureAction::Create)),
            'triggers' => MessageBuilder::TRIGGERS,
            'events' => MessageBuilder::EVENTS,
            'channels' => MessageBuilder::CHANNELS,
            'placeholders' => MessageBuilder::PLACEHOLDERS,
        ], ['hr']);
    }

    public function store(Request $request, Audience $audience): RedirectResponse
    {
        $data = $this->validateTemplate($request);
        [$companyId, $branchId] = Audience::parse($data['audience']);
        $target = $audience->resolve($companyId, $branchId, $this->reach(HrFeature::Messages, FeatureAction::Create));

        $template = new MessageTemplate(self::clean($data));
        $template->forceFill(['client_id' => $request->user()->client_id, ...$target, 'created_by' => $request->user()->id])->save();
        $this->toast(__('hr.messages.saved'));

        return back();
    }

    public function update(Request $request, MessageTemplate $template, Audience $audience): RedirectResponse
    {
        abort_unless($this->canAddress($template), 403);
        $data = $this->validateTemplate($request);
        [$companyId, $branchId] = Audience::parse($data['audience']);
        $target = $audience->resolve($companyId, $branchId, $this->reach(HrFeature::Messages, FeatureAction::Update));

        $template->fill(self::clean($data))->forceFill($target)->save();
        $this->toast(__('hr.messages.saved'));

        return back();
    }

    public function send(MessageTemplate $template, MessageBuilder $builder): RedirectResponse
    {
        abort_unless($this->canAddress($template), 403);
        $count = $builder->sendNow($template);
        $this->toast(__('hr.messages.sent', ['count' => $count]));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function templateArray(MessageTemplate $template): array
    {
        return [
            'id' => $template->id, 'name' => $template->name, 'trigger' => $template->trigger, 'event' => $template->event,
            'weekday' => $template->weekday, 'month_day' => $template->month_day, 'days_before' => $template->days_before,
            'channels' => $template->channels, 'subject' => $template->subject, 'body' => $template->body, 'is_active' => $template->is_active,
            'audience' => $template->branch_id ? "b-{$template->branch_id}" : ($template->company_id ? "c-{$template->company_id}" : 'all'),
        ];
    }

    /**
     * The employee's inbox.
     *
     * @return list<array<string, mixed>>
     */
    public static function inbox(Employee $employee): array
    {
        return EmployeeMessage::query()->where('employee_id', $employee->id)->latest('sent_at')->limit(30)->get()
            ->map(fn (EmployeeMessage $message): array => ['id' => $message->id, 'subject' => $message->subject, 'body' => $message->body, 'sent_at' => $message->sent_at->toIso8601String(), 'read' => $message->read_at !== null])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger' => ['required', Rule::in(MessageBuilder::TRIGGERS)],
            'event' => ['nullable', 'required_if:trigger,event', Rule::in(MessageBuilder::EVENTS)],
            'weekday' => ['nullable', 'required_if:trigger,weekly', 'integer', 'between:0,6'],
            'month_day' => ['nullable', 'required_if:trigger,monthly', 'integer', 'between:1,28'],
            'days_before' => ['nullable', 'integer', 'between:1,180'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(MessageBuilder::CHANNELS)],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'audience' => ['required', 'string', 'max:30'],
        ]);
    }

    /**
     * Keep only the schedule fields the trigger uses; the in-app inbox always gets a copy.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return [
            'name' => $data['name'], 'trigger' => $data['trigger'], 'subject' => $data['subject'], 'body' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'event' => $data['trigger'] === 'event' ? $data['event'] : null,
            'weekday' => $data['trigger'] === 'weekly' ? (int) $data['weekday'] : null,
            'month_day' => $data['trigger'] === 'monthly' ? (int) $data['month_day'] : null,
            'days_before' => ($data['event'] ?? null) === 'contract_ending' ? (int) ($data['days_before'] ?? 30) : null,
            'channels' => array_values(array_unique(['inapp', ...$data['channels']])),
        ];
    }

    private function canAddress(MessageTemplate $template): bool
    {
        $reach = $this->reach(HrFeature::Messages, FeatureAction::Update)->merge($this->reach(HrFeature::Messages, FeatureAction::Create));

        return match (true) {
            $template->branch_id !== null => $reach->allowsBranch($template->branch_id, (int) $template->company_id),
            $template->company_id !== null => $reach->allowsWholeCompany($template->company_id),
            default => $reach->all,
        };
    }
}
