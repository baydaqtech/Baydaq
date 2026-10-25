# Baydaq ER diagram

> Generated from the database schema by `php artisan docs:er`. Do not edit by hand: change a
> migration, run `php artisan migrate` and `php artisan docs:er`. The test
> `tests/Feature/Docs/ErDiagramTest.php` fails while this file is out of date.
>
> Solid lines are database foreign keys. Dotted lines are links by convention (ids that point
> across modules, which the modular design keeps without a foreign key). `client_id` (the tenant)
> is on almost every table and is not drawn.

## Tables per module

- Platform (kernel: tenants, structure, access, packages, customers): 23 tables
- HR: 48 tables
- Point of sale: 12 tables
- Invoicing (ZATCA): 5 tables
- Reservations: 6 tables
- Subscriptions (memberships): 8 tables
- Assets and inventory: 18 tables
- Finance (ledger, purchasing): 8 tables
- Core app (users, clients, portal, permissions): 25 tables

## Platform (kernel: tenants, structure, access, packages, customers)

```mermaid
erDiagram
    platform_access_assignments {
        bigint id PK
        bigint client_id FK
        bigint user_id FK
        bigint access_role_id FK
        varchar level
        bigint organization_id FK
        bigint company_id FK
        bigint branch_id FK
        varchar scope_key
        timestamp created_at
        timestamp updated_at
    }
    platform_access_role_permissions {
        bigint id PK
        bigint access_role_id FK
        varchar permission
    }
    platform_access_roles {
        bigint id PK
        bigint client_id FK
        varchar key
        varchar name_ar
        varchar name_en
        tinyint is_locked
        timestamp created_at
        timestamp updated_at
    }
    platform_addons {
        bigint id PK
        varchar code
        varchar name_ar
        varchar name_en
        decimal price_monthly
        json modules
        json features
        json mobile
        json limits
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    platform_audit_events {
        bigint id PK
        bigint client_id FK
        bigint actor_id FK
        varchar auditable_type
        bigint auditable_id
        varchar event
        json changes
        varchar ip
        timestamp created_at
    }
    platform_branches {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        varchar code
        varchar name_ar
        varchar name_en
        varchar city_ar
        varchar city_en
        tinyint is_head_office
        bigint head_office_company_id
        varchar status
        timestamp created_at
        timestamp updated_at
        char join_token
    }
    platform_brand_branch {
        bigint brand_id FK
        bigint branch_id FK
    }
    platform_brands {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        varchar slug
        varchar name_ar
        varchar name_en
        varchar logo
        varchar color
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    platform_client_plans {
        bigint id PK
        bigint client_id FK
        bigint package_id FK
        json addons
        json overrides
        varchar status
        varchar billing_cycle
        date starts_on
        date ends_on
        timestamp created_at
        timestamp updated_at
    }
    platform_companies {
        bigint id PK
        bigint client_id FK
        bigint organization_id FK
        varchar slug
        varchar name_ar
        varchar name_en
        varchar cr_number
        varchar vat_number
        varchar unified_number
        varchar gosi_number
        varchar qiwa_id
        varchar street
        varchar building_number
        varchar district
        varchar city
        varchar postal_code
        varchar timezone
        decimal loyalty_percent
        varchar client_scope
        varchar status
        timestamp created_at
        timestamp updated_at
    }
    platform_customer_registrations {
        bigint id PK
        bigint client_id FK
        bigint customer_id FK
        varchar scope_type
        bigint scope_id
        bigint branch_id
        varchar source
        bigint registered_by FK
        timestamp marketing_consent_at
        timestamp created_at
        timestamp updated_at
    }
    platform_customers {
        bigint id PK
        bigint client_id FK
        varchar name
        varchar phone
        varchar email
        date birth_date
        varchar gender
        decimal wallet_balance
        text notes
        timestamp created_at
        timestamp updated_at
        timestamp erased_at
    }
    platform_messages {
        bigint id PK
        bigint client_id FK
        varchar channel
        varchar to
        varchar template
        json params
        text body
        varchar locale
        varchar context
        varchar status
        varchar provider_id
        varchar error
        timestamp created_at
        timestamp updated_at
    }
    platform_organizations {
        bigint id PK
        bigint client_id FK
        varchar slug
        varchar name_ar
        varchar name_en
        varchar status
        timestamp created_at
        timestamp updated_at
    }
    platform_packages {
        bigint id PK
        varchar code
        varchar name_ar
        varchar name_en
        text description_ar
        text description_en
        decimal price_monthly
        decimal price_yearly
        smallint trial_days
        json modules
        json features
        json mobile
        json limits
        tinyint is_public
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    platform_payments {
        bigint id PK
        bigint client_id FK
        varchar token
        varchar reference
        decimal amount
        char currency
        varchar description
        varchar provider
        varchar provider_id
        varchar checkout_url
        varchar status
        varchar return_url
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
    }
    platform_privacy_requests {
        bigint id PK
        bigint client_id FK
        varchar kind
        varchar subject
        bigint customer_id
        varchar name
        varchar contact
        date received_on
        date due_on
        varchar status
        text details
        text outcome
        bigint handled_by FK
        timestamp closed_at
        timestamp created_at
        timestamp updated_at
    }
    platform_scope_shortcuts {
        bigint id PK
        bigint client_id FK
        bigint user_id FK
        varchar segment
        varchar branches
        varchar name
        tinyint pinned
        timestamp last_used_at
        timestamp created_at
        timestamp updated_at
    }
    platform_subscriptions {
        bigint id PK
        bigint client_id FK
        varchar module
        varchar status
        varchar billing_unit
        int quantity
        decimal unit_price
        date starts_on
        date ends_on
        text notes
        timestamp created_at
        timestamp updated_at
    }
    platform_themes {
        bigint id PK
        bigint client_id FK
        varchar app_name
        varchar logo_path
        char primary_color
        char accent_color
        varchar radius
        varchar mode
        timestamp created_at
        timestamp updated_at
    }
    platform_wallet_entries {
        bigint id PK
        bigint client_id FK
        bigint customer_id FK
        bigint company_id
        decimal amount
        decimal balance_after
        varchar kind
        varchar source
        varchar reference
        varchar note
        bigint user_id FK
        timestamp created_at
    }
    platform_work_location_branch {
        bigint work_location_id FK
        bigint branch_id FK
    }
    platform_work_locations {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        decimal latitude
        decimal longitude
        smallint radius_m
        json wifi_bssids
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    users { }
    platform_access_roles ||--o{ platform_access_assignments : "access_role_id"
    platform_access_roles ||--o{ platform_access_role_permissions : "access_role_id"
    platform_branches ||--o{ platform_access_assignments : "branch_id"
    platform_branches ||--o{ platform_brand_branch : "branch_id"
    platform_branches ||--o{ platform_work_location_branch : "branch_id"
    platform_branches ||..o{ platform_customer_registrations : "branch_id"
    platform_brands ||--o{ platform_brand_branch : "brand_id"
    platform_companies ||--o{ platform_access_assignments : "company_id"
    platform_companies ||--o{ platform_branches : "client_id,company_id"
    platform_companies ||--o{ platform_brands : "client_id,company_id"
    platform_companies ||..o{ platform_wallet_entries : "company_id"
    platform_customers ||--o{ platform_customer_registrations : "customer_id"
    platform_customers ||--o{ platform_wallet_entries : "customer_id"
    platform_organizations ||--o{ platform_access_assignments : "organization_id"
    platform_organizations ||--o{ platform_companies : "client_id,organization_id"
    platform_packages ||--o{ platform_client_plans : "package_id"
    platform_work_locations ||--o{ platform_work_location_branch : "work_location_id"
    users ||--o{ platform_access_assignments : "user_id"
    users ||--o{ platform_audit_events : "actor_id"
    users ||--o{ platform_customer_registrations : "registered_by"
    users ||--o{ platform_privacy_requests : "handled_by"
    users ||--o{ platform_scope_shortcuts : "user_id"
    users ||--o{ platform_wallet_entries : "user_id"
```

