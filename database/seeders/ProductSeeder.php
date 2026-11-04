<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Baydaq's products for the landing page. Names are placeholders the admin can
     * correct; descriptions are left empty where the owner has not written one yet.
     * Existing rows are left alone so admin edits survive.
     */
    public function run(): void
    {
        foreach ($this->products() as $order => $product) {
            Product::query()->firstOrCreate(['slug' => $product['slug']], [...$product, 'sort_order' => ($order + 1) * 10]);
        }
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function products(): array
    {
        return [
            [
                'slug' => 'baydaq-platform',
                'name_ar' => 'منصة بيدق',
                'name_en' => 'Baydaq Platform',
                'description_ar' => 'منصة واحدة لخدمات البريد ورموز التحقق والرسائل والإشعارات والمدفوعات لتطبيقك.',
                'description_en' => 'One platform for email, OTP, messaging, push and payments for your app.',
            ],
            ['slug' => 'pchome', 'name_ar' => 'Pchome', 'name_en' => 'Pchome', 'description_ar' => null, 'description_en' => null],
            ['slug' => 'narpion', 'name_ar' => 'NARPION', 'name_en' => 'NARPION', 'description_ar' => null, 'description_en' => null],
            [
                'slug' => 'reservation-system',
                'name_ar' => 'نظام الحجوزات',
                'name_en' => 'Reservation System',
                'description_ar' => 'إدارة الحجوزات والمواعيد والتوفر في مكان واحد.',
                'description_en' => 'Manage bookings, appointments and availability in one place.',
            ],
            ['slug' => 'nuzol', 'name_ar' => 'نزل', 'name_en' => 'Nuzol', 'description_ar' => null, 'description_en' => null],
            ['slug' => 'nuzol-umrah', 'name_ar' => 'نزل عمرة', 'name_en' => 'Nuzol Umrah', 'description_ar' => null, 'description_en' => null],
            ['slug' => 'zad', 'name_ar' => 'زاد', 'name_en' => 'Zad', 'description_ar' => null, 'description_en' => null],
            [
                'slug' => 'pos-system',
                'name_ar' => 'نظام نقاط البيع',
                'name_en' => 'POS System',
                'description_ar' => 'نقطة بيع للمتاجر والمطاعم مع المخزون والتقارير.',
                'description_en' => 'Point of sale for shops and restaurants, with stock and reports.',
            ],
            [
                'slug' => 'hr-system',
                'name_ar' => 'نظام الموارد البشرية',
                'name_en' => 'HR System',
                'description_ar' => 'إدارة الموظفين والحضور والإجازات والرواتب.',
                'description_en' => 'Employees, attendance, leave and payroll.',
            ],
            [
                'slug' => 'assets-management',
                'name_ar' => 'نظام إدارة الأصول',
                'name_en' => 'Assets Management System',
                'description_ar' => 'تتبع أصول الشركة وعهدها وصيانتها.',
                'description_en' => 'Track company assets, custody and maintenance.',
            ],
            [
                'slug' => 'zatca-e-invoicing',
                'name_ar' => 'الفوترة الإلكترونية (زاتكا)',
                'name_en' => 'ZATCA E-Invoicing',
                'description_ar' => 'إصدار الفواتير الإلكترونية المتوافقة مع متطلبات هيئة الزكاة والضريبة والجمارك.',
                'description_en' => 'E-invoicing that meets ZATCA requirements.',
            ],
        ];
    }
}
