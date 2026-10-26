<?php

namespace App\Modules\Assets\Actions\Inventory;

use App\Modules\Assets\Enums\AssetStatus;
use App\Modules\Assets\Enums\HolderType;
use App\Modules\Assets\Enums\IncidentKind;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\ItemVariant;
use App\Modules\Assets\Models\MovementLine;
use App\Modules\Assets\Support\Holder;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Shared by transfers and handovers: checking what is sent, and settling what was counted on
 * arrival. Every unit sent ends up either with the receiver or in an incident.
 */
class MovementLines
{
    public function __construct(private Quantities $quantities, private StockLedger $ledger, private RecordIncident $incidents) {}

    /**
     * @param  list<array{variant_id?: ?int, asset_id?: ?int, quantity?: numeric-string|float|int|null, unit?: ?string, condition?: ?string, note?: ?string}>  $lines
     * @return list<array{variant: ?ItemVariant, asset: ?Asset, quantity: string, entered: ?string, condition: ?string, note: ?string}>
     */
    public function prepare(array $lines, Holder $from, int $companyId): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('assets.errors.no_lines')]);
        }

        $prepared = [];

        foreach ($lines as $index => $line) {
            if (! empty($line['asset_id'])) {
                $asset = Asset::query()->with('openCustody')->where('company_id', $companyId)->find($line['asset_id'])
                    ?? throw ValidationException::withMessages(["lines.{$index}" => __('assets.errors.unknown_asset')]);
                $held = match ($from->type) {
                    HolderType::Employee => $asset->openCustody?->employee_id === $from->id,
                    HolderType::Warehouse => $asset->warehouse_id === $from->id && $asset->status === AssetStatus::Available,
                    default => false,
                };

                if (! $held) {
                    throw ValidationException::withMessages(["lines.{$index}" => __('assets.errors.asset_not_held', ['tag' => $asset->tag])]);
                }

                $prepared[] = ['variant' => null, 'asset' => $asset, 'quantity' => '1', 'entered' => null, 'condition' => $line['condition'] ?? null, 'note' => $line['note'] ?? null];

                continue;
            }

            $variant = ItemVariant::query()->whereHas('item')->find($line['variant_id'] ?? 0) ?? throw ValidationException::withMessages(["lines.{$index}" => __('assets.errors.unknown_item')]);
            [$quantity, $entered] = $this->quantities->toStockUnits($variant, (string) ($line['quantity'] ?? 0), $line['unit'] ?? null);
            $prepared[] = ['variant' => $variant, 'asset' => null, 'quantity' => $quantity, 'entered' => $entered, 'condition' => $line['condition'] ?? null, 'note' => $line['note'] ?? null];
        }

        return $prepared;
    }

    /**
     * Write the lines and move the quantities from the sender into the document while it travels.
     *
     * @param  list<array{variant: ?ItemVariant, asset: ?Asset, quantity: string, entered: ?string, condition: ?string, note: ?string}>  $prepared
     */
    public function dispatch(array $prepared, string $documentType, int $documentId, Holder $from, ?Authenticatable $actor): void
    {
        $inFlight = Holder::of($documentType, $documentId);

        foreach ($prepared as $line) {
            (new MovementLine)->forceFill([
                'document_type' => $documentType, 'document_id' => $documentId, 'variant_id' => $line['variant']?->id, 'asset_id' => $line['asset']?->id,
                'quantity' => $line['quantity'], 'entered_unit' => $line['entered'], 'condition' => $line['condition'], 'note' => $line['note'],
            ])->save();

            if ($line['variant']) {
                $this->ledger->move($line['variant'], $line['quantity'], $from, $inFlight, $documentType, $documentType, $documentId, $actor);
            }
        }
    }

    /**
     * Settle each line as counted by the receiver. What arrived goes to the receiver; every missing
     * unit must be explained (broken, lost, stolen…) or it is recorded as missing. Returns the lines
     * whose tagged asset arrived, for the caller to re-home.
     *
     * @param  Collection<int, MovementLine>  $lines
     * @param  array<int, numeric-string|float|int>  $counts  line id → quantity received (default: all)
     * @param  array<int, list<array{kind: string, quantity: numeric-string|float|int, description?: ?string}>>  $explanations  line id → what happened to the rest
     * @return Collection<int, MovementLine>
     */
    public function settle(Collection $lines, array $counts, array $explanations, string $documentType, int $documentId, ?Holder $to, int $companyId, ?int $responsibleEmployeeId, ?Authenticatable $actor): Collection
    {
        $inFlight = Holder::of($documentType, $documentId);

        foreach ($lines as $line) {
            $sent = BigDecimal::of((string) $line->quantity);
            $received = BigDecimal::of((string) ($counts[$line->id] ?? $line->quantity));

            if ($received->isNegative() || $received->isGreaterThan($sent) || ($line->asset_id && ! in_array((string) $received, ['0', '1', '1.000', '0.000'], true))) {
                throw ValidationException::withMessages(["counts.{$line->id}" => __('assets.errors.count', ['sent' => (string) $sent->strippedOfTrailingZeros()])]);
            }

            $line->forceFill(['received' => (string) $received])->save();
            $short = $sent->minus($received);

            if ($line->variant_id && $received->isPositive() && $to) {
                $this->ledger->move($line->variant, (string) $received, $inFlight, $to, $documentType, $documentType, $documentId, $actor);
            }

            if (! $short->isPositive()) {
                continue;
            }

            $explained = collect($explanations[$line->id] ?? [])->filter(fn (array $row): bool => BigDecimal::of((string) ($row['quantity'] ?? 0))->isPositive());
            $total = $explained->reduce(fn (BigDecimal $sum, array $row) => $sum->plus((string) $row['quantity']), BigDecimal::zero());

            if ($total->isGreaterThan($short)) {
                throw ValidationException::withMessages(["explanations.{$line->id}" => __('assets.errors.explained_too_much', ['short' => (string) $short->strippedOfTrailingZeros()])]);
            }

            if ($total->isLessThan($short)) {
                $explained->push(['kind' => IncidentKind::Missing->value, 'quantity' => (string) $short->minus($total), 'description' => null]);
            }

            foreach ($explained as $row) {
                $this->incidents->handle(
                    ['kind' => $row['kind'], 'quantity' => (string) $row['quantity'], 'description' => $row['description'] ?? null],
                    $line->variant, $line->asset, $inFlight, $documentType, $documentId, $companyId, $responsibleEmployeeId, takeFromHolder: (bool) $line->variant_id, actor: $actor,
                );
            }
        }

        return $lines->filter(fn (MovementLine $line): bool => $line->asset_id !== null && (string) $line->received !== '0.000');
    }

    /** Send everything still travelling back to where it came from (a rejected or cancelled document). */
    public function returnToSender(Collection $lines, string $documentType, int $documentId, Holder $from, ?Authenticatable $actor): void
    {
        foreach ($lines->whereNotNull('variant_id') as $line) {
            $this->ledger->move($line->variant, (string) $line->quantity, Holder::of($documentType, $documentId), $from, $documentType, $documentType, $documentId, $actor);
        }
    }
}
