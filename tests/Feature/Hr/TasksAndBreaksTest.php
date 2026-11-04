<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Enums\BreakStatus;
use App\Modules\Hr\Enums\TaskStatus;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\Task;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Enums\ScopeLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsSuite;
use Tests\TestCase;

/**
 * Tasks given to employees and followed up from the app, and the break system.
 */
class TasksAndBreaksTest extends TestCase
{
    use BuildsSuite, RefreshDatabase;

    private Employee $sara;

    private User $saraUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('hr');
        $this->buildSuite();
        $this->travelTo('2026-11-01 07:00:00'); // 10:00 in Riyadh
        $this->sara = $this->employeeAt($this->branch('RUH'), ['name_en' => 'Sara', 'name_ar' => 'سارة', 'status' => 'active']);
        app(LinkEmployeeUser::class)->handle($this->sara, 'sara@example.test');
        $this->saraUser = User::query()->where('email', 'sara@example.test')->sole();
        $this->saraUser->forceFill(['must_change_password' => false])->save();
    }

    private function clockIn(Employee $employee, string $at = '2026-11-01 06:00:00'): void
    {
        (new AttendancePunch)->forceFill([
            'client_id' => $this->client->id, 'employee_id' => $employee->id, 'type' => 'in', 'punched_at' => $at, 'work_date' => '2026-11-01',
            'branch_id' => $employee->branch_id, 'home_branch_id' => $employee->branch_id,
        ])->save();
    }

    public function test_a_manager_assigns_a_task_and_the_employee_finishes_it_from_the_app(): void
    {
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/tasks'), [
            'title' => 'Clean the coffee machine', 'priority' => 'high', 'assignee_id' => $this->sara->id, 'needs_photo' => true,
            'due_at' => '2026-11-01T12:00:00Z', 'checklist' => [['text' => 'Descale'], ['text' => 'Wipe'], ['text' => '']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $task = Task::query()->sole();
        $this->assertCount(2, $task->checklist);
        $this->assertSame($this->branch('RUH')->id, $task->branch_id);

        Sanctum::actingAs($this->saraUser);
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('tasks.0.title', 'Clean the coffee machine')->assertJsonMissingPath('tasks.0.assignee_id');
        $this->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk()->assertJsonPath('status', 'in_progress');
        $this->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done', 'checked' => [0, 1]])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->post("/api/v1/tasks/{$task->id}/status", ['status' => 'done', 'checked' => [0, 1], 'photo' => UploadedFile::fake()->image('machine.jpg'), 'note' => 'Shiny'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('comments.0.body', 'Shiny');

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        Storage::disk('hr')->assertExists($task->photo_path);

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/tasks'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Tasks/Index')->where('tasks.0.status', 'done')->where('tasks.0.has_photo', true));
    }

    public function test_tasks_stay_inside_the_managers_reach_and_the_employees_own_list(): void
    {
        $other = $this->employeeAt($this->branch('JED'), ['status' => 'active']);
        $jeddahLead = $this->memberWith(['hr.tasks.view', 'hr.tasks.create', 'hr.tasks.update'], ScopeLevel::Branch, $this->branch('JED'));

        $this->actingAs($jeddahLead)->post($this->suiteUrl('hr/tasks'), ['title' => 'X', 'priority' => 'normal', 'assignee_id' => $this->sara->id])->assertForbidden();
        $this->actingAs($jeddahLead)->post($this->suiteUrl('hr/tasks'), ['title' => 'Count stock', 'priority' => 'normal', 'assignee_id' => $other->id])->assertSessionHasNoErrors();
        $task = Task::query()->sole();

        Sanctum::actingAs($this->saraUser);
        $this->getJson('/api/v1/tasks')->assertJsonCount(0, 'tasks');
        $this->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'done'])->assertNotFound();
        $this->postJson('/api/v1/tasks', ['title' => 'Y', 'assignee_id' => $other->id])->assertForbidden();
    }

    public function test_an_employee_takes_the_scheduled_break_and_comes_back(): void
    {
        (new Shift)->forceFill([
            'client_id' => $this->client->id, 'company_id' => $this->company->id, 'branch_id' => $this->branch('RUH')->id, 'employee_id' => $this->sara->id,
            'shift_date' => '2026-11-01', 'starts_at' => '09:00', 'ends_at' => '17:00', 'minutes' => 480, 'break_at' => '13:00', 'break_minutes' => 45,
        ])->save();
        Sanctum::actingAs($this->saraUser);

        $this->postJson('/api/v1/breaks')->assertUnprocessable()->assertJsonValidationErrors('employee'); // not clocked in
        $this->clockIn($this->sara);
        $this->travelTo('2026-11-01 10:05:00'); // 13:05 in Riyadh

        $this->getJson('/api/v1/breaks')->assertOk()->assertJsonPath('scheduled.at', '13:00')->assertJsonPath('current', null);
        $id = $this->postJson('/api/v1/breaks')->assertCreated()->assertJsonPath('kind', 'scheduled')->assertJsonPath('status', 'on_break')->assertJsonPath('minutes', 45)->json('id');
        $this->postJson('/api/v1/breaks')->assertUnprocessable();

        $this->travel(50)->minutes();
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/breaks'))->assertInertia(fn (Assert $page) => $page
            ->component('Hr/Breaks/Index')->where('on_break.0.overdue', true));

        Sanctum::actingAs($this->saraUser);
        $this->postJson("/api/v1/breaks/{$id}/end")->assertOk()->assertJsonPath('status', 'ended');
    }

    public function test_breaks_wait_for_a_manager_when_asked_for_and_the_branch_cap_holds(): void
    {
        app(HrSettings::class)->set('break_self_start', false);
        app(HrSettings::class)->set('break_max_concurrent', 1);
        $ali = $this->employeeAt($this->branch('RUH'), ['status' => 'active']);
        $this->clockIn($this->sara);
        $this->clockIn($ali);

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/breaks/employees/{$ali->id}"), ['minutes' => 30])->assertSessionHasNoErrors();

        Sanctum::actingAs($this->saraUser);
        $this->postJson('/api/v1/breaks', ['note' => 'prayer'])->assertCreated()->assertJsonPath('status', 'requested');
        $request = WorkBreak::query()->where('employee_id', $this->sara->id)->sole();

        $this->actingAs($this->owner)->post($this->suiteUrl("hr/breaks/{$request->id}/decide"), ['approve' => true])->assertSessionHasErrors('status'); // Ali is out
        $this->actingAs($this->owner)->post($this->suiteUrl('hr/breaks/'.WorkBreak::query()->where('employee_id', $ali->id)->value('id').'/end'))->assertRedirect();
        $this->actingAs($this->owner)->post($this->suiteUrl("hr/breaks/{$request->id}/decide"), ['approve' => true])->assertSessionHasNoErrors();
        $this->assertSame(BreakStatus::OnBreak, $request->fresh()->status);

        $jeddah = $this->memberWith(['hr.breaks.view', 'hr.breaks.approve'], ScopeLevel::Branch, $this->branch('JED'));
        $this->actingAs($jeddah)->post($this->suiteUrl("hr/breaks/{$request->id}/end"))->assertForbidden();
    }

    public function test_the_board_flags_five_hours_without_a_break(): void
    {
        $this->clockIn($this->sara, '2026-11-01 04:00:00'); // 07:00 in Riyadh
        $this->travelTo('2026-11-01 09:30:00'); // 5.5 hours later

        $this->actingAs($this->owner)->get($this->suiteUrl('hr/breaks'))->assertInertia(fn (Assert $page) => $page
            ->where('working.0.name', 'سارة')->where('working.0.worked_minutes', 330));

        $this->actingAs($this->saraUser)->get('/me')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('break_state.current', null)->has('tasks', 0));
        $this->actingAs($this->saraUser)->post('/me/breaks')->assertRedirect();
        $this->actingAs($this->owner)->get($this->suiteUrl('hr/breaks'))->assertInertia(fn (Assert $page) => $page->has('working', 0)->has('on_break', 1));
    }
}
