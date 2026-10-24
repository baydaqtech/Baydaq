<?php

return [
    'title' => 'Assets',
    'features' => ['register' => 'Asset register and custody', 'maintenance' => 'Maintenance', 'audits' => 'Stock-takes'],
    'abilities' => ['assign' => 'Hand out and take back custody', 'dispose' => 'Write off assets', 'view_costs' => 'See costs and book values', 'close' => 'Close stock-takes'],

    'register' => [
        'title' => 'Assets',
        'new' => 'New asset',
        'saved' => 'Asset :tag registered.',
        'updated' => 'Asset updated.',
        'disposed' => 'Asset written off.',
        'none' => 'No assets yet.',
        'count' => 'Assets in service',
        'assigned' => 'Held by employees',
        'total_cost' => 'Total cost',
        'total_book' => 'Book value today',
        'dispose' => 'Write off',
        'depreciation' => 'Depreciation (straight line)',
        'history' => 'History',
        'months' => ':count months',
        'fields' => [
            'tag' => 'Tag', 'name_ar' => 'Name (Arabic)', 'name_en' => 'Name (English)', 'name' => 'Name', 'category' => 'Category', 'branch' => 'Branch', 'serial' => 'Serial number',
            'model' => 'Model', 'purchase_date' => 'Purchase date', 'cost' => 'Cost', 'salvage_value' => 'Salvage value', 'useful_life_months' => 'Useful life (months, empty = category)',
            'notes' => 'Notes', 'status' => 'Status', 'holder' => 'Held by', 'book_value' => 'Book value', 'year' => 'Year', 'date' => 'Date',
            'as' => 'Write off as', 'on' => 'Date', 'amount' => 'Amount received', 'reason' => 'Reason', 'life' => 'Useful life',
        ],
    ],

    'custody' => [
        'title' => 'Custody',
        'assign' => 'Hand out',
        'return' => 'Take back',
        'assigned' => 'Custody handed out.',
        'returned' => 'Custody returned.',
        'none' => 'Never handed out.',
        'fields' => ['employee' => 'Employee', 'assigned_on' => 'Handed out', 'returned_on' => 'Returned', 'condition' => 'Condition', 'note' => 'Note', 'condition_out' => 'Condition out', 'condition_in' => 'Condition in'],
    ],

    'maintenance' => [
        'title' => 'Maintenance',
        'open' => 'New job',
        'close' => 'Close job',
        'opened' => 'Maintenance job opened.',
        'closed' => 'Maintenance job closed.',
        'none' => 'No maintenance yet.',
        'fields' => ['kind' => 'Kind', 'description' => 'Problem or service', 'opened_on' => 'Opened', 'closed_on' => 'Closed', 'vendor' => 'Vendor', 'cost' => 'Cost', 'resolution' => 'Resolution', 'reported_by' => 'Reported by'],
    ],

    'categories' => [
        'title' => 'Asset categories',
        'new' => 'New category',
        'saved' => 'Category saved.',
        'none' => 'No categories yet.',
        'fields' => ['name_ar' => 'Name (Arabic)', 'name_en' => 'Name (English)', 'useful_life_months' => 'Default useful life (months)', 'depreciates' => 'Depreciates', 'is_active' => 'Active'],
    ],

    'audits' => [
        'title' => 'Stock-takes',
        'new' => 'Start a stock-take',
        'closed' => 'Stock-take closed.',
        'none' => 'No stock-takes yet.',
        'scan' => 'Scan or type a tag',
        'scan_submit' => 'Tick off',
        'close' => 'Close stock-take',
        'mark_lost' => 'Write off what is missing as lost',
        'lost_reason' => 'Missing in the stock-take of :date',
        'open' => 'In progress',
        'fields' => ['branch' => 'Branch', 'started_on' => 'Started', 'closed_at' => 'Closed', 'items' => 'Expected', 'found' => 'Found', 'missing' => 'Missing', 'unexpected' => 'Unexpected', 'note' => 'Note'],
    ],

    'errors' => [
        'branch_company' => 'Choose a branch of the asset\'s company.',
        'employee' => 'Choose an active employee of the asset\'s company.',
        'not_available' => 'Only an available asset can be handed out.',
        'already_returned' => 'This custody was already returned.',
        'return_date' => 'The return date cannot be before the hand-out date.',
        'not_serviceable' => 'This asset is already in maintenance or written off.',
        'already_closed' => 'This job is already closed.',
        'not_disposable' => 'This asset is already written off.',
        'in_use' => 'Take the asset back and close its maintenance before writing it off.',
        'audit_open' => 'A stock-take is already in progress at this branch.',
        'audit_closed' => 'This stock-take is closed.',
        'unknown_tag' => 'No asset of this company has that tag.',
        'holds_assets' => 'Still holds :count asset(s): :assets. Take them back first.',
    ],

    'events' => ['registered' => 'Registered', 'updated' => 'Updated', 'moved' => 'Moved to another branch', 'assigned' => 'Handed out', 'returned' => 'Taken back', 'maintenance_opened' => 'Maintenance opened', 'maintenance_closed' => 'Maintenance closed', 'disposed' => 'Written off', 'lost' => 'Recorded as lost'],
    'asset_status' => ['available' => 'Available', 'assigned' => 'In custody', 'maintenance' => 'In maintenance', 'lost' => 'Lost', 'disposed' => 'Written off'],
    'maintenance_kind' => ['repair' => 'Repair', 'preventive' => 'Service'],
    'audit_item_status' => ['expected' => 'Not yet scanned', 'found' => 'Found', 'missing' => 'Missing', 'unexpected' => 'Not expected here'],
];