## HR

```mermaid
erDiagram
    hr_advance_installments {
        bigint id PK
        bigint advance_id FK
        date due_month
        decimal amount
        decimal paid
        varchar status
        bigint payroll_line_id
    }
    hr_advance_repayments {
        bigint id PK
        bigint advance_id FK
        bigint installment_id FK
        decimal amount
        varchar method
        date paid_on
        varchar reference
        bigint recorded_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_advances {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        decimal amount
        smallint installments
        date first_installment_month
        varchar status
        varchar disbursement
        date disbursed_on
        varchar reason
        bigint requested_by FK
        bigint approved_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_allowance_types {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        varchar calculation
        decimal default_value
        tinyint counts_for_gosi
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_attendance_devices {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar device_id
        varchar platform
        varchar model
        timestamp bound_at
        timestamp created_at
        timestamp updated_at
    }
    hr_attendance_punches {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar type
        timestamp punched_at
        date work_date
        decimal latitude
        decimal longitude
        smallint accuracy_m
        tinyint mocked
        bigint work_location_id FK
        bigint branch_id FK
        bigint home_branch_id
        varchar flag
        varchar resolution
        varchar resolution_note
        bigint resolved_by FK
        timestamp resolved_at
        varchar source
        varchar device_id
        varchar selfie_path
        timestamp created_at
        timestamp updated_at
        tinyint is_remote
    }
    hr_bonus_types {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_bonuses {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint bonus_type_id FK
        decimal amount
        varchar status
        date pay_month
        varchar reason
        bigint payroll_line_id
        bigint requested_by FK
        bigint approved_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_breaks {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint branch_id
        bigint employee_id FK
        varchar kind
        varchar status
        smallint minutes
        date work_date
        timestamp requested_at
        timestamp starts_at
        timestamp ends_at
        bigint decided_by FK
        varchar note
        timestamp created_at
        timestamp updated_at
    }
    hr_candidates {
        bigint id PK
        bigint client_id FK
        bigint opening_id FK
        varchar name
        varchar phone
        varchar email
        varchar nationality
        varchar cv_path
        varchar stage
        tinyint rating
        text notes
        varchar source
        timestamp interview_at
        decimal offer_salary
        bigint employee_id FK
        timestamp consent_at
        timestamp created_at
        timestamp updated_at
    }
    hr_change_requests {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar kind
        bigint contract_id FK
        text payload
        json summary
        varchar status
        bigint requested_by FK
        bigint decided_by FK
        timestamp decided_at
        timestamp created_at
        timestamp updated_at
    }
    hr_contract_allowances {
        bigint id PK
        bigint contract_id FK
        bigint allowance_type_id FK
        varchar calculation
        decimal value
        decimal amount
    }
    hr_contracts {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar type
        varchar status
        date starts_on
        date ends_on
        smallint probation_days
        decimal basic_salary
        tinyint qiwa_authenticated
        text notes
        timestamp created_at
        timestamp updated_at
        varchar qiwa_status
        varchar qiwa_reference
        date qiwa_documented_on
    }
    hr_deduction_types {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_deductions {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint deduction_type_id FK
        decimal amount
        varchar status
        date pay_month
        varchar reason
        varchar origin
        bigint payroll_line_id
        bigint requested_by FK
        bigint approved_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_document_renewals {
        bigint id PK
        bigint client_id FK
        bigint employee_document_id FK
        date expires_on
        decimal cost
        varchar status
        bigint done_by FK
        timestamp done_at
        timestamp created_at
        timestamp updated_at
    }
    hr_document_types {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        tinyint has_number
        tinyint has_expiry
        json alert_days
        decimal renewal_cost
        json fields
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_employee_document_files {
        bigint id PK
        bigint document_id FK
        varchar disk
        varchar path
        varchar name
        varchar mime
        int size
        timestamp created_at
        timestamp updated_at
    }
    hr_employee_documents {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint document_type_id FK
        text number
        date issued_on
        date expires_on
        json data
        date next_alert_on
        json alerted
        text notes
        timestamp created_at
        timestamp updated_at
    }
    hr_employee_movements {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar type
        varchar status
        bigint from_branch_id FK
        bigint to_company_id FK
        bigint to_branch_id FK
        date starts_on
        date ends_on
        varchar reason
        bigint requested_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_employee_placements {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint movement_id FK
        bigint company_id FK
        bigint branch_id FK
        varchar kind
        date starts_on
        date ends_on
        timestamp created_at
        timestamp updated_at
    }
    hr_employees {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        bigint section_id FK
        bigint job_id FK
        bigint manager_id FK
        bigint user_id FK
        varchar pin_hash
        varchar employee_number
        varchar name_ar
        varchar name_en
        varchar email
        varchar phone
        varchar id_type
        text national_id
        char national_id_hash
        char nationality
        varchar gender
        date birth_date
        date hired_on
        date gosi_registered_on
        date terminated_on
        varchar status
        text iban
        text notes
        timestamp created_at
        timestamp updated_at
        varchar work_mode
        json remote_days
    }
    hr_expense_claims {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint company_id
        bigint branch_id
        date spent_on
        varchar category
        decimal amount
        decimal vat
        varchar merchant
        varchar merchant_vat_number
        varchar description
        varchar receipt_path
        varchar status
        varchar decision_note
        bigint decided_by FK
        timestamp decided_at
        date paid_on
        timestamp created_at
        timestamp updated_at
    }
    hr_final_settlements {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar reason
        date last_day
        decimal eosb_award
        decimal advances_recovered
        decimal advances_left
        decimal net_payable
        bigint settled_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_holidays {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        varchar kind
        date starts_on
        date ends_on
        timestamp created_at
        timestamp updated_at
    }
    hr_job_company_blocks {
        bigint job_id FK
        bigint company_id FK
    }
    hr_job_openings {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint branch_id
        bigint job_id FK
        varchar title_ar
        varchar title_en
        text description
        smallint positions
        varchar status
        date closes_on
        char token
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_job_section {
        bigint job_id FK
        bigint section_id FK
    }
    hr_jobs {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        varchar name_ar_key
        varchar name_en_key
        bigint default_role_id FK
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_leave_adjustments {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint leave_type_id FK
        decimal days
        date effective_on
        varchar reason
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_leave_requests {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        bigint leave_type_id FK
        date starts_on
        date ends_on
        decimal days
        varchar status
        varchar reason
        varchar decision_note
        bigint requested_by FK
        bigint decided_by FK
        timestamp decided_at
        timestamp created_at
        timestamp updated_at
    }
    hr_leave_types {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        decimal days_per_year
        decimal senior_days_per_year
        tinyint senior_after_years
        varchar accrual
        varchar year_basis
        decimal carry_over_days
        tinyint allow_negative
        tinyint is_paid
        tinyint needs_balance
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_letters {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint employee_id FK
        varchar kind
        char language
        varchar addressed_to
        varchar reference
        varchar token
        json snapshot
        bigint issued_by FK
        timestamp issued_at
        timestamp revoked_at
        timestamp created_at
        timestamp updated_at
    }
    hr_payroll_lines {
        bigint id PK
        bigint payroll_run_id FK
        bigint employee_id FK
        bigint branch_id
        decimal basic
        decimal allowances
        decimal bonuses
        decimal deductions
        decimal advance_installments
        decimal unpaid_leave
        decimal gosi_employee
        decimal gosi_employer
        decimal gross
        decimal net
        tinyint working_days
        decimal unpaid_leave_days
        json breakdown
        timestamp created_at
        timestamp updated_at
    }
    hr_payroll_runs {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        date pay_month
        varchar status
        decimal total_gross
        decimal total_deductions
        decimal total_net
        decimal total_employer_gosi
        int employees_count
        bigint approved_by FK
        timestamp approved_at
        date paid_on
        timestamp created_at
        timestamp updated_at
    }
    hr_recharges {
        bigint id PK
        bigint client_id FK
        bigint movement_id FK
        date month
        bigint from_company_id
        bigint to_company_id
        smallint days
        decimal amount
        bigint invoice_id
        timestamp created_at
        timestamp updated_at
    }
    hr_remote_requests {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        date work_date
        varchar reason
        varchar status
        bigint decided_by FK
        timestamp decided_at
        timestamp created_at
        timestamp updated_at
    }
    hr_review_cycles {
        bigint id PK
        bigint client_id FK
        bigint company_id
        varchar name
        date period_start
        date period_end
        date due_on
        varchar status
        json criteria
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_reviews {
        bigint id PK
        bigint client_id FK
        bigint cycle_id FK
        bigint employee_id FK
        json self_scores
        text self_comment
        timestamp self_submitted_at
        json manager_scores
        text manager_comment
        text goals
        decimal overall
        bigint reviewed_by FK
        timestamp reviewed_at
        timestamp created_at
        timestamp updated_at
    }
    hr_section_company_blocks {
        bigint section_id FK
        bigint company_id FK
    }
    hr_sections {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        varchar name_ar_key
        varchar name_en_key
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    hr_separations {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar kind
        varchar status
        varchar source
        date submitted_on
        date last_day
        date deemed_accept_on
        tinyint deemed
        text reason
        json checklist
        bigint submitted_by FK
        bigint decided_by FK
        timestamp decided_at
        timestamp created_at
        timestamp updated_at
    }
    hr_settings {
        bigint id PK
        bigint client_id FK
        varchar key
        json value
        timestamp created_at
        timestamp updated_at
    }
    hr_shifts {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        bigint employee_id FK
        bigint job_id FK
        bigint movement_id FK
        date shift_date
        time starts_at
        time ends_at
        time break_at
        smallint break_minutes
        smallint minutes
        tinyint is_open
        tinyint on_holiday
        tinyint ramadan_hours
        varchar notes
        timestamp created_at
        timestamp updated_at
    }
    hr_task_comments {
        bigint id PK
        bigint client_id FK
        bigint task_id FK
        bigint user_id FK
        text body
        timestamp created_at
    }
    hr_tasks {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint branch_id
        bigint assignee_id FK
        bigint created_by FK
        varchar title
        text description
        varchar priority
        varchar status
        json checklist
        tinyint needs_photo
        varchar photo_path
        timestamp due_at
        timestamp started_at
        timestamp completed_at
        timestamp created_at
        timestamp updated_at
    }
    hr_travel_visas {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar kind
        varchar number
        date issued_on
        date expires_on
        smallint duration_days
        date departed_on
        date returned_on
        varchar status
        text notes
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_violations {
        bigint id PK
        bigint client_id FK
        bigint employee_id FK
        varchar category
        date occurred_on
        smallint minutes
        tinyint occurrence
        text description
        varchar penalty
        decimal deduction_days
        bigint deduction_id FK
        varchar status
        text employee_response
        timestamp notified_at
        timestamp responded_at
        varchar source
        bigint recorded_by FK
        timestamp created_at
        timestamp updated_at
    }
    users { }
    platform_branches { }
    platform_work_locations { }
    platform_companies { }
    platform_access_roles { }
    hr_advance_installments ||--o{ hr_advance_repayments : "installment_id"
    hr_advances ||--o{ hr_advance_installments : "advance_id"
    hr_advances ||--o{ hr_advance_repayments : "advance_id"
    hr_allowance_types ||--o{ hr_contract_allowances : "allowance_type_id"
    hr_bonus_types ||--o{ hr_bonuses : "bonus_type_id"
    hr_contracts ||--o{ hr_change_requests : "contract_id"
    hr_contracts ||--o{ hr_contract_allowances : "contract_id"
    hr_deduction_types ||--o{ hr_deductions : "deduction_type_id"
    hr_deductions ||--o{ hr_violations : "deduction_id"
    hr_document_types ||--o{ hr_employee_documents : "document_type_id"
    hr_employee_documents ||--o{ hr_document_renewals : "employee_document_id"
    hr_employee_documents ||--o{ hr_employee_document_files : "document_id"
    hr_employee_movements ||--o{ hr_employee_placements : "movement_id"
    hr_employee_movements ||--o{ hr_recharges : "movement_id"
    hr_employee_movements ||--o{ hr_shifts : "movement_id"
    hr_employees ||--o{ hr_advances : "employee_id"
    hr_employees ||--o{ hr_attendance_devices : "employee_id"
    hr_employees ||--o{ hr_attendance_punches : "employee_id"
    hr_employees ||--o{ hr_bonuses : "employee_id"
    hr_employees ||--o{ hr_breaks : "employee_id"
    hr_employees ||--o{ hr_candidates : "employee_id"
    hr_employees ||--o{ hr_change_requests : "employee_id"
    hr_employees ||--o{ hr_contracts : "employee_id"
    hr_employees ||--o{ hr_deductions : "employee_id"
    hr_employees ||--o{ hr_employee_documents : "employee_id"
    hr_employees ||--o{ hr_employee_movements : "employee_id"
    hr_employees ||--o{ hr_employee_placements : "employee_id"
    hr_employees ||--o{ hr_employees : "manager_id"
    hr_employees ||--o{ hr_expense_claims : "employee_id"
    hr_employees ||--o{ hr_final_settlements : "employee_id"
    hr_employees ||--o{ hr_leave_adjustments : "employee_id"
    hr_employees ||--o{ hr_leave_requests : "employee_id"
    hr_employees ||--o{ hr_letters : "employee_id"
    hr_employees ||--o{ hr_payroll_lines : "employee_id"
    hr_employees ||--o{ hr_remote_requests : "employee_id"
    hr_employees ||--o{ hr_reviews : "employee_id"
    hr_employees ||--o{ hr_separations : "employee_id"
    hr_employees ||--o{ hr_shifts : "employee_id"
    hr_employees ||--o{ hr_tasks : "assignee_id"
    hr_employees ||--o{ hr_travel_visas : "employee_id"
    hr_employees ||--o{ hr_violations : "employee_id"
    hr_job_openings ||--o{ hr_candidates : "opening_id"
    hr_jobs ||--o{ hr_employees : "job_id"
    hr_jobs ||--o{ hr_job_company_blocks : "job_id"
    hr_jobs ||--o{ hr_job_openings : "job_id"
    hr_jobs ||--o{ hr_job_section : "job_id"
    hr_jobs ||--o{ hr_shifts : "job_id"
    hr_leave_types ||--o{ hr_leave_adjustments : "leave_type_id"
    hr_leave_types ||--o{ hr_leave_requests : "leave_type_id"
    hr_payroll_runs ||--o{ hr_payroll_lines : "payroll_run_id"
    hr_review_cycles ||--o{ hr_reviews : "cycle_id"
    hr_sections ||--o{ hr_employees : "section_id"
    hr_sections ||--o{ hr_job_section : "section_id"
    hr_sections ||--o{ hr_section_company_blocks : "section_id"
    hr_tasks ||--o{ hr_task_comments : "task_id"
    platform_access_roles ||--o{ hr_jobs : "default_role_id"
    platform_branches ||--o{ hr_attendance_punches : "branch_id"
    platform_branches ||--o{ hr_employee_movements : "from_branch_id"
    platform_branches ||--o{ hr_employee_movements : "to_company_id,to_branch_id"
    platform_branches ||--o{ hr_employee_placements : "company_id,branch_id"
    platform_branches ||--o{ hr_employees : "company_id,branch_id"
    platform_branches ||--o{ hr_shifts : "company_id,branch_id"
    platform_branches ||..o{ hr_attendance_punches : "home_branch_id"
    platform_branches ||..o{ hr_breaks : "branch_id"
    platform_branches ||..o{ hr_expense_claims : "branch_id"
    platform_branches ||..o{ hr_job_openings : "branch_id"
    platform_branches ||..o{ hr_payroll_lines : "branch_id"
    platform_branches ||..o{ hr_tasks : "branch_id"
    platform_companies ||--o{ hr_employees : "client_id,company_id"
    platform_companies ||--o{ hr_job_company_blocks : "company_id"
    platform_companies ||--o{ hr_payroll_runs : "client_id,company_id"
    platform_companies ||--o{ hr_section_company_blocks : "company_id"
    platform_companies ||--o{ hr_shifts : "client_id,company_id"
    platform_companies ||..o{ hr_breaks : "company_id"
    platform_companies ||..o{ hr_expense_claims : "company_id"
    platform_companies ||..o{ hr_job_openings : "company_id"
    platform_companies ||..o{ hr_letters : "company_id"
    platform_companies ||..o{ hr_review_cycles : "company_id"
    platform_companies ||..o{ hr_tasks : "company_id"
    platform_work_locations ||--o{ hr_attendance_punches : "work_location_id"
    users ||--o{ hr_advance_repayments : "recorded_by"
    users ||--o{ hr_advances : "approved_by"
    users ||--o{ hr_advances : "requested_by"
    users ||--o{ hr_attendance_punches : "resolved_by"
    users ||--o{ hr_bonuses : "approved_by"
    users ||--o{ hr_bonuses : "requested_by"
    users ||--o{ hr_breaks : "decided_by"
    users ||--o{ hr_change_requests : "decided_by"
    users ||--o{ hr_change_requests : "requested_by"
    users ||--o{ hr_deductions : "approved_by"
    users ||--o{ hr_deductions : "requested_by"
    users ||--o{ hr_document_renewals : "done_by"
    users ||--o{ hr_employee_movements : "requested_by"
    users ||--o{ hr_employees : "user_id"
    users ||--o{ hr_expense_claims : "decided_by"
    users ||--o{ hr_final_settlements : "settled_by"
    users ||--o{ hr_job_openings : "created_by"
    users ||--o{ hr_leave_adjustments : "created_by"
    users ||--o{ hr_leave_requests : "decided_by"
    users ||--o{ hr_leave_requests : "requested_by"
    users ||--o{ hr_letters : "issued_by"
    users ||--o{ hr_payroll_runs : "approved_by"
    users ||--o{ hr_remote_requests : "decided_by"
    users ||--o{ hr_review_cycles : "created_by"
    users ||--o{ hr_reviews : "reviewed_by"
    users ||--o{ hr_separations : "decided_by"
    users ||--o{ hr_separations : "submitted_by"
    users ||--o{ hr_task_comments : "user_id"
    users ||--o{ hr_tasks : "created_by"
    users ||--o{ hr_travel_visas : "created_by"
    users ||--o{ hr_violations : "recorded_by"
```

