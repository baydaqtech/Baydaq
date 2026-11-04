<?php

namespace App\Modules\Hr\Actions\Documents;

use App\Modules\Hr\Models\DocumentRenewal;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Platform\Access\BranchSet;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;

/**
 * Documents expiring in the next 90 days (and those already expired), bucketed by 30/60/90,
 * with what renewing them is expected to cost and whether a renewal task is open (H6).
 */
class DocumentRadar
{
    public const BUCKETS = ['expired' => 0, '30' => 30, '60' => 60, '90' => 90];

    /**
     * @return array{buckets: array<string, array{count: int, cost: string}>, rows: list<array<string, mixed>>}
     */
    public function handle(BranchSet $reach, ?Carbon $today = null): array
    {
        $today ??= today();
        $documents = EmployeeDocument::query()
            ->whereNotNull('expires_on')->whereDate('expires_on', '<=', $today->copy()->addDays(90))
            ->whereHas('employee', fn ($query) => $query->visibleIn($reach)->whereIn('status', ['active', 'on_leave', 'suspended']))
            ->with(['type', 'employee:id,name_ar,name_en,branch_id,company_id'])->orderBy('expires_on')->get();

        $renewals = DocumentRenewal::query()->whereIn('employee_document_id', $documents->pluck('id'))->get()
            ->keyBy(fn (DocumentRenewal $renewal): string => $renewal->employee_document_id.'|'.$renewal->expires_on->toDateString());

        $rows = $documents->map(function (EmployeeDocument $document) use ($today, $renewals): array {
            $daysLeft = (int) $today->diffInDays($document->expires_on, false);
            $renewal = $renewals[$document->id.'|'.$document->expires_on->toDateString()] ?? null;

            return [
                'id' => $document->id,
                'employee_id' => $document->employee_id,
                'employee' => $document->employee->localized('name'),
                'type' => $document->type->localized('name'),
                'expires_on' => $document->expires_on->toDateString(),
                'days_left' => $daysLeft,
                'bucket' => $this->bucket($daysLeft),
                'cost' => $renewal?->cost !== null ? (string) $renewal->cost : ($document->type->renewal_cost !== null ? (string) $document->type->renewal_cost : null),
                'renewal' => $renewal ? ['id' => $renewal->id, 'status' => $renewal->status] : null,
            ];
        });

        $buckets = collect(self::BUCKETS)->map(fn (int $days, string $bucket): array => [
            'count' => $rows->where('bucket', $bucket)->count(),
            'cost' => (string) $rows->where('bucket', $bucket)->reduce(fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['cost'] ?? '0'), BigDecimal::zero())->toScale(2),
        ])->all();

        return ['buckets' => $buckets, 'rows' => $rows->values()->all()];
    }

    private function bucket(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'expired',
            $daysLeft <= 30 => '30',
            $daysLeft <= 60 => '60',
            default => '90',
        };
    }
}
