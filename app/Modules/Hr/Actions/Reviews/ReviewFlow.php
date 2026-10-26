<?php

namespace App\Modules\Hr\Actions\Reviews;

use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Review;
use App\Modules\Hr\Models\ReviewCycle;
use App\Modules\Platform\Access\BranchSet;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Performance reviews: a cycle opens a review for every working employee in reach, each employee
 * scores themselves (1–5 per criterion), then the manager scores, comments and sets goals. The
 * overall is the manager's weighted average.
 */
class ReviewFlow
{
    /**
     * @param  array{name: string, period_start: string, period_end: string, due_on: string, company_id?: ?int, criteria: list<array{key: string, label_ar: string, label_en: string, weight: int}>}  $data
     */
    public function launch(array $data, BranchSet $reach, ?Authenticatable $actor = null): ReviewCycle
    {
        return DB::transaction(function () use ($data, $reach, $actor): ReviewCycle {
            $cycle = (new ReviewCycle)->forceFill([...$data, 'status' => 'open', 'created_by' => $actor?->getAuthIdentifier()]);
            $cycle->save();

            $employees = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated())
                ->when($data['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))->pluck('id');

            foreach ($employees as $employeeId) {
                $cycle->reviews()->make()->forceFill(['client_id' => $cycle->client_id, 'employee_id' => $employeeId])->save();
            }

            return $cycle;
        });
    }

    /**
     * @param  array<string, int>  $scores
     */
    public function selfAssess(Review $review, array $scores, ?string $comment): Review
    {
        $this->expectOpen($review);

        if ($review->reviewed_at !== null) {
            throw ValidationException::withMessages(['scores' => __('hr.reviews.errors.done')]);
        }

        $review->forceFill(['self_scores' => $this->clean($review, $scores), 'self_comment' => $comment, 'self_submitted_at' => now()])->save();

        return $review;
    }

    /**
     * @param  array<string, int>  $scores
     */
    public function managerAssess(Review $review, array $scores, ?string $comment, ?string $goals, ?Authenticatable $actor = null): Review
    {
        $this->expectOpen($review);
        $scores = $this->clean($review, $scores);
        $criteria = collect($review->cycle->criteria);
        $weights = $criteria->sum('weight') ?: $criteria->count();
        $total = $criteria->reduce(fn (BigDecimal $sum, array $criterion): BigDecimal => $sum->plus(BigDecimal::of($scores[$criterion['key']])->multipliedBy($criterion['weight'] ?: 1)), BigDecimal::zero());

        $review->forceFill([
            'manager_scores' => $scores, 'manager_comment' => $comment, 'goals' => $goals,
            'overall' => (string) $total->dividedBy($weights, 2, RoundingMode::HalfUp),
            'reviewed_by' => $actor?->getAuthIdentifier(), 'reviewed_at' => now(),
        ])->save();

        return $review;
    }

    /**
     * @param  array<string, mixed>  $scores
     * @return array<string, int>
     */
    private function clean(Review $review, array $scores): array
    {
        $clean = [];

        foreach ($review->cycle->criteria as $criterion) {
            $score = (int) ($scores[$criterion['key']] ?? 0);

            if ($score < 1 || $score > 5) {
                throw ValidationException::withMessages(["scores.{$criterion['key']}" => __('hr.reviews.errors.score')]);
            }

            $clean[$criterion['key']] = $score;
        }

        return $clean;
    }

    private function expectOpen(Review $review): void
    {
        if ($review->cycle->status !== 'open') {
            throw ValidationException::withMessages(['scores' => __('hr.reviews.errors.closed')]);
        }
    }
}