## Point of sale

```mermaid
erDiagram
    pos_branch_products {
        bigint product_id FK
        bigint branch_id FK
        decimal price
        tinyint is_available
    }
    pos_cashiers {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint employee_id
        varchar name
        varchar pin_hash
        decimal commission_rate
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    pos_categories {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint brand_id FK
        varchar name_ar
        varchar name_en
        varchar color
        smallint sort_order
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    pos_modifier_groups {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        varchar name_ar
        varchar name_en
        tinyint min
        tinyint max
        timestamp created_at
        timestamp updated_at
    }
    pos_modifiers {
        bigint id PK
        bigint group_id FK
        varchar name_ar
        varchar name_en
        decimal price
        smallint sort_order
        tinyint is_active
    }
    pos_order_lines {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        varchar name
        decimal quantity
        decimal unit_price
        decimal vat_rate
        decimal net
        decimal vat
        decimal total
        json modifiers
        varchar note
    }
    pos_orders {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        bigint brand_id
        bigint register_id FK
        bigint shift_id FK
        bigint cashier_id FK
        bigint staff_employee_id
        int number
        date order_date
        varchar channel
        varchar type
        varchar status
        varchar kitchen_status
        varchar table_no
        varchar customer_name
        varchar customer_phone
        decimal subtotal
        decimal discount
        decimal vat
        decimal total
        bigint invoice_id
        varchar note
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
        bigint platform_customer_id
    }
    pos_payments {
        bigint id PK
        bigint order_id FK
        varchar method
        decimal amount
        varchar reference
        timestamp paid_at
    }
    pos_product_modifier_group {
        bigint product_id FK
        bigint group_id FK
    }
    pos_products {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint category_id FK
        varchar sku
        varchar name_ar
        varchar name_en
        text description_ar
        text description_en
        decimal price
        decimal cost
        decimal vat_rate
        varchar image
        smallint calories
        tinyint high_salt
        decimal salt_g
        smallint caffeine_mg
        json allergens
        tinyint is_active
        tinyint sold_by_weight
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    pos_registers {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        varchar name
        bigint einvoice_unit_id
        bigint asset_id
        tinyint require_check_in
        varchar menu_token
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    pos_shifts {
        bigint id PK
        bigint client_id FK
        bigint register_id FK
        bigint cashier_id FK
        timestamp opened_at
        timestamp closed_at
        decimal opening_cash
        decimal expected_cash
        decimal counted_cash
        decimal difference
        int orders_count
        decimal sales_total
        tinyint shortage_sent
        bigint commission_bonus_id
        timestamp created_at
        timestamp updated_at
    }
    platform_branches { }
    platform_companies { }
    platform_brands { }
    platform_customers { }
    platform_branches ||--o{ pos_branch_products : "branch_id"
    platform_branches ||--o{ pos_orders : "company_id,branch_id"
    platform_branches ||--o{ pos_registers : "company_id,branch_id"
    platform_brands ||--o{ pos_categories : "brand_id"
    platform_companies ||--o{ pos_categories : "client_id,company_id"
    platform_companies ||--o{ pos_modifier_groups : "client_id,company_id"
    platform_companies ||--o{ pos_products : "client_id,company_id"
    platform_companies ||..o{ pos_cashiers : "company_id"
    platform_customers ||..o{ pos_orders : "platform_customer_id"
    pos_cashiers ||--o{ pos_orders : "cashier_id"
    pos_cashiers ||--o{ pos_shifts : "cashier_id"
    pos_categories ||--o{ pos_products : "category_id"
    pos_modifier_groups ||--o{ pos_modifiers : "group_id"
    pos_modifier_groups ||--o{ pos_product_modifier_group : "group_id"
    pos_orders ||--o{ pos_order_lines : "order_id"
    pos_orders ||--o{ pos_payments : "order_id"
    pos_products ||--o{ pos_branch_products : "product_id"
    pos_products ||--o{ pos_order_lines : "product_id"
    pos_products ||--o{ pos_product_modifier_group : "product_id"
    pos_registers ||--o{ pos_orders : "register_id"
    pos_registers ||--o{ pos_shifts : "register_id"
    pos_shifts ||--o{ pos_orders : "shift_id"
```

