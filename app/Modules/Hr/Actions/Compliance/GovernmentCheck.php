<?php

namespace App\Modules\Hr\Actions\Compliance;

use App\Modules\Hr\Actions\Pay\DailyWage;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\BlindIndex;
use App\Modules\Platform\Access\BranchSet;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Compare the wages a government system holds (an export from GOSI, Qiwa or Mudad) with
 * Baydaq's contracts (H5). A wage that differs can freeze the company's government services,
 * so mismatches are listed first. GOSI holds the registrable wage (basic + GOSI allowances);
 * Qiwa and Mudad hold the full contract wage.
 */
class GovernmentCheck
{
    public const SOURCES = ['gosi', 'qiwa', 'mudad'];

    /** Header names accepted for the two columns that matter. */
    private const ID_HEADERS = ['national_id', 'id', 'id_number', 'iqama', 'identity', 'رقم الهوية', 'رقم الاقامة', 'رقم الإقامة', 'الهوية'];

    private const WAGE_HEADERS = ['wage', 'salary', 'basic_wage', 'total_wage', 'contributory_wage', 'الأجر', 'الراتب', 'الاجر', 'الأجر الخاضع'];

    public function __construct(private DailyWage $wage) {}

    /**
     * @return array{mismatched: list<array<string, mixed>>, missing_here: list<array<string, mixed>>, missing_there: list<array<string, mixed>>, matched: int}
     */
    public function handle(string $source, UploadedFile $file, BranchSet $reach): array
    {
        $rows = $this->read($file);
        $employees = Employee::query()->visibleIn($reach)->whereIn('status', EmployeeStatus::seated())->get()->keyBy('national_id_hash');
        $seen = [];
        $result = ['mismatched' => [], 'missing_here' => [], 'missing_there' => [], 'matched' => 0];

        foreach ($rows as [$nationalId, $theirs]) {
            $hash = BlindIndex::of($nationalId);
            $employee = $hash ? $employees->get($hash) : null;

            if ($employee === null) {
                $result['missing_here'][] = ['national_id' => '****'.substr($nationalId, -4), 'wage' => $theirs];

                continue;
            }

            $seen[$employee->id] = true;
            $ours = $this->ourWage($source, $employee);

            if (BigDecimal::of($ours)->isEqualTo($theirs)) {
                $result['matched']++;
            } else {
                $result['mismatched'][] = ['id' => $employee->id, 'name' => $employee->localized('name'), 'ours' => $ours, 'theirs' => $theirs, 'difference' => (string) BigDecimal::of($theirs)->minus($ours)];
            }
        }

        foreach ($employees as $employee) {
            if (! isset($seen[$employee->id])) {
                $result['missing_there'][] = ['id' => $employee->id, 'name' => $employee->localized('name'), 'ours' => $this->ourWage($source, $employee)];
            }
        }

        return $result;
    }

    private function ourWage(string $source, Employee $employee): string
    {
        $contract = $this->wage->contractOf($employee);

        if ($contract === null) {
            return '0.00';
        }

        if ($source !== 'gosi') {
            return (string) $this->wage->monthly($contract);
        }

        $contract->loadMissing('allowances.type');

        return (string) BigDecimal::of((string) $contract->basic_salary)
            ->plus($contract->allowances->filter(fn ($line) => $line->type?->counts_for_gosi)->reduce(fn (BigDecimal $sum, $line) => $sum->plus((string) $line->amount), BigDecimal::zero()))
            ->toScale(2);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn ($cell): string => mb_strtolower(trim(preg_replace('/^\x{FEFF}/u', '', (string) $cell))), fgetcsv($handle) ?: []);
        $idColumn = $this->column($header, self::ID_HEADERS);
        $wageColumn = $this->column($header, self::WAGE_HEADERS);

        if ($idColumn === null || $wageColumn === null) {
            fclose($handle);

            throw ValidationException::withMessages(['file' => __('hr.compliance.errors.columns')]);
        }

        $rows = [];

        while (($line = fgetcsv($handle)) !== false && count($rows) < 20000) {
            $nationalId = preg_replace('/\D/', '', (string) ($line[$idColumn] ?? ''));
            $wage = str_replace([',', ' '], '', (string) ($line[$wageColumn] ?? ''));

            if ($nationalId !== '' && is_numeric($wage)) {
                $rows[] = [$nationalId, (string) BigDecimal::of($wage)->toScale(2)];
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $names
     */
    private function column(array $header, array $names): ?int
    {
        foreach ($header as $index => $cell) {
            if (in_array($cell, $names, true)) {
                return $index;
            }
        }

        return null;
    }
}
