<?php

return [
    'title' => 'الأصول',
    'features' => ['register' => 'سجل الأصول والعهد', 'maintenance' => 'الصيانة', 'audits' => 'الجرد'],
    'abilities' => ['assign' => 'تسليم العهد واستلامها', 'dispose' => 'إخراج الأصول من الخدمة', 'view_costs' => 'رؤية التكاليف والقيم الدفترية', 'close' => 'إقفال الجرد'],

    'register' => [
        'title' => 'الأصول',
        'new' => 'أصل جديد',
        'saved' => 'تم تسجيل الأصل :tag.',
        'updated' => 'تم تحديث الأصل.',
        'disposed' => 'تم إخراج الأصل من الخدمة.',
        'none' => 'لا توجد أصول بعد.',
        'count' => 'الأصول في الخدمة',
        'assigned' => 'عهد لدى الموظفين',
        'total_cost' => 'إجمالي التكلفة',
        'total_book' => 'القيمة الدفترية اليوم',
        'dispose' => 'إخراج من الخدمة',
        'depreciation' => 'الإهلاك (القسط الثابت)',
        'history' => 'السجل',
        'months' => ':count شهراً',
        'fields' => [
            'tag' => 'الرمز', 'name_ar' => 'الاسم (عربي)', 'name_en' => 'الاسم (إنجليزي)', 'name' => 'الاسم', 'category' => 'الفئة', 'branch' => 'الفرع', 'serial' => 'الرقم التسلسلي',
            'model' => 'الطراز', 'purchase_date' => 'تاريخ الشراء', 'cost' => 'التكلفة', 'salvage_value' => 'القيمة المتبقية', 'useful_life_months' => 'العمر الإنتاجي (بالأشهر، فارغ = حسب الفئة)',
            'notes' => 'ملاحظات', 'status' => 'الحالة', 'holder' => 'لدى', 'book_value' => 'القيمة الدفترية', 'year' => 'السنة', 'date' => 'التاريخ',
            'as' => 'نوع الإخراج', 'on' => 'التاريخ', 'amount' => 'المبلغ المستلم', 'reason' => 'السبب', 'life' => 'العمر الإنتاجي',
        ],
    ],

    'custody' => [
        'title' => 'العهدة',
        'assign' => 'تسليم عهدة',
        'return' => 'استلام العهدة',
        'assigned' => 'تم تسليم العهدة.',
        'returned' => 'تم استلام العهدة.',
        'none' => 'لم تُسلَّم من قبل.',
        'fields' => ['employee' => 'الموظف', 'assigned_on' => 'تاريخ التسليم', 'returned_on' => 'تاريخ الاستلام', 'condition' => 'الحالة', 'note' => 'ملاحظة', 'condition_out' => 'الحالة عند التسليم', 'condition_in' => 'الحالة عند الاستلام'],
    ],

    'maintenance' => [
        'title' => 'الصيانة',
        'open' => 'أمر صيانة جديد',
        'close' => 'إقفال أمر الصيانة',
        'opened' => 'تم فتح أمر الصيانة.',
        'closed' => 'تم إقفال أمر الصيانة.',
        'none' => 'لا توجد صيانة بعد.',
        'fields' => ['kind' => 'النوع', 'description' => 'العطل أو الخدمة', 'opened_on' => 'تاريخ الفتح', 'closed_on' => 'تاريخ الإقفال', 'vendor' => 'المورّد', 'cost' => 'التكلفة', 'resolution' => 'الحل', 'reported_by' => 'أبلغ عنه'],
    ],

    'categories' => [
        'title' => 'فئات الأصول',
        'new' => 'فئة جديدة',
        'saved' => 'تم حفظ الفئة.',
        'none' => 'لا توجد فئات بعد.',
        'fields' => ['name_ar' => 'الاسم (عربي)', 'name_en' => 'الاسم (إنجليزي)', 'useful_life_months' => 'العمر الإنتاجي الافتراضي (بالأشهر)', 'depreciates' => 'تُهلك', 'is_active' => 'نشطة'],
    ],

    'audits' => [
        'title' => 'الجرد',
        'new' => 'بدء جرد',
        'closed' => 'تم إقفال الجرد.',
        'none' => 'لا توجد عمليات جرد بعد.',
        'scan' => 'امسح الرمز أو اكتبه',
        'scan_submit' => 'تأشير',
        'close' => 'إقفال الجرد',
        'mark_lost' => 'إخراج المفقود من الخدمة كمفقود',
        'lost_reason' => 'مفقود في جرد :date',
        'open' => 'قيد التنفيذ',
        'fields' => ['branch' => 'الفرع', 'started_on' => 'البدء', 'closed_at' => 'الإقفال', 'items' => 'المتوقع', 'found' => 'الموجود', 'missing' => 'المفقود', 'unexpected' => 'غير متوقع', 'note' => 'ملاحظة'],
    ],

    'errors' => [
        'branch_company' => 'اختر فرعاً من شركة الأصل.',
        'employee' => 'اختر موظفاً نشطاً من شركة الأصل.',
        'not_available' => 'يمكن تسليم الأصل المتاح فقط.',
        'already_returned' => 'تم استلام هذه العهدة بالفعل.',
        'return_date' => 'لا يمكن أن يسبق تاريخ الاستلام تاريخ التسليم.',
        'not_serviceable' => 'الأصل في الصيانة أو خارج الخدمة بالفعل.',
        'already_closed' => 'أمر الصيانة مقفل بالفعل.',
        'not_disposable' => 'الأصل خارج الخدمة بالفعل.',
        'in_use' => 'استلم الأصل وأقفل صيانته قبل إخراجه من الخدمة.',
        'audit_open' => 'يوجد جرد قيد التنفيذ في هذا الفرع.',
        'audit_closed' => 'هذا الجرد مقفل.',
        'unknown_tag' => 'لا يوجد أصل لهذه الشركة بهذا الرمز.',
        'holds_assets' => 'لا تزال لديه :count عهدة: :assets. استلمها أولاً.',
    ],

    'events' => ['registered' => 'سُجّل', 'updated' => 'عُدّل', 'moved' => 'نُقل إلى فرع آخر', 'assigned' => 'سُلّم كعهدة', 'returned' => 'استُلم', 'maintenance_opened' => 'فُتح أمر صيانة', 'maintenance_closed' => 'أُقفل أمر صيانة', 'disposed' => 'أُخرج من الخدمة', 'lost' => 'سُجّل كمفقود'],
    'asset_status' => ['available' => 'متاح', 'assigned' => 'عهدة', 'maintenance' => 'في الصيانة', 'lost' => 'مفقود', 'disposed' => 'خارج الخدمة'],
    'maintenance_kind' => ['repair' => 'إصلاح', 'preventive' => 'صيانة دورية'],
    'audit_item_status' => ['expected' => 'لم يُمسح بعد', 'found' => 'موجود', 'missing' => 'مفقود', 'unexpected' => 'غير متوقع هنا'],
];