## Invoicing (ZATCA)

```mermaid
erDiagram
    inv_customers {
        bigint id PK
        bigint client_id FK
        bigint company_id
        varchar name_ar
        varchar name_en
        varchar vat_number
        varchar cr_number
        varchar email
        varchar phone
        varchar street
        varchar building_number
        varchar district
        varchar city
        varchar postal_code
        char country
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    inv_einvoice_units {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id
        varchar name
        varchar serial
        varchar environment
        varchar status
        text private_key
        text csr
        text compliance_certificate
        text compliance_secret
        varchar compliance_request_id
        text production_certificate
        text production_secret
        int counter
        varchar previous_hash
        json compliance_checks
        timestamp created_at
        timestamp updated_at
    }
    inv_invoice_lines {
        bigint id PK
        bigint invoice_id FK
        varchar description
        decimal quantity
        varchar unit_code
        decimal unit_price
        decimal discount
        decimal vat_rate
        varchar vat_category
        decimal net
        decimal vat
        decimal total
        smallint sort_order
    }
    inv_invoices {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint customer_id FK
        bigint unit_id FK
        char uuid
        varchar number
        varchar type
        varchar kind
        tinyint intercompany
        varchar source
        bigint related_invoice_id FK
        varchar status
        date issue_date
        time issue_time
        date due_date
        date supply_date
        char currency
        varchar payment_means
        decimal subtotal
        decimal discount
        decimal vat
        decimal total
        decimal rounding
        varchar note
        varchar reason
        text qr
        varchar hash
        int counter
        bigint issued_by FK
        timestamp issued_at
        timestamp paid_at
        timestamp created_at
        timestamp updated_at
    }
    inv_submissions {
        bigint id PK
        bigint invoice_id FK
        bigint unit_id FK
        varchar mode
        varchar environment
        varchar status
        int counter
        varchar hash
        longtext xml
        json response
        timestamp submitted_at
        timestamp created_at
        timestamp updated_at
    }
    platform_companies { }
    platform_branches { }
    users { }
    inv_customers ||--o{ inv_invoices : "customer_id"
    inv_einvoice_units ||--o{ inv_invoices : "unit_id"
    inv_einvoice_units ||--o{ inv_submissions : "unit_id"
    inv_invoices ||--o{ inv_invoice_lines : "invoice_id"
    inv_invoices ||--o{ inv_invoices : "related_invoice_id"
    inv_invoices ||--o{ inv_submissions : "invoice_id"
    platform_branches ||..o{ inv_einvoice_units : "branch_id"
    platform_companies ||--o{ inv_einvoice_units : "client_id,company_id"
    platform_companies ||--o{ inv_invoices : "client_id,company_id"
    platform_companies ||..o{ inv_customers : "company_id"
    users ||--o{ inv_invoices : "issued_by"
```

