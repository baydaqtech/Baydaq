<?php

namespace Database\Seeders;

use App\Enums\PricingModel;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * The services catalog. Existing rows are left alone so admin edits (prices, wording) survive.
     * Prices start empty; the admin sets them.
     */
    public function run(): void
    {
        foreach ($this->catalog() as $order => $service) {
            Service::query()->firstOrCreate(['key' => $service['key']], [...$service, 'sort_order' => ($order + 1) * 10]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            [
                'key' => 'email',
                'name_ar' => 'البريد الإلكتروني',
                'name_en' => 'Email',
                'description_ar' => 'إرسال رسائل البريد التشغيلية لتطبيقك (رموز التحقق، الترحيب، الفواتير، التنبيهات) باسم مشروعك ونطاقه.',
                'description_en' => 'Transactional email for your app (codes, welcome, invoices, alerts) from your own sender name and domain.',
                'icon' => 'mail',
                'pricing_model' => PricingModel::PerUse,
                'unit' => 'email',
            ],
            [
                'key' => 'otp',
                'name_ar' => 'رموز التحقق (OTP)',
                'name_en' => 'OTP',
                'description_ar' => 'إنشاء رموز التحقق لمرة واحدة والتحقق منها عبر الرسائل النصية أو البريد.',
                'description_en' => 'Generate and verify one-time codes for sign-in and verification by SMS or email.',
                'icon' => 'key-round',
                'pricing_model' => PricingModel::PerUse,
                'unit' => 'otp',
            ],
            [
                'key' => 'sms',
                'name_ar' => 'الرسائل النصية',
                'name_en' => 'SMS',
                'description_ar' => 'إرسال رسائل نصية داخل المملكة باسم مرسل معتمد.',
                'description_en' => 'Send SMS inside Saudi Arabia from a registered sender name.',
                'icon' => 'message-square',
                'pricing_model' => PricingModel::PerUse,
                'unit' => 'message',
            ],
            [
                'key' => 'whatsapp',
                'name_ar' => 'واتساب للأعمال',
                'name_en' => 'WhatsApp',
                'description_ar' => 'إرسال رسائل القوالب المعتمدة ورموز التحقق عبر واتساب للأعمال.',
                'description_en' => 'Send approved template messages and codes through the WhatsApp Business API.',
                'icon' => 'message-circle',
                'pricing_model' => PricingModel::PerUse,
                'unit' => 'message',
            ],
            [
                'key' => 'push',
                'name_ar' => 'الإشعارات الفورية',
                'name_en' => 'Push notifications',
                'description_ar' => 'إرسال إشعارات لمستخدمي تطبيقك على iOS وAndroid والويب.',
                'description_en' => 'Push notifications to your app users on iOS, Android and the web.',
                'icon' => 'bell',
                'pricing_model' => PricingModel::Monthly,
                'unit' => null,
            ],
            [
                'key' => 'payments',
                'name_ar' => 'المدفوعات',
                'name_en' => 'Payments',
                'description_ar' => 'استقبال المدفوعات بمدى وفيزا وماستركارد وApple Pay، وتصل الأموال لحسابك مباشرة.',
                'description_en' => 'Take Mada, Visa, Mastercard and Apple Pay payments, paid straight into your own account.',
                'icon' => 'credit-card',
                'pricing_model' => PricingModel::Monthly,
                'unit' => null,
            ],
            [
                'key' => 'ai',
                'name_ar' => 'الذكاء الاصطناعي',
                'name_en' => 'AI',
                'description_ar' => 'واجهة محادثة ونصوص بالذكاء الاصطناعي لتطبيقك.',
                'description_en' => 'Chat and text AI API for your app.',
                'icon' => 'sparkles',
                'is_active' => false,
                'pricing_model' => PricingModel::PerUse,
                'unit' => 'request',
            ],
            [
                'key' => 'storage',
                'name_ar' => 'تخزين الملفات',
                'name_en' => 'File storage',
                'description_ar' => 'تخزين ملفات متوافق مع S3.',
                'description_en' => 'S3-compatible file storage.',
                'icon' => 'hard-drive',
                'is_active' => false,
                'pricing_model' => PricingModel::Monthly,
                'unit' => null,
            ],
            [
                'key' => 'hosting',
                'name_ar' => 'الاستضافة والمراقبة',
                'name_en' => 'Hosting and monitoring',
                'description_ar' => 'استضافة تطبيقك ومراقبته على مدار الساعة.',
                'description_en' => 'Hosting for your app with round-the-clock monitoring.',
                'icon' => 'server',
                'is_active' => false,
                'pricing_model' => PricingModel::Monthly,
                'unit' => null,
            ],
            [
                'key' => 'backups',
                'name_ar' => 'النسخ الاحتياطي',
                'name_en' => 'Backups',
                'description_ar' => 'نسخ احتياطية مجدولة لبياناتك.',
                'description_en' => 'Scheduled backups of your data.',
                'icon' => 'database-backup',
                'is_active' => false,
                'pricing_model' => PricingModel::Monthly,
                'unit' => null,
            ],
            [
                'key' => 'maps',
                'name_ar' => 'الخرائط والمواقع',
                'name_en' => 'Maps and geocoding',
                'description_ar' => 'خرائط وتحويل العناوين إلى إحداثيات.',
                'description_en' => 'Maps and address geocoding.',
                'icon' => 'map-pin',
                'is_active' => false,
                'pricing_model' => PricingModel::PerUse,
                'unit' => 'request',
            ],
        ];
    }
}
