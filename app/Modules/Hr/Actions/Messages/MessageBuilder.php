<?php

namespace App\Modules\Hr\Actions\Messages;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeMessage;
use App\Modules\Hr\Models\MessageTemplate;
use App\Modules\Hr\Notifications\EmployeeMessageMail;
use App\Modules\Platform\Ports\Accounts;
use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Ports\OrgDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The message builder. A template has a subject and body with placeholders ({{name}}, {{job}}...),
 * an audience, channels, and a trigger: sent by hand, on a weekday or a day of the month, or on an
 * employee event. Each send is stored as the employee's inbox entry; the (template, employee,
 * occurrence) key makes every send happen once even if the daily job runs twice.
 */
class MessageBuilder
{
    public const TRIGGERS = ['manual', 'weekly', 'monthly', 'event'];

    public const EVENTS = ['hired', 'job_changed', 'birthday', 'work_anniversary', 'contract_ending'];

    public const CHANNELS = ['inapp', 'email', 'whatsapp'];

    public const PLACEHOLDERS = ['name', 'first_name', 'job', 'branch', 'company', 'hired_on', 'years', 'date', 'contract_ends_on'];

    public function __construct(private OrgDirectory $directory, private Accounts $accounts, private Messaging $messaging) {}

    /**
     * Fill the placeholders for one employee.
     *
     * @return array{subject: string, body: string}
     */
    public function render(MessageTemplate $template, Employee $employee, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today('Asia/Riyadh');
        $name = (string) $employee->localized('name');
        $contract = $employee->relationLoaded('activeContract') ? $employee->activeContract : $employee->activeContract()->first();
        $values = [
            'name' => $name,
            'first_name' => strtok($name, ' ') ?: $name,
            'job' => (string) $employee->job?->localized('name'),
            'branch' => (string) $this->directory->branch($employee->branch_id)?->name,
            'company' => (string) $this->directory->company($employee->company_id)?->name,
            'hired_on' => (string) $employee->hired_on?->toDateString(),
            'years' => (string) ($employee->hired_on ? (int) $employee->hired_on->diffInYears($today) : 0),
            'date' => $today->toDateString(),
            'contract_ends_on' => (string) $contract?->ends_on?->toDateString(),
        ];
        $fill = fn (string $text): string => preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn (array $match): string => $values[$match[1]] ?? $match[0], $text);

        return ['subject' => $fill($template->subject), 'body' => $fill($template->body)];
    }

    /**
     * Send one template to one employee once per occurrence. Returns null when it was already sent.
     */
    public function deliver(MessageTemplate $template, Employee $employee, string $occurrence, ?CarbonImmutable $today = null): ?EmployeeMessage
    {
        $text = $this->render($template, $employee, $today);

        try {
            $message = (new EmployeeMessage)->forceFill([
                'client_id' => $employee->client_id, 'template_id' => $template->id, 'employee_id' => $employee->id, 'occurrence' => $occurrence,
                ...$text, 'channels' => $template->channels, 'sent_at' => now(),
            ]);
            $message->save();
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        if (in_array('email', $template->channels, true) && $employee->user_id) {
            $this->accounts->notify($employee->user_id, new EmployeeMessageMail($text['subject'], $text['body']));
        }

        if (in_array('whatsapp', $template->channels, true) && $employee->phone) {
            $this->messaging->send($employee->phone, 'hr_message', ['name' => (string) $employee->localized('name'), ...$text], app()->getLocale(), "hr.message:{$message->id}");
        }

        return $message;
    }

    /**
     * "Send now": everyone in the template's audience.
     */
    public function sendNow(MessageTemplate $template): int
    {
        $occurrence = 'manual:'.now()->format('YmdHis');

        return $this->audience($template)->filter(fn (Employee $employee): bool => $this->deliver($template, $employee, $occurrence) !== null)->count();
    }

    /**
     * Something happened to an employee: send every active event template for it whose audience includes them.
     */
    public function onEvent(string $event, Employee $employee, string $occurrence): int
    {
        if ($employee->status === EmployeeStatus::Terminated) {
            return 0;
        }

        return MessageTemplate::query()->where('is_active', true)->where('trigger', 'event')->where('event', $event)->forEmployee($employee)->get()
            ->filter(fn (MessageTemplate $template): bool => $this->deliver($template, $employee, $occurrence) !== null)->count();
    }

    /**
     * The daily run for the current tenant: weekly and monthly templates due today, birthdays, work
     * anniversaries and contracts ending.
     */
    public function runDaily(CarbonImmutable $today): int
    {
        $sent = 0;

        foreach (MessageTemplate::query()->where('is_active', true)->whereIn('trigger', ['weekly', 'monthly', 'event'])->get() as $template) {
            [$due, $occurrence] = match (true) {
                $template->trigger === 'weekly' && $template->weekday === $today->dayOfWeek => [$this->audience($template), 'week:'.$today->format('o-W')],
                $template->trigger === 'monthly' && $template->month_day === $today->day => [$this->audience($template), 'month:'.$today->format('Y-m')],
                $template->trigger === 'event' => [$this->dueForEvent($template, $today), null],
                default => [collect(), null],
            };

            foreach ($due as $employee) {
                $key = $occurrence ?? match ($template->event) {
                    'birthday' => 'birthday:'.$today->year,
                    'work_anniversary' => 'anniversary:'.$today->year,
                    default => 'contract:'.$employee->activeContract?->id,
                };
                $sent += $this->deliver($template, $employee, $key, $today) !== null ? 1 : 0;
            }

            $template->forceFill(['last_run_on' => $today->toDateString()])->save();
        }

        return $sent;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function audience(MessageTemplate $template): Collection
    {
        return $template->audienceEmployees()->whereIn('status', EmployeeStatus::seated())->with(['job', 'activeContract'])->get();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function dueForEvent(MessageTemplate $template, CarbonImmutable $today): Collection
    {
        $people = $template->audienceEmployees()->whereIn('status', EmployeeStatus::seated())->with(['job', 'activeContract']);

        return match ($template->event) {
            'birthday' => $people->whereMonth('birth_date', $today->month)->whereDay('birth_date', $today->day)->get(),
            'work_anniversary' => $people->whereMonth('hired_on', $today->month)->whereDay('hired_on', $today->day)->whereYear('hired_on', '<', $today->year)->get(),
            'contract_ending' => $people->whereHas('activeContract', fn ($query) => $query->whereDate('ends_on', $today->addDays($template->days_before ?? 30)))->get(),
            default => collect(),
        };
    }

    /** Run an employee event after the surrounding transaction commits. */
    public static function afterCommit(string $event, Employee $employee, string $occurrence): void
    {
        DB::afterCommit(fn () => app(self::class)->onEvent($event, $employee->fresh(['job', 'activeContract']) ?? $employee, $occurrence));
    }
}