## Reservations

```mermaid
erDiagram
    res_bookings {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        bigint service_id FK
        bigint resource_id FK
        bigint employee_id
        varchar reference
        timestamp starts_at
        timestamp ends_at
        timestamp blocks_until
        varchar status
        varchar channel
        varchar customer_name
        varchar customer_phone
        varchar customer_email
        smallint party_size
        smallint nights
        decimal price
        decimal deposit
        timestamp deposit_paid_at
        timestamp reminded_at
        decimal paid_amount
        bigint invoice_id
        varchar note
        varchar cancel_reason
        bigint created_by
        timestamp created_at
        timestamp updated_at
        bigint platform_customer_id
    }
    res_branch_settings {
        bigint branch_id FK
        bigint client_id FK
        varchar booking_token
        json hours
        smallint slot_minutes
        smallint min_notice_minutes
        smallint max_days_ahead
        tinyint is_online
        timestamp created_at
        timestamp updated_at
    }
    res_resources {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        varchar name_ar
        varchar name_en
        varchar kind
        smallint capacity
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    res_service_resource {
        bigint service_id FK
        bigint resource_id FK
    }
    res_service_staff {
        bigint service_id FK
        bigint employee_id
    }
    res_services {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        varchar name_ar
        varchar name_en
        text description_ar
        text description_en
        smallint duration_minutes
        smallint buffer_minutes
        decimal price
        decimal vat_rate
        decimal deposit
        tinyint needs_staff
        tinyint is_stay
        time check_in_time
        time check_out_time
        tinyint is_online
        tinyint is_active
        varchar color
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    platform_branches { }
    platform_customers { }
    platform_companies { }
    platform_branches ||--o{ res_bookings : "company_id,branch_id"
    platform_branches ||--o{ res_branch_settings : "branch_id"
    platform_branches ||--o{ res_resources : "company_id,branch_id"
    platform_companies ||--o{ res_services : "client_id,company_id"
    platform_customers ||..o{ res_bookings : "platform_customer_id"
    res_resources ||--o{ res_bookings : "resource_id"
    res_resources ||--o{ res_service_resource : "resource_id"
    res_services ||--o{ res_bookings : "service_id"
    res_services ||--o{ res_service_resource : "service_id"
    res_services ||--o{ res_service_staff : "service_id"
```

