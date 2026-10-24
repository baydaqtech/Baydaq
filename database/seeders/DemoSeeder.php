<?php

namespace Database\Seeders;

use App\Actions\Services\IssueApiKey;
use App\Actions\Usage\RecordUsage;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectResource;
use App\Models\ProjectService;
use App\Models\Service;
use App\Models\User;
use App\Modules\Assets\Actions\ManageCustody;
use App\Modules\Assets\Actions\SaveAsset;
use App\Modules\Assets\Models\Asset;
use App\Modules\Assets\Models\Category as AssetCategory;
use App\Modules\Hr\Actions\Catalog\SaveJob;
use App\Modules\Hr\Actions\Catalog\SaveSection;
use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Actions\Employees\LinkEmployeeUser;
use App\Modules\Hr\Actions\Employees\SaveEmployee;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Models\AllowanceType;
use App\Modules\Hr\Models\BonusType;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\DeductionType;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Section;
use App\Modules\Invoicing\Models\Customer;
use App\Modules\Platform\Actions\Access\AssignAccess;
use App\Modules\Platform\Actions\Suite\ProvisionSuite;
use App\Modules\Platform\Actions\Suite\SaveBrand;
use App\Modules\Platform\Actions\Suite\SaveCompany;
use App\Modules\Platform\Actions\Suite\SaveWorkLocation;
use App\Modules\Platform\Enums\Module;
use App\Modules\Platform\Enums\ScopeLevel;
use App\Modules\Platform\Enums\SuiteFeature;
use App\Modules\Platform\Models\Access\AccessRole;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Brand;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use App\Modules\Platform\Models\Subscription;
use App\Modules\Platform\Models\WorkLocation;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Pos\Actions\ManageShift;
use App\Modules\Pos\Actions\SaveCashier;
use App\Modules\Pos\Actions\SaveProduct;
use App\Modules\Pos\Models\Cashier;
use App\Modules\Pos\Models\Category;
use App\Modules\Pos\Models\ModifierGroup;
use App\Modules\Pos\Models\Product;
use App\Modules\Pos\Models\Register;
use App\Modules\Reservations\Actions\BookAppointment;
use App\Modules\Reservations\Actions\FindSlots;
use App\Modules\Reservations\Actions\SaveService;
use App\Modules\Reservations\Enums\BookingChannel;
use App\Modules\Reservations\Models\BranchSettings;
use App\Modules\Reservations\Models\Resource as ReservationResource;
use App\Modules\Reservations\Models\Service as ReservationService;
use App\Modules\Subscriptions\Actions\SaveMember;
use App\Modules\Subscriptions\Actions\SellMembership;
use App\Modules\Subscriptions\Enums\PaymentMeans as SubscriptionPaymentMeans;
use App\Modules\Subscriptions\Models\Member;
use App\Modules\Subscriptions\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Sample clients, projects and invoices for local development only.
     * Sign in as client@baydaq.test / password to see the portal and the suite as owner,
     * manager@baydaq.test / password to see the suite limited to one company, or
     * employee@baydaq.test / password in the mobile app.
     */
    public function run(): void
    {
        if (User::query()->where('email', 'client@baydaq.test')->exists()) {
            return;
        }

        $client = Client::factory()->create(['name_ar' => 'شركة المثال', 'name_en' => 'Example Co.']);
        User::factory()->client($client)->create(['name' => 'Demo Client', 'email' => 'client@baydaq.test']);

        $services = Service::query()->listed()->get();

        $projects = [
            Project::factory()->partnership()->for($client)->create(['name_ar' => 'تطبيق التوصيل', 'name_en' => 'Delivery App', 'slug' => 'delivery-app', 'progress' => 60]),
            Project::factory()->product()->live()->for($client)->create(['name_ar' => 'نظام الحجوزات', 'name_en' => 'Bookings', 'slug' => 'bookings']),
            Project::factory()->clientOwned()->live()->for($client)->create(['name_ar' => 'متجر المثال', 'name_en' => 'Example Store', 'slug' => 'example-store']),
        ];

        foreach ($projects as $project) {
            Milestone::factory()->done()->for($project)->create(['title' => 'Discovery', 'sort_order' => 1]);
            Milestone::factory()->for($project)->create(['title' => 'First release', 'sort_order' => 2]);
            ProjectResource::factory()->count(2)->for($project)->create();
            ProjectFile::factory()->count(3)->for($project)->create();

            foreach ($services->random(min(2, $services->count())) as $service) {
                ProjectService::factory()->for($project)->for($service)->create(['pricing_model' => $service->pricing_model, 'price' => $service->default_price ?? '149.00']);
            }
        }

        foreach ([InvoiceStatus::Paid, InvoiceStatus::Sent, InvoiceStatus::Overdue] as $status) {
            $invoice = Invoice::factory()->{$status->value}()->for($client)->for($projects[0])->create();
            InvoiceItem::factory()->for($invoice)->create([
                'description' => 'Development work',
                'quantity' => 1,
                'unit_price' => $invoice->subtotal,
                'total' => $invoice->subtotal,
            ]);
        }

        ClientRequest::factory()->count(2)->create();
        ClientRequest::factory()->forServices()->create();

        // Connected services with a month of usage, so the portal meters have something to show.
        foreach (ProjectService::query()->whereHas('project', fn ($query) => $query->where('client_id', $client->id))->get() as $projectService) {
            app(IssueApiKey::class)->handle($projectService);

            for ($day = 1; $day <= min(now()->day, 28); $day++) {
                app(RecordUsage::class)->handle($projectService, random_int(20, 400), now()->startOfMonth()->addDays($day - 1)->toDateString());
            }
        }

        $this->seedSuite($client);
    }

    /**
     * The business suite for the demo client: two companies with branches, an HR subscription,
     * and a company manager limited to the second company.
     */
    private function seedSuite(Client $client): void
    {
        $owner = $client->users()->firstOrFail();
        app(ProvisionSuite::class)->handle($client, [$owner]);

        app(CurrentTenant::class)->run($client, function () use ($client): void {
            $organization = Organization::query()->firstOrFail();
            $first = $organization->companies()->firstOrFail();

            foreach ([['RUH', 'الرياض', 'Riyadh'], ['JED', 'جدة', 'Jeddah']] as [$code, $ar, $en]) {
                Branch::factory()->for($first)->create(['client_id' => $client->id, 'code' => $code, 'name_ar' => "فرع {$ar}", 'name_en' => "{$en} Branch", 'city_ar' => $ar, 'city_en' => $en]);
            }

            $second = app(SaveCompany::class)->handle(
                $organization->companies()->make(),
                ['name_ar' => 'مطاعم المثال', 'name_en' => 'Example Restaurants', 'timezone' => 'Asia/Riyadh', 'status' => 'active'],
            );
            Branch::factory()->for($second)->create(['client_id' => $client->id, 'code' => 'DMM', 'name_ar' => 'فرع الدمام', 'name_en' => 'Dammam Branch', 'city_ar' => 'الدمام', 'city_en' => 'Dammam']);

            Subscription::factory()->seats(50)->create(['client_id' => $client->id]);
            Subscription::factory()->module(Module::Invoicing)->create(['client_id' => $client->id, 'quantity' => 2, 'unit_price' => '1200.00']);

            $first->forceFill(['vat_number' => '300000000000003', 'cr_number' => '1010101010', 'street' => 'طريق الملك فهد', 'building_number' => '1234', 'district' => 'العليا', 'city' => 'الرياض', 'postal_code' => '12211'])->save();
            foreach ([['شركة الأفق للتجارة', 'Horizon Trading Co', '311111111111113'], ['مؤسسة النخبة', 'Elite Est.', null]] as [$ar, $en, $vat]) {
                (new Customer(['name_ar' => $ar, 'name_en' => $en, 'vat_number' => $vat, 'street' => 'شارع التحلية', 'building_number' => '4321', 'district' => 'السليمانية', 'city' => 'الرياض', 'postal_code' => '12233']))->forceFill(['client_id' => $client->id])->save();
            }

            $role = AccessRole::factory()->granting([
                SuiteFeature::Structure->key('view'), SuiteFeature::Structure->key('update'),
                SuiteFeature::Access->key('view'),
                HrFeature::Employees->key('view'), HrFeature::Employees->key('create'), HrFeature::Employees->key('update'),
                HrFeature::Contracts->key('view'), HrFeature::Documents->key('view'), HrFeature::Documents->key('create'),
                HrFeature::Movements->key('view'), HrFeature::Movements->key('create'), HrFeature::Reports->key('view'), HrFeature::Catalog->key('view'),
            ])->create(['client_id' => $client->id, 'name_ar' => 'مدير شركة', 'name_en' => 'Company Manager']);

            $manager = User::factory()->create(['client_id' => $client->id, 'name' => 'Demo Manager', 'email' => 'manager@baydaq.test']);
            app(AssignAccess::class)->handle($manager, $role, ScopeLevel::Company, $second, actor: null, guard: false);

            $this->seedHr($client, $first, $second);
            $this->seedPos($client, $second);
            $this->seedReservations($client, $first);
            $this->seedSubscriptions($client, $first);
            $this->seedAssets($client, $first);
        });
    }

    /**
     * Meeting rooms and a staffed consultation at the Riyadh branch, bookable online, with one booking.
     */
    private function seedReservations(Client $client, Company $first): void
    {
        Subscription::factory()->module(Module::Reservations)->create(['client_id' => $client->id, 'quantity' => 1, 'unit_price' => '600.00']);
        $ruh = Branch::query()->where('code', 'RUH')->firstOrFail();
        $rooms = collect([['قاعة اجتماعات أ', 'Meeting room A', 8], ['قاعة اجتماعات ب', 'Meeting room B', 4]])->map(function (array $room) use ($client, $first, $ruh): ReservationResource {
            $resource = (new ReservationResource(['name_ar' => $room[0], 'name_en' => $room[1], 'kind' => 'room', 'capacity' => $room[2]]))->forceFill(['client_id' => $client->id, 'company_id' => $first->id, 'branch_id' => $ruh->id]);
            $resource->save();

            return $resource;
        });

        $room = app(SaveService::class)->handle((new ReservationService)->forceFill(['client_id' => $client->id]), ['company_id' => $first->id, 'name_ar' => 'حجز قاعة (ساعة)', 'name_en' => 'Meeting room (1 hour)', 'duration_minutes' => 60, 'price' => '150.00', 'deposit' => '50.00', 'color' => '#0f766e'], $rooms->pluck('id')->all());
        app(SaveService::class)->handle((new ReservationService)->forceFill(['client_id' => $client->id]), ['company_id' => $first->id, 'name_ar' => 'استشارة مالية', 'name_en' => 'Finance consultation', 'duration_minutes' => 30, 'buffer_minutes' => 10, 'price' => '200.00', 'needs_staff' => true, 'color' => '#7c3aed'], [], Employee::query()->whereIn('employee_number', ['E-002', 'E-003'])->pluck('id')->all());
        BranchSettings::forBranch($ruh->id, $client->id)->forceFill(['is_online' => true, 'min_notice_minutes' => 30])->save();

        $slot = app(FindSlots::class)->handle($room, $ruh->id, now('Asia/Riyadh')->addDay()->toDateString())[2] ?? null;
        if ($slot) {
            app(BookAppointment::class)->handle($room, $ruh->id, $slot['starts_at'], ['customer_name' => 'شركة الأفق', 'customer_phone' => '0551234567', 'party_size' => 6], BookingChannel::Staff);
        }
    }

    /**
     * A club at the head office: monthly, quarterly and 10-visit plans and three members.
     */
    private function seedSubscriptions(Client $client, Company $first): void
    {
        Subscription::factory()->module(Module::Subscriptions)->create(['client_id' => $client->id, 'quantity' => 1, 'unit_price' => '600.00']);
        $plan = fn (array $data): Plan => tap((new Plan(['company_id' => $first->id, ...$data]))->forceFill(['client_id' => $client->id]))->save();
        $monthly = $plan(['name_ar' => 'اشتراك شهري', 'name_en' => 'Monthly', 'price' => '300.00', 'freeze_days' => 7]);
        $quarterly = $plan(['name_ar' => 'اشتراك ربع سنوي', 'name_en' => 'Quarterly', 'period_count' => 3, 'price' => '800.00', 'freeze_days' => 14]);
        $visits = $plan(['name_ar' => '10 زيارات', 'name_en' => '10 visits', 'price' => '250.00', 'sessions' => 10]);

        foreach ([['سعد القحطاني', '0561112233', 'male', $monthly], ['ريم الشهري', '0562223344', 'female', $quarterly], ['فهد الدوسري', '0563334455', 'male', $visits]] as [$name, $phone, $gender, $chosen]) {
            $member = app(SaveMember::class)->handle(new Member, ['name' => $name, 'phone' => $phone, 'gender' => $gender], $first->id);
            app(SellMembership::class)->handle($member, $chosen, SubscriptionPaymentMeans::Card);
        }
    }

    /**
     * Laptops, a delivery van and office furniture, one laptop in an employee's custody.
     */
    private function seedAssets(Client $client, Company $first): void
    {
        Subscription::factory()->module(Module::Assets)->create(['client_id' => $client->id, 'quantity' => 1, 'unit_price' => '900.00']);
        $category = fn (string $ar, string $en, int $months): AssetCategory => tap((new AssetCategory(['name_ar' => $ar, 'name_en' => $en, 'useful_life_months' => $months]))->forceFill(['client_id' => $client->id]))->save();
        $laptops = $category('حواسيب محمولة', 'Laptops', 36);
        $vehicles = $category('مركبات', 'Vehicles', 60);
        $furniture = $category('أثاث مكتبي', 'Office furniture', 84);
        $jed = Branch::query()->where('code', 'JED')->firstOrFail();
        $hq = $first->headOffice()->firstOrFail();

        $laptop = app(SaveAsset::class)->handle(new Asset, ['category_id' => $laptops->id, 'name_ar' => 'ماك بوك برو 14', 'name_en' => 'MacBook Pro 14', 'serial' => 'C02XK1', 'purchase_date' => now()->subMonths(8)->toDateString(), 'cost' => '9800.00', 'salvage_value' => '800.00'], $jed->id);
        app(SaveAsset::class)->handle(new Asset, ['category_id' => $laptops->id, 'name_ar' => 'لينوفو ثينك باد', 'name_en' => 'Lenovo ThinkPad', 'serial' => 'PF3ZK9', 'purchase_date' => now()->subMonths(20)->toDateString(), 'cost' => '5200.00'], $hq->id);
        app(SaveAsset::class)->handle(new Asset, ['category_id' => $vehicles->id, 'name_ar' => 'فان توصيل', 'name_en' => 'Delivery van', 'serial' => 'JTD1234', 'purchase_date' => now()->subYear()->toDateString(), 'cost' => '95000.00', 'salvage_value' => '25000.00'], $hq->id);
        app(SaveAsset::class)->handle(new Asset, ['category_id' => $furniture->id, 'name_ar' => 'طاولة اجتماعات', 'name_en' => 'Meeting table', 'purchase_date' => now()->subMonths(30)->toDateString(), 'cost' => '4500.00'], $hq->id);

        app(ManageCustody::class)->assign($laptop, Employee::query()->where('employee_number', 'E-004')->value('id'), now()->subMonths(7)->toDateString(), 'جديد');
    }

    /**
     * A burger brand at the restaurants company: menu with SFDA labels, a till with an open shift
     * (cashier PIN 1234) and a QR menu link.
     */
    private function seedPos(Client $client, Company $restaurants): void
    {
        Subscription::factory()->module(Module::Pos)->create(['client_id' => $client->id, 'quantity' => 1, 'unit_price' => '900.00']);
        $branch = $restaurants->branches()->where('code', 'DMM')->firstOrFail();
        $brand = app(SaveBrand::class)->handle(new Brand, ['company_id' => $restaurants->id, 'name_ar' => 'برجر المثال', 'name_en' => 'Example Burger', 'color' => '#b45309', 'is_active' => true], [$branch->id]);

        $category = fn (string $ar, string $en, int $order): Category => tap((new Category(['company_id' => $restaurants->id, 'brand_id' => $brand->id, 'name_ar' => $ar, 'name_en' => $en, 'sort_order' => $order]))->forceFill(['client_id' => $client->id]))->save();
        $burgers = $category('البرجر', 'Burgers', 1);
        $drinks = $category('المشروبات', 'Drinks', 2);

        $size = tap((new ModifierGroup(['company_id' => $restaurants->id, 'name_ar' => 'الحجم', 'name_en' => 'Size', 'min' => 1, 'max' => 1]))->forceFill(['client_id' => $client->id]))->save();
        $size->modifiers()->createMany([['name_ar' => 'عادي', 'name_en' => 'Regular', 'price' => '0', 'sort_order' => 0], ['name_ar' => 'كبير', 'name_en' => 'Large', 'price' => '4.00', 'sort_order' => 1]]);
        $extras = tap((new ModifierGroup(['company_id' => $restaurants->id, 'name_ar' => 'إضافات', 'name_en' => 'Extras', 'min' => 0, 'max' => 3]))->forceFill(['client_id' => $client->id]))->save();
        $extras->modifiers()->createMany([['name_ar' => 'جبنة إضافية', 'name_en' => 'Extra cheese', 'price' => '3.00', 'sort_order' => 0], ['name_ar' => 'هالبينو', 'name_en' => 'Jalapeño', 'price' => '2.00', 'sort_order' => 1]]);

        foreach ([
            [$burgers, 'برجر كلاسيك', 'Classic Burger', '28.00', 650, true, null, ['gluten', 'milk'], [$extras->id]],
            [$burgers, 'برجر دجاج', 'Chicken Burger', '26.00', 540, true, null, ['gluten', 'egg'], [$extras->id]],
            [$burgers, 'بطاطس', 'Fries', '9.00', 320, true, null, [], [$size->id]],
            [$drinks, 'كولا', 'Cola', '6.00', 140, false, 34, [], [$size->id]],
            [$drinks, 'لاتيه', 'Latte', '17.25', 180, false, 120, ['milk'], [$size->id]],
        ] as $index => [$in, $ar, $en, $price, $calories, $salty, $caffeine, $allergens, $groups]) {
            app(SaveProduct::class)->handle(new Product, ['company_id' => $restaurants->id, 'category_id' => $in->id, 'name_ar' => $ar, 'name_en' => $en, 'price' => $price, 'calories' => $calories, 'high_salt' => $salty, 'caffeine_mg' => $caffeine, 'allergens' => $allergens, 'sort_order' => $index], $groups);
        }

        $register = (new Register(['name' => 'كاشير 1']))->forceFill(['client_id' => $client->id, 'company_id' => $restaurants->id, 'branch_id' => $branch->id, 'menu_token' => Str::random(32)]);
        $register->save();
        $cashier = app(SaveCashier::class)->handle((new Cashier)->forceFill(['client_id' => $client->id]), ['company_id' => $restaurants->id, 'name' => 'Demo Cashier'], '1234');
        app(ManageShift::class)->open($register, $cashier, '1234', '200.00');
    }

    /**
     * Sections, jobs, allowance and document types, and a handful of employees across both companies.
     */
    private function seedHr(Client $client, Company $first, Company $second): void
    {
        $sections = collect([['الإدارة', 'Management'], ['المحاسبة', 'Accounting'], ['التقنية', 'IT'], ['العمليات', 'Operations']])
            ->mapWithKeys(fn (array $name) => [$name[1] => app(SaveSection::class)->handle((new Section)->forceFill(['client_id' => $client->id]), ['name_ar' => $name[0], 'name_en' => $name[1]])]);

        $jobs = collect([
            ['الرئيس التنفيذي', 'CEO', ['Management']],
            ['مدير فرع', 'Branch Manager', ['Management', 'Operations']],
            ['محاسب', 'Accountant', ['Accounting']],
            ['مطوّر', 'Developer', ['IT']],
            ['كاشير', 'Cashier', ['Operations']],
        ])->mapWithKeys(fn (array $job) => [$job[1] => app(SaveJob::class)->handle((new Job)->forceFill(['client_id' => $client->id]), ['name_ar' => $job[0], 'name_en' => $job[1]], $sections->only($job[2])->pluck('id')->all())]);

        $housing = AllowanceType::factory()->create(['client_id' => $client->id]);
        $transport = AllowanceType::factory()->fixed('500.00')->create(['client_id' => $client->id]);
        foreach ([['أداء', 'Performance'], ['عيد', 'Eid'], ['إنجاز مشروع', 'Project delivery']] as [$ar, $en]) {
            (new BonusType(['name_ar' => $ar, 'name_en' => $en]))->forceFill(['client_id' => $client->id])->save();
        }
        foreach ([['غياب', 'Absence'], ['تأخير', 'Lateness'], ['مخالفة', 'Violation'], ['أضرار', 'Damage']] as [$ar, $en]) {
            (new DeductionType(['name_ar' => $ar, 'name_en' => $en]))->forceFill(['client_id' => $client->id])->save();
        }
        (new LeaveType(['name_ar' => 'سنوية', 'name_en' => 'Annual', 'days_per_year' => '21']))->forceFill(['client_id' => $client->id])->save();
        (new LeaveType(['name_ar' => 'مرضية', 'name_en' => 'Sick', 'days_per_year' => '30']))->forceFill(['client_id' => $client->id])->save();
        (new LeaveType(['name_ar' => 'بدون راتب', 'name_en' => 'Unpaid', 'days_per_year' => '0', 'is_paid' => false, 'needs_balance' => false]))->forceFill(['client_id' => $client->id])->save();
        (new LeaveType(['name_ar' => 'حج', 'name_en' => 'Hajj', 'days_per_year' => '0', 'needs_balance' => false]))->forceFill(['client_id' => $client->id])->save();
        DocumentType::factory()->create(['client_id' => $client->id]);
        DocumentType::factory()->create(['client_id' => $client->id, 'name_ar' => 'جواز سفر', 'name_en' => 'Passport', 'fields' => []]);
        DocumentType::factory()->create(['client_id' => $client->id, 'name_ar' => 'تأمين طبي', 'name_en' => 'Medical insurance', 'alert_days' => [30], 'fields' => [['key' => 'provider', 'label_ar' => 'شركة التأمين', 'label_en' => 'Provider', 'type' => 'text', 'required' => true, 'options' => []]]]);

        $branches = Branch::query()->get()->keyBy('code');
        $people = [
            ['E-001', 'عبدالله الراشد', 'Abdullah Alrashed', $first, $first->headOffice()->value('id'), 'Management', 'CEO', '18000', null],
            ['E-002', 'نورة العتيبي', 'Noura Alotaibi', $first, $branches['RUH']->id, 'Management', 'Branch Manager', '11000', 'E-001'],
            ['E-003', 'محمد حسن', 'Mohamed Hassan', $first, $branches['RUH']->id, 'Accounting', 'Accountant', '7000', 'E-002'],
            ['E-004', 'سلمى يوسف', 'Salma Yousef', $first, $branches['JED']->id, 'IT', 'Developer', '9000', 'E-001'],
            ['E-101', 'خالد المطيري', 'Khalid Almutairi', $second, $second->headOffice()->value('id'), 'Management', 'Branch Manager', '10000', null],
            ['E-102', 'راجيش كومار', 'Rajesh Kumar', $second, $branches['DMM']->id, 'Operations', 'Cashier', '4500', 'E-101'],
        ];
        $created = [];

        foreach ($people as [$number, $ar, $en, $company, $branchId, $section, $job, $salary, $managerNumber]) {
            $employee = app(SaveEmployee::class)->handle(new Employee, [
                'company_id' => $company->id, 'branch_id' => $branchId, 'status' => 'active',
                'employee_number' => $number, 'name_ar' => $ar, 'name_en' => $en,
                'section_id' => $sections[$section]->id, 'job_id' => $jobs[$job]->id,
                'manager_id' => $managerNumber ? $created[$managerNumber]->id : null,
                'id_type' => 'iqama', 'national_id' => '2'.str_pad((string) crc32($number), 9, '0', STR_PAD_LEFT), 'nationality' => 'SA',
                'hired_on' => now()->subMonths(crc32($number) % 36)->toDateString(),
            ]);
            app(SaveContract::class)->handle($employee, new Contract, ['type' => 'open_ended', 'status' => 'active', 'starts_on' => $employee->hired_on->toDateString(), 'basic_salary' => $salary], [
                ['allowance_type_id' => $housing->id, 'calculation' => 'percent_of_basic', 'value' => '25'],
                ['allowance_type_id' => $transport->id, 'calculation' => 'fixed', 'value' => '500'],
            ]);
            $created[$number] = $employee;
        }

        // A geofence per branch (Riyadh HQ + RUH share one building) and a login for the mobile demo:
        // employee@baydaq.test / password, bound to whichever phone signs in first.
        $hq = $first->headOffice()->value('id');
        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $client->id]), ['name_ar' => 'برج العليا', 'name_en' => 'Olaya Tower', 'latitude' => 24.6950, 'longitude' => 46.6850, 'radius_m' => 120], [$hq, $branches['RUH']->id]);
        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $client->id]), ['name_ar' => 'مكتب جدة', 'name_en' => 'Jeddah office', 'latitude' => 21.5430, 'longitude' => 39.1730, 'radius_m' => 100], [$branches['JED']->id]);
        app(SaveWorkLocation::class)->handle((new WorkLocation)->forceFill(['client_id' => $client->id]), ['name_ar' => 'مطعم الدمام', 'name_en' => 'Dammam restaurant', 'latitude' => 26.4207, 'longitude' => 50.0888, 'radius_m' => 80], [$branches['DMM']->id]);

        app(LinkEmployeeUser::class)->handle($created['E-003'], 'employee@baydaq.test');
        User::query()->where('email', 'employee@baydaq.test')->update(['password' => bcrypt('password'), 'must_change_password' => false]);
    }
}
