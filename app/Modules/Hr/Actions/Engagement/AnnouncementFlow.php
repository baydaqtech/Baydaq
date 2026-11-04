<?php

namespace App\Modules\Hr\Actions\Engagement;

use App\Modules\Hr\Models\Announcement;
use App\Modules\Hr\Models\AnnouncementRead;
use App\Modules\Hr\Models\Employee;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Policies, circulars and news: published to an audience, read by employees, and acknowledged
 * ("I have read and accept") when the post asks for it.
 */
class AnnouncementFlow
{
    public const KINDS = ['policy', 'circular', 'news'];

    public function __construct(private Audit $audit) {}

    /**
     * @param  array{kind: string, title: string, body: string, requires_ack?: bool}  $data
     * @param  array{company_id: ?int, branch_id: ?int}  $audience
     */
    public function publish(int $clientId, array $data, array $audience, ?UploadedFile $file, ?Authenticatable $actor = null): Announcement
    {
        $announcement = new Announcement($data);
        $announcement->forceFill([
            'client_id' => $clientId, ...$audience, 'published_at' => now(), 'created_by' => $actor?->getAuthIdentifier(),
            'file_path' => $file?->store("clients/{$clientId}/announcements", config('baydaq.hr.disk')),
            'file_name' => $file?->getClientOriginalName(),
        ])->save();
        $this->audit->record($announcement, 'published', ['kind' => $announcement->kind], $actor);

        return $announcement;
    }

    /** The employee opened it; acknowledging also records the time they accepted it. */
    public function read(Announcement $announcement, Employee $employee, bool $acknowledge = false): AnnouncementRead
    {
        if ($acknowledge && ! $announcement->requires_ack) {
            throw ValidationException::withMessages(['announcement' => __('hr.announcements.errors.no_ack')]);
        }

        $existing = fn (): ?AnnouncementRead => AnnouncementRead::query()->where('announcement_id', $announcement->id)->where('employee_id', $employee->id)->first();
        $read = $existing();

        if ($read === null) {
            try {
                $read = (new AnnouncementRead)->forceFill(['client_id' => $employee->client_id, 'announcement_id' => $announcement->id, 'employee_id' => $employee->id, 'read_at' => now()]);
                $read->save();
            } catch (UniqueConstraintViolationException) {
                $read = $existing(); // opened twice at once
            }
        }

        if ($acknowledge && $read->acknowledged_at === null) {
            $read->forceFill(['acknowledged_at' => now()])->save();
        }

        return $read;
    }
}