## Subscriptions (memberships)

```mermaid
erDiagram
    sub_check_ins {
        bigint id PK
        bigint client_id FK
        bigint member_id FK
        bigint membership_id FK
        bigint branch_id FK
        bigint zone_id FK
        timestamp checked_in_at
        varchar method
        tinyint allowed
        varchar reason
        tinyint used_session
        bigint checked_by
    }
    sub_freezes {
        bigint id PK
        bigint membership_id FK
        date starts_on
        date ends_on
        smallint days
        varchar reason
        bigint created_by
        timestamp created_at
        timestamp updated_at
    }
    sub_members {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint employee_id
        varchar number
        varchar name
        varchar phone
        varchar email
        varchar gender
        date birth_date
        varchar card_token
        varchar note
        tinyint is_active
        timestamp created_at
        timestamp updated_at
        bigint platform_customer_id
    }
    sub_membership_members {
        bigint membership_id FK
        bigint member_id FK
    }
    sub_memberships {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint member_id FK
        bigint plan_id FK
        bigint renewed_from_id FK
        bigint sold_at_branch_id
        date starts_on
        date ends_on
        varchar status
        smallint sessions_total
        smallint sessions_used
        smallint frozen_days
        decimal price
        varchar bnpl_provider
        bigint invoice_id
        varchar cancel_reason
        timestamp cancelled_at
        bigint sold_by
        timestamp created_at
        timestamp updated_at
    }
    sub_plan_branch {
        bigint plan_id FK
        bigint branch_id FK
    }
    sub_plans {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        varchar name_ar
        varchar name_en
        text description_ar
        text description_en
        varchar period_unit
        smallint period_count
        decimal price
        decimal vat_rate
        smallint sessions
        smallint freeze_days
        varchar gender
        tinyint is_corporate
        tinyint family_size
        tinyint is_active
        smallint sort_order
        timestamp created_at
        timestamp updated_at
    }
    sub_zones {
        bigint id PK
        bigint client_id FK
        bigint branch_id FK
        varchar name_ar
        varchar name_en
        varchar gender
        timestamp created_at
        timestamp updated_at
    }
    platform_branches { }
    platform_companies { }
    platform_customers { }
    platform_branches ||--o{ sub_check_ins : "branch_id"
    platform_branches ||--o{ sub_plan_branch : "branch_id"
    platform_branches ||--o{ sub_zones : "branch_id"
    platform_companies ||--o{ sub_members : "client_id,company_id"
    platform_companies ||--o{ sub_plans : "client_id,company_id"
    platform_companies ||..o{ sub_memberships : "company_id"
    platform_customers ||..o{ sub_members : "platform_customer_id"
    sub_members ||--o{ sub_check_ins : "member_id"
    sub_members ||--o{ sub_membership_members : "member_id"
    sub_members ||--o{ sub_memberships : "member_id"
    sub_memberships ||--o{ sub_check_ins : "membership_id"
    sub_memberships ||--o{ sub_freezes : "membership_id"
    sub_memberships ||--o{ sub_membership_members : "membership_id"
    sub_memberships ||--o{ sub_memberships : "renewed_from_id"
    sub_plans ||--o{ sub_memberships : "plan_id"
    sub_plans ||--o{ sub_plan_branch : "plan_id"
    sub_zones ||--o{ sub_check_ins : "zone_id"
```

## Assets and inventory

