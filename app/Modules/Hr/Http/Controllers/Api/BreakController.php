<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Breaks\BreakFlow;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Models\WorkBreak;
use App\Modules\Hr\Support\HrSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The employee app's break button: today's planned break, the break in progress, and taking or
 * ending a break.
 */
class BreakController extends Controller
{
    use ResolvesEmployee;

    public function __construct(private BreakFlow $flow) {}

    public function show(Request $request, HrSettings $settings): JsonResponse
    {
        $employee = $this->employee($request);
        $shift = $this->flow->shiftToday($employee);
        $active = $this->flow->active($employee);

        return response()->json([
            'scheduled' => $shift?->break_at ? ['at' => substr($shift->break_at, 0, 5), 'minutes' => $shift->break_minutes] : null,
            'current' => $active ? self::present($active) : null,
            'today' => WorkBreak::query()->where('employee_id', $employee->id)->whereDate('work_date', CarbonImmutable::now('Asia/Riyadh')->toDateString())
                ->latest('id')->get()->map(fn (WorkBreak $break): array => self::present($break))->all(),
            'policy' => ['minutes' => (int) $settings->get('break_minutes'), 'self_start' => (bool) $settings->get('break_self_start')],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['minutes' => ['nullable', 'integer', 'min:5', 'max:120'], 'note' => ['nullable', 'string', 'max:255']]);
        $break = $this->flow->take($this->employee($request), $data['minutes'] ?? null, $data['note'] ?? null, $request->user());

        return response()->json(self::present($break), 201);
    }

    public function end(Request $request, WorkBreak $break): JsonResponse
    {
        abort_unless($break->employee_id === $this->employee($request)->id, 404);

        return response()->json(self::present($this->flow->end($break)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(WorkBreak $break): array
    {
        return [
            'id' => $break->id, 'kind' => $break->kind->value, 'status' => $break->status->value, 'minutes' => $break->minutes,
            'starts_at' => $break->starts_at?->toIso8601String(), 'back_at' => $break->dueBackAt()?->toIso8601String(),
            'ends_at' => $break->ends_at?->toIso8601String(), 'overdue' => $break->isOverdue(),
        ];
    }
}
