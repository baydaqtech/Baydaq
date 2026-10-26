<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Platform\Actions\Suite\ProvisionSuite;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Models\WorkLocation;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;

/**
 * A clean test account to try the suite by hand: one owner, one organization, two companies with
 * branches, every module on, and five employees who can sign in to /me and the mobile app.
 *
 *   php artisan db:seed --class=TestAccountSeeder
 *
 * Every login uses the password below. Safe to run again (it does nothing the second time).
 */
class TestAccountSeeder extends Seeder
{
    public const PASSWORD = 'Baydaq@2026';

    public const OWNER = 'owner@alnoor.test';

    public function run(): void
    {
        if (User::query()->where('email', self::OWNER)->exists()) {
            return;
        }

        $client = Client::factory()->create(['name_ar' => 'مجموعة النور', 'name_en' => 'Al Noor Group']);
        $owner = User::factory()->client($client)->create([
            'name' => 'Faisal Alnoor', 'email' => self::OWNER, 'password' => bcrypt(self::PASSWORD), 'must_change_password' => false,
        ]);
        app(ProvisionSuite::class)->handle($client, [$owner]);

        app(CurrentTenant::class)->run($client, function () use ($client): void {
            $organization = Organization::query()->firstOrFail();
            $organization->forceFill(['name_ar' => 'مجموعة النور القابضة', 'name_en' => 'Al Noor Holding'])->save();

            $cafes = $organization->companies()->firstOrFail();
            $cafes->forceFill([
                'name_ar' => 'مقاهي النور', 'name_en' => 'Al Noor Cafes', 'vat_number' => '310000000000003', 'cr_number' => '1010200300',
                'street' => 'طريق الملك فهد', 'building_number' => '2101', 'district' => 'العليا', 'city' => 'الرياض', 'postal_code' => '12214',
            ])->save();
            $trading = app(SaveCompany::class)->handle($organization->companies()->make(), [
                'name_ar' => 'النور للتجارة', 'name_en' => 'Al Noor Trading', 'timezone' => 'Asia/Riyadh', 'status' => 'active',
            ]);
            $trading->forceFill(['vat_number' => '310000000000013', 'cr_number' => '4030200400'])->save();

            $olaya = $this->branch($client, $cafes, 'OLY', 'العليا', 'Olaya', 'الرياض', 'Riyadh');
            $nakheel = $this->branch($client, $cafes, 'NKH', 'النخيل', 'Al Nakheel', 'الرياض', 'Riyadh');
            $jeddah = $this->branch($client, $trading, 'JED', 'جدة', 'Jeddah', 'جدة', 'Jeddah');

            foreach (Module::cases() as $module) {
                Subscription::factory()->module($module)->create(['client_id' => $client->id, 'quantity' => $module === Module::Hr ? 50 : 5]);
            }

            $this->locations($client, $cafes, $olaya, $nakheel, $jeddah);
            $this->catalog($client);
            $this->employees($client, $cafes, $trading, $olaya, $nakheel, $jeddah);
        });
    }

    private function branch(Client $client, Company $company, string $code, string $ar, string $en, string $cityAr, string $cityEn): Branch
    {
        return Branch::factory()->for($company)->create([
            'client_id' => $client->id, 'code' => $code, 'name_ar' => "فرع {$ar}", 'name_en' => "{$en} Branch", 'city_ar' => $cityAr, 'city_en' => $cityEn,
        ]);
    }

    private function locations(Client $client, Company $cafes, Branch $olaya, Branch $nakheel, Branch $jeddah): void
    {
        $place = fn (string $ar, string $en, float $lat, float $lng, array $branches) => app(SaveWorkLocation::class)->handle(
            (new WorkLocation)->forceFill(['client_id' => $client->id]), ['name_ar' => $ar, 'name_en' => $en, 'latitude' => $lat, 'longitude' => $lng, 'radius_m' => 120], $branches,
        );
        $place('مقر العليا', 'Olaya HQ', 24.6950, 46.6850, [$cafes->headOffice()->value('id'), $olaya->id]);
        $place('مقهى النخيل', 'Nakheel cafe', 24.7690, 46.6340, [$nakheel->id]);
        $place('مستودع جدة', 'Jeddah warehouse', 21.5430, 39.1730, [$jeddah->id]);
    }