```mermaid
erDiagram
    ast_assets {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        bigint warehouse_id FK
        bigint category_id FK
        varchar tag
        varchar name_ar
        varchar name_en
        varchar serial
        varchar model
        date purchase_date
        decimal cost
        decimal salvage_value
        smallint useful_life_months
        varchar status
        date disposed_on
        decimal disposal_amount
        varchar disposal_reason
        text notes
        timestamp created_at
        timestamp updated_at
    }
    ast_attachments {
        bigint id PK
        bigint client_id FK
        varchar attachable_type
        bigint attachable_id
        varchar disk
        varchar path
        varchar mime
        int size
        varchar stage
        bigint uploaded_by
        timestamp created_at
        timestamp updated_at
    }
    ast_audit_items {
        bigint id PK
        bigint audit_id FK
        bigint asset_id FK
        varchar status
        timestamp scanned_at
    }
    ast_audits {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        date started_on
        timestamp closed_at
        varchar note
        bigint started_by
        bigint closed_by
        timestamp created_at
        timestamp updated_at
    }
    ast_categories {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        smallint useful_life_months
        tinyint depreciates
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    ast_custodies {
        bigint id PK
        bigint client_id FK
        bigint asset_id FK
        bigint employee_id
        date assigned_on
        date returned_on
        varchar condition_out
        varchar condition_in
        varchar note
        bigint assigned_by
        bigint returned_by
        timestamp created_at
        timestamp updated_at
    }
    ast_handovers {
        bigint id PK
        bigint client_id FK
        bigint company_id
        varchar number
        varchar from_type
        bigint from_id
        varchar to_type
        bigint to_id
        varchar status
        varchar purpose
        varchar note
        decimal latitude
        decimal longitude
        varchar channel
        bigint created_by
        bigint accepted_by
        timestamp accepted_at
        timestamp created_at
        timestamp updated_at
    }
    ast_incidents {
        bigint id PK
        bigint client_id FK
        bigint company_id
        varchar number
        varchar kind
        varchar source_type
        bigint source_id
        bigint variant_id FK
        bigint asset_id FK
        decimal quantity
        decimal value
        varchar holder_type
        bigint holder_id
        bigint responsible_employee_id
        text description
        varchar status
        varchar resolution
        decimal charged_amount
        varchar resolution_note
        bigint reported_by
        bigint resolved_by
        timestamp resolved_at
        timestamp created_at
        timestamp updated_at
    }
    ast_item_packs {
        bigint id PK
        bigint item_id FK
        varchar name_ar
        varchar name_en
        decimal factor
    }
    ast_item_variants {
        bigint id PK
        bigint item_id FK
        varchar name_ar
        varchar name_en
        varchar sku
        varchar barcode
        decimal content
        bigint content_unit_id FK
        decimal unit_cost
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    ast_items {
        bigint id PK
        bigint client_id FK
        bigint category_id FK
        bigint unit_id FK
        varchar name_ar
        varchar name_en
        text description
        decimal min_stock
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    ast_maintenance {
        bigint id PK
        bigint client_id FK
        bigint asset_id FK
        varchar kind
        varchar description
        bigint reported_by_employee_id
        date opened_on
        date closed_on
        decimal cost
        varchar vendor
        varchar resolution
        bigint opened_by
        timestamp created_at
        timestamp updated_at
    }
    ast_movement_lines {
        bigint id PK
        varchar document_type
        bigint document_id
        bigint variant_id FK
        bigint asset_id FK
        decimal quantity
        decimal received
        varchar entered_unit
        varchar condition
        varchar note
    }
    ast_stock {
        bigint id PK
        bigint client_id FK
        bigint variant_id FK
        varchar holder_type
        bigint holder_id
        decimal quantity
        timestamp created_at
        timestamp updated_at
    }
    ast_stock_moves {
        bigint id PK
        bigint client_id FK
        bigint variant_id FK
        varchar from_type
        bigint from_id
        varchar to_type
        bigint to_id
        decimal quantity
        varchar reason
        varchar document_type
        bigint document_id
        bigint user_id
        timestamp moved_at
    }
    ast_transfers {
        bigint id PK
        bigint client_id FK
        varchar number
        bigint from_warehouse_id FK
        bigint to_warehouse_id FK
        varchar status
        varchar note
        timestamp sent_at
        timestamp received_at
        bigint created_by
        bigint sent_by
        bigint received_by
        timestamp created_at
        timestamp updated_at
    }
    ast_units {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        varchar symbol
        varchar dimension
        decimal factor
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    ast_warehouses {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id FK
        varchar code
        varchar name_ar
        varchar name_en
        varchar kind
        bigint keeper_employee_id
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    platform_branches { }
    platform_companies { }
    ast_assets ||--o{ ast_audit_items : "asset_id"
    ast_assets ||--o{ ast_custodies : "asset_id"
    ast_assets ||--o{ ast_incidents : "asset_id"
    ast_assets ||--o{ ast_maintenance : "asset_id"
    ast_assets ||--o{ ast_movement_lines : "asset_id"
    ast_audits ||--o{ ast_audit_items : "audit_id"
    ast_categories ||--o{ ast_assets : "category_id"
    ast_categories ||--o{ ast_items : "category_id"
    ast_item_variants ||--o{ ast_incidents : "variant_id"
    ast_item_variants ||--o{ ast_movement_lines : "variant_id"
    ast_item_variants ||--o{ ast_stock : "variant_id"
    ast_item_variants ||--o{ ast_stock_moves : "variant_id"
    ast_items ||--o{ ast_item_packs : "item_id"
    ast_items ||--o{ ast_item_variants : "item_id"
    ast_units ||--o{ ast_item_variants : "content_unit_id"
    ast_units ||--o{ ast_items : "unit_id"
    ast_warehouses ||--o{ ast_assets : "warehouse_id"
    ast_warehouses ||--o{ ast_transfers : "from_warehouse_id"
    ast_warehouses ||--o{ ast_transfers : "to_warehouse_id"
    platform_branches ||--o{ ast_assets : "company_id,branch_id"
    platform_branches ||--o{ ast_audits : "company_id,branch_id"
    platform_branches ||--o{ ast_warehouses : "company_id,branch_id"
    platform_companies ||..o{ ast_handovers : "company_id"
    platform_companies ||..o{ ast_incidents : "company_id"
```

## Finance (ledger, purchasing)

```mermaid
erDiagram
    finance_accounts {
        bigint id PK
        bigint client_id FK
        varchar code
        varchar name_ar
        varchar name_en
        varchar type
        varchar system_key
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    finance_bill_payments {
        bigint id PK
        bigint bill_id FK
        decimal amount
        date paid_on
        varchar method
        varchar reference
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    finance_bills {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint branch_id
        bigint supplier_id FK
        bigint purchase_order_id FK
        varchar supplier_invoice_no
        date bill_date
        date due_on
        varchar status
        decimal subtotal
        decimal vat
        decimal total
        decimal paid
        varchar attachment_path
        bigint approved_by FK
        timestamp created_at
        timestamp updated_at
    }
    finance_document_lines {
        bigint id PK
        varchar document_type
        bigint document_id
        bigint account_id FK
        varchar description
        decimal quantity
        decimal unit_price
        decimal vat_rate
        decimal net
        decimal vat
    }
    finance_journal_entries {
        bigint id PK
        bigint client_id FK
        bigint company_id FK
        bigint branch_id
        date entry_date
        varchar source
        bigint source_id
        varchar memo
        tinyint is_reversal
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    finance_journal_lines {
        bigint id PK
        bigint entry_id FK
        bigint account_id FK
        decimal debit
        decimal credit
        decimal tax_base
        varchar memo
    }
    finance_purchase_orders {
        bigint id PK
        bigint client_id FK
        bigint company_id
        bigint branch_id
        bigint supplier_id FK
        varchar number
        date order_date
        date expected_on
        varchar status
        decimal subtotal
        decimal vat
        decimal total
        text notes
        bigint created_by FK
        bigint approved_by FK
        timestamp created_at
        timestamp updated_at
    }
    finance_suppliers {
        bigint id PK
        bigint client_id FK
        varchar name
        varchar vat_number
        varchar cr_number
        varchar phone
        varchar email
        varchar iban
        smallint payment_days
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }
    users { }
    platform_companies { }
    platform_branches { }
    finance_accounts ||--o{ finance_document_lines : "account_id"
    finance_accounts ||--o{ finance_journal_lines : "account_id"
    finance_bills ||--o{ finance_bill_payments : "bill_id"
    finance_journal_entries ||--o{ finance_journal_lines : "entry_id"
    finance_purchase_orders ||--o{ finance_bills : "purchase_order_id"
    finance_suppliers ||--o{ finance_bills : "supplier_id"
    finance_suppliers ||--o{ finance_purchase_orders : "supplier_id"
    platform_branches ||..o{ finance_bills : "branch_id"
    platform_branches ||..o{ finance_journal_entries : "branch_id"
    platform_branches ||..o{ finance_purchase_orders : "branch_id"
    platform_companies ||--o{ finance_journal_entries : "client_id,company_id"
    platform_companies ||..o{ finance_bills : "company_id"
    platform_companies ||..o{ finance_purchase_orders : "company_id"
    users ||--o{ finance_bill_payments : "created_by"
    users ||--o{ finance_bills : "approved_by"
    users ||--o{ finance_journal_entries : "created_by"
    users ||--o{ finance_purchase_orders : "approved_by"
    users ||--o{ finance_purchase_orders : "created_by"
```

