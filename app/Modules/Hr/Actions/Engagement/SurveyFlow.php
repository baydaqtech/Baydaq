<?php

namespace App\Modules\Hr\Actions\Engagement;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Survey;
use App\Modules\Hr\Models\SurveyResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Surveys: questions rated 1–5, eNPS 0–10, a choice, or free text. Anonymous surveys store a keyed
 * hash instead of the employee, so each person answers once and nobody can see who said what;
 * their results show only once enough people answered.
 */
class SurveyFlow
{
    public const TYPES = ['rating', 'nps', 'choice', 'text'];

    /** Fewest answers before an anonymous survey's results show. */
    public const MIN_ANONYMOUS = 3;

    /**
     * @param  list<array{type: string, label: string, options?: list<string>}>  $questions
     * @return list<array{id: string, type: string, label: string, options: list<string>}>
     */
    public static function normalizeQuestions(array $questions): array
    {
        return array_values(array_map(fn (array $question): array => [
            'id' => Str::random(8),
            'type' => $question['type'],
            'label' => $question['label'],
            'options' => $question['type'] === 'choice' ? array_values(array_filter(array_map('trim', $question['options'] ?? []), fn (string $option): bool => $option !== '')) : [],
        ], $questions));
    }

    public static function respondentKey(Survey $survey, Employee $employee): string
    {
        return hash_hmac('sha256', "survey:{$survey->id}:employee:{$employee->id}", (string) config('app.key'));
    }

    public function hasAnswered(Survey $survey, Employee $employee): bool
    {
        return SurveyResponse::query()->where('survey_id', $survey->id)->where('respondent_key', self::respondentKey($survey, $employee))->exists();
    }

    /**
     * @param  array<string, mixed>  $answers  question id => answer
     */
    public function answer(Survey $survey, Employee $employee, array $answers): SurveyResponse
    {
        if ($survey->status !== 'open' || ($survey->closes_on !== null && $survey->closes_on->lt(today()))) {
            throw ValidationException::withMessages(['answers' => __('hr.surveys.errors.closed')]);
        }

        $clean = [];

        foreach ($survey->questions as $question) {
            $value = $answers[$question['id']] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $valid = match ($question['type']) {
                'rating' => is_numeric($value) && (int) $value >= 1 && (int) $value <= 5,
                'nps' => is_numeric($value) && (int) $value >= 0 && (int) $value <= 10,
                'choice' => in_array($value, $question['options'], true),
                default => is_string($value) && mb_strlen($value) <= 2000,
            };

            if (! $valid) {
                throw ValidationException::withMessages(["answers.{$question['id']}" => __('hr.surveys.errors.answer')]);
            }

            $clean[$question['id']] = in_array($question['type'], ['rating', 'nps'], true) ? (int) $value : $value;
        }

        if ($clean === []) {
            throw ValidationException::withMessages(['answers' => __('hr.surveys.errors.empty')]);
        }

        try {
            // Anonymous answers keep only the day, so the time can't be matched to who was online.
            $response = (new SurveyResponse)->forceFill([
                'client_id' => $survey->client_id, 'survey_id' => $survey->id, 'employee_id' => $survey->anonymous ? null : $employee->id,
                'respondent_key' => self::respondentKey($survey, $employee), 'answers' => $clean, 'created_at' => $survey->anonymous ? now()->startOfDay() : now(),
            ]);
            $response->save();

            return $response;
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['answers' => __('hr.surveys.errors.answered')]);
        }
    }

    /**
     * Totals per question. Named surveys also list who answered; anonymous ones stay hidden below the minimum.
     *
     * @return array{count: int, hidden: bool, questions: list<array<string, mixed>>}
     */
    public function results(Survey $survey): array
    {
        $responses = SurveyResponse::query()->where('survey_id', $survey->id)->get();
        $hidden = $survey->anonymous && $responses->count() < self::MIN_ANONYMOUS;

        return [
            'count' => $responses->count(),
            'hidden' => $hidden,
            'questions' => $hidden ? [] : array_map(function (array $question) use ($responses): array {
                $values = $responses->pluck("answers.{$question['id']}")->filter(fn ($value) => $value !== null)->values();

                return [
                    ...$question,
                    'answered' => $values->count(),
                    'average' => in_array($question['type'], ['rating', 'nps'], true) && $values->isNotEmpty() ? round($values->avg(), 1) : null,
                    // eNPS = % promoters (9–10) − % detractors (0–6).
                    'enps' => $question['type'] === 'nps' && $values->isNotEmpty()
                        ? (int) round(($values->filter(fn ($value) => $value >= 9)->count() - $values->filter(fn ($value) => $value <= 6)->count()) / $values->count() * 100)
                        : null,
                    'counts' => in_array($question['type'], ['rating', 'nps', 'choice'], true) ? $values->countBy()->sortKeys()->all() : null,
                    'texts' => $question['type'] === 'text' ? $values->shuffle()->take(100)->values()->all() : null,
                ];
            }, $survey->questions),
        ];
    }
}