    private function catalog(Client $client): void
    {
        $owned = fn ($model) => $model->forceFill(['client_id' => $client->id])->save();
        $owned(new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21']));
        $owned(new LeaveType(['name_ar' => 'مرضية', 'name_en' => 'Sick', 'days_per_year' => '30']));
        $owned(new LeaveType(['name_ar' => 'بدون راتب', 'name_en' => 'Unpaid', 'days_per_year' => '0', 'is_paid' => false, 'needs_balance' => false]));
        $owned(new DeductionType(['name_ar' => 'تأخير', 'name_en' => 'Lateness']));
        $owned(new DeductionType(['name_ar' => 'جزاء', 'name_en' => 'Penalty']));
        AllowanceType::factory()->create(['client_id' => $client->id]); // housing, 25% of basic
    }

    private function employees(Client $client, Company $cafes, Company $trading, Branch $olaya, Branch $nakheel, Branch $jeddah): void
    {
        $housing = AllowanceType::query()->firstOrFail();
        $people = [
            // number, email, Arabic name, English name, company, branch, nationality, basic, mode, remote days
            ['N-001', 'manager@alnoor.test', 'سارة القحطاني', 'Sara Alqahtani', $cafes, $cafes->headOffice()->value('id'), 'SA', '14000', 'hybrid', [0, 1]],
            ['N-002', 'barista1@alnoor.test', 'أحمد الزهراني', 'Ahmed Alzahrani', $cafes, $olaya->id, 'SA', '6000', 'onsite', []],
            ['N-003', 'barista2@alnoor.test', 'ماريا سانتوس', 'Maria Santos', $cafes, $nakheel->id, 'PH', '4200', 'onsite', []],
            ['N-004', 'accountant@alnoor.test', 'عمر فاروق', 'Omar Farooq', $trading, $trading->headOffice()->value('id'), 'PK', '8500', 'remote', []],
            ['N-005', 'storekeeper@alnoor.test', 'ريم الشهري', 'Reem Alshehri', $trading, $jeddah->id, 'SA', '7000', 'onsite', []],
        ];

        foreach ($people as $index => [$number, $email, $ar, $en, $company, $branchId, $nationality, $basic, $mode, $remoteDays]) {
            $employee = app(SaveEmployee::class)->handle(new Employee, [
                'company_id' => $company->id, 'branch_id' => $branchId, 'status' => 'active', 'employee_number' => $number,
                'name_ar' => $ar, 'name_en' => $en, 'email' => $email, 'phone' => '05000000'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'id_type' => $nationality === 'SA' ? 'national_id' : 'iqama', 'national_id' => ($nationality === 'SA' ? '1' : '2').str_pad((string) (900000000 + $index), 9, '0', STR_PAD_LEFT),
                'nationality' => $nationality, 'gender' => in_array($number, ['N-001', 'N-003', 'N-005'], true) ? 'female' : 'male',
                'hired_on' => now()->subMonths(6 + $index * 5)->toDateString(),
                'gosi_registered_on' => $nationality === 'SA' ? now()->subMonths(6 + $index * 5)->toDateString() : null,
            ]);
            $employee->forceFill(['work_mode' => $mode, 'remote_days' => $mode === 'hybrid' ? $remoteDays : null])->save();
            app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => $employee->hired_on->toDateString(), 'basic_salary' => $basic], [
                ['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25'],
            ]);
            app(LinkEmployeeUser::class)->handle($employee, $email);
            User::query()->where('email', $email)->update(['password' => bcrypt(self::PASSWORD), 'must_change_password' => false]);
        }
    }
}