## Core app (users, clients, portal, permissions)

```mermaid
erDiagram
    change_requests {
        bigint id PK
        bigint project_id FK
        bigint user_id FK
        varchar title
        text description
        varchar status
        decimal quote_amount
        smallint quote_days
        text quote_note
        timestamp quoted_at
        bigint decided_by FK
        timestamp decided_at
        bigint invoice_id FK
        timestamp created_at
        timestamp updated_at
    }
    clients {
        bigint id PK
        varchar name_ar
        varchar name_en
        varchar contact_name
        varchar contact_email
        varchar contact_phone
        varchar status
        text notes
        timestamp created_at
        timestamp updated_at
    }
    contact_messages {
        bigint id PK
        varchar name
        varchar email
        varchar company
        varchar service
        text message
        varchar ip
        timestamp created_at
        timestamp updated_at
    }
    invite_tokens {
        varchar email
        varchar token
        timestamp created_at
    }
    invoice_items {
        bigint id PK
        bigint invoice_id FK
        varchar description
        decimal quantity
        decimal unit_price
        decimal total
        int sort_order
        timestamp created_at
        timestamp updated_at
    }
    invoices {
        bigint id PK
        bigint client_id FK
        bigint project_id FK
        varchar number
        date issue_date
        date due_date
        varchar status
        decimal subtotal
        decimal vat_rate
        decimal vat
        decimal total
        char currency
        text notes
        json issuer
        timestamp sent_at
        timestamp paid_at
        bigint einvoice_id
        varchar einvoice_number
        varchar einvoice_status
        timestamp created_at
        timestamp updated_at
    }
    milestones {
        bigint id PK
        bigint project_id FK
        varchar title
        text description
        date due_date
        timestamp done_at
        int sort_order
        timestamp created_at
        timestamp updated_at
    }
    model_has_permissions {
        bigint permission_id FK
        varchar model_type
        bigint model_id
    }
    model_has_roles {
        bigint role_id FK
        varchar model_type
        bigint model_id
    }
    notifications {
        char id PK
        varchar type
        varchar notifiable_type
        bigint notifiable_id
        text data
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }
    permissions {
        bigint id PK
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }
    personal_access_tokens {
        bigint id PK
        varchar tokenable_type
        bigint tokenable_id
        text name
        varchar token
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }
    products {
        bigint id PK
        varchar slug
        varchar name_ar
        varchar name_en
        text description_ar
        text description_en
        varchar logo
        varchar url
        tinyint is_active
        int sort_order
        timestamp created_at
        timestamp updated_at
    }
    project_files {
        bigint id PK
        bigint project_id FK
        varchar title
        varchar url
        varchar category
        timestamp created_at
        timestamp updated_at
    }
    project_resources {
        bigint id PK
        bigint project_id FK
        varchar name
        varchar role
        text notes
        timestamp created_at
        timestamp updated_at
    }
    project_services {
        bigint id PK
        bigint project_id FK
        bigint service_id FK
        varchar status
        varchar plan
        decimal price
        decimal monthly_cap
        char cap_alert_month
        tinyint cap_alert_level
        varchar pricing_model
        text api_key
        timestamp api_key_issued_at
        timestamp last_used_at
        date started_at
        text notes
        json meta
        timestamp created_at
        timestamp updated_at
    }
    projects {
        bigint id PK
        bigint client_id FK
        varchar name_ar
        varchar name_en
        varchar slug
        varchar logo
        text description_ar
        text description_en
        varchar type
        varchar relationship
        decimal partnership_share
        varchar status
        tinyint progress
        date started_at
        date expected_launch_at
        date launched_at
        varchar drive_url
        varchar repo_url
        varchar app_url
        timestamp created_at
        timestamp updated_at
    }
    releases {
        bigint id PK
        bigint project_id FK
        varchar version
        varchar platform
        varchar channel
        varchar url
        text notes
        date released_at
        timestamp created_at
        timestamp updated_at
    }
    requests {
        bigint id PK
        varchar type
        varchar name
        varchar company
        varchar email
        varchar phone
        text message
        json services
        varchar status
        text admin_notes
        bigint handled_by FK
        bigint client_id FK
        varchar locale
        varchar ip
        timestamp created_at
        timestamp updated_at
    }
    role_has_permissions {
        bigint permission_id FK
        bigint role_id FK
    }
    roles {
        bigint id PK
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }
    service_usage {
        bigint id PK
        bigint project_service_id FK
        date usage_date
        decimal quantity
        decimal cost
        varchar source
        json meta
        timestamp created_at
        timestamp updated_at
    }
    services {
        bigint id PK
        varchar key
        varchar name_ar
        varchar name_en
        text description_ar
        text description_en
        varchar icon
        tinyint is_active
        int sort_order
        varchar docs_url
        decimal default_price
        varchar pricing_model
        varchar unit
        timestamp created_at
        timestamp updated_at
    }
    settings {
        bigint id PK
        varchar key
        text value
        timestamp created_at
        timestamp updated_at
    }
    users {
        bigint id PK
        bigint client_id FK
        varchar name
        varchar email
        timestamp email_verified_at
        varchar password
        tinyint must_change_password
        varchar locale
        timestamp last_login_at
        varchar remember_token
        timestamp created_at
        timestamp updated_at
    }
    invoices ||--o{ change_requests : "invoice_id"
    invoices ||--o{ invoice_items : "invoice_id"
    permissions ||--o{ model_has_permissions : "permission_id"
    permissions ||--o{ role_has_permissions : "permission_id"
    project_services ||--o{ service_usage : "project_service_id"
    projects ||--o{ change_requests : "project_id"
    projects ||--o{ invoices : "project_id"
    projects ||--o{ milestones : "project_id"
    projects ||--o{ project_files : "project_id"
    projects ||--o{ project_resources : "project_id"
    projects ||--o{ project_services : "project_id"
    projects ||--o{ releases : "project_id"
    roles ||--o{ model_has_roles : "role_id"
    roles ||--o{ role_has_permissions : "role_id"
    services ||--o{ project_services : "service_id"
    users ||--o{ change_requests : "decided_by"
    users ||--o{ change_requests : "user_id"
    users ||--o{ requests : "handled_by"
```
