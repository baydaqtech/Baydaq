# Baydaq — project handbook

The single reference for what Baydaq is, how it is built, what exists today and what comes next.
Keep it current: every change that adds, removes or changes a feature updates this file, `docs/ROADMAP.md`
(history and plans) and, after a migration, `docs/ER.md` (`php artisan docs:er`).

| Document | What it holds |
|---|---|
| `docs/PROJECT.md` (this file) | Product, architecture, every module and feature, shared concepts, how to run and test |
| `docs/ER.md` | Entity-relationship diagram per module, generated from the database |
| `docs/SECURITY.md` | Security checklist (what is checked, what is open), race conditions and data-integrity rules |
| `docs/AUDIT.md` | Conflicts and overlaps between subsystems, with status |
| `docs/ROADMAP.md` | What was built (change log), what is planned, ideas per subsystem, MVP priorities |

---

## 1. What Baydaq is

A Saudi business suite sold as packages. An **owner** (a client of Baydaq) runs one or more
**organizations → companies → branches**, and Baydaq gives them, in Arabic and English:

- **HR** for staff (attendance with geofences, shifts, leave, payroll with GOSI and WPS, offboarding…), with a
  mobile app for employees;
- **Point of sale**, **invoicing** (ZATCA e-invoicing), **reservations**, **memberships**, **assets and inventory**;
- **Finance** (one ledger every module posts to, purchasing, supplier bills, VAT return);
- **Customers** (one record per phone number across all of the above, with a wallet);
- government helpers (Qiwa, GOSI, Mudad, Muqeem, ZATCA links and files).

Baydaq staff use an **admin** area to manage clients, packages, add-ons and plans. Clients also have a **portal**
(projects, invoices from Baydaq).

## 2. Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13, PHP 8.4, MySQL 8.4 |
| Frontend | Inertia v3 + React 19 (TSX), Tailwind 4, shadcn/ui |
| Auth | Fortify (web), Sanctum tokens (mobile API `/api/v1`), Spatie permissions (Baydaq staff only) |
| Tests | PHPUnit 12 (feature tests against MySQL `testing`), `tests/Architecture/ModuleBoundariesTest` |
| PDF / QR | mPDF, chillerlan/php-qrcode |
| AI | Anthropic PHP SDK (`config('baydaq.ai.model')`), optional per package (`suite.ai`) |
| Money | `brick/math` BigDecimal; database `decimal(…,2)` |

## 3. Architecture

### 3.1 Modular monolith

`app/Modules/{Platform, Hr, Pos, Invoicing, Reservations, Subscriptions, Assets, Finance}`. Each module has its own
migrations (table prefix), models, actions, controllers, routes and a service provider registered in
`bootstrap/providers.php`.

| Module | Table prefix | Owns |
|---|---|---|
| Platform (kernel) | `platform__` | Tenancy, organizations/companies/branches, work locations, access roles, packages and plans, module subscriptions, customers and wallet, theme, privacy requests, audit |
| Hr | `hr__` | Employees, contracts, documents, attendance, shifts, breaks, leave, pay adjustments, advances and loans, overtime, insurance, training, payroll, letters, tasks, separations, violations, hiring, reviews, remote work, expense claims, government trackers |
| Pos | `pos__` | Catalog, registers, cashiers, shifts, orders, payments, kitchen, QR menus |
| Invoicing | `inv__` | Invoices, credit/debit notes, customers (B2B buyers), ZATCA units and submissions |
| Reservations | `res__` | Services, resources, bookings, online booking pages |
| Subscriptions | `sub__` | Membership plans, members, memberships, check-ins, zones, corporate memberships |
| Assets | `ast__` | Fixed assets, custody, maintenance, audits, inventory items, warehouses, transfers, handovers, incidents |
| Finance | `finance__` | Chart of accounts, journal, suppliers, purchase orders, bills, bill payments |

**The rule:** a module talks to another only through the other module's `Ports` (interfaces), `Data` (read-only DTOs)
and `Events`. The Platform kernel additionally exposes `Enums`, `Access`, `Tenancy`, `Models\Concerns` and
`Http\Controllers\Concerns`. No module reads another module's tables. `tests/Architecture/ModuleBoundariesTest.php`
enforces both rules.

Important ports (in `app/Modules/Platform/Ports` unless noted):

| Port | Purpose | Implemented by |
|---|---|---|
| `OrgDirectory` | Companies, branches, work locations as DTOs | Platform |
| `Access`, `Entitlements` | Permission checks; modules/features/limits of the account's package | Platform |
| `Customers` | Identify by phone, register at a branch/company/organization, wallet credit/debit, loyalty | Platform (`WalletCustomers`) |
| `Ledger` | Post a business event to the books once | Finance (`GeneralLedger`); `NullLedger` when Finance is absent |
| `Accounts` | Logins of the tenant (invite, sign out everywhere) | Platform |
| `Payments`, `Messaging`, `PdfRenderer`, `QrCodes`, `Intelligence`, `Branding`, `Audit` | Shared services | Platform |
| Tagged: `SuiteSearch`, `BoardSignals`, `CustomerActivity`, `CustomerData` | Each module contributes search results, board health, customer timeline, personal-data export/erase | Modules |
| `Hr\Ports\StaffDirectory`, `PayrollAdjustments`, `StaffAvailability`, `OffboardingClearance` | HR services other modules use (cashiers, deductions, bookable staff, custody clearance) | Hr |
| `Invoicing\Ports\InvoiceIssuer` | Issue simplified/standard invoices for POS, bookings, memberships, recharges | Invoicing |

### 3.2 Multi-tenancy

- Every tenant table has `client_id`. Models use `BelongsToClient`, which adds `TenantScope` (fails closed: no tenant
  set → no rows) and fills `client_id` on create.
- The current tenant is resolved by middleware (`suite.scope`, `api.tenant`) before route-model binding.
- Public pages (QR join, careers, booking, menus, letter verification) look the record up with `withoutGlobalScopes()`
  by a random token and then run inside `CurrentTenant::runFor($clientId, …)`.
- Seeders run without model events, so actions that may run in seeders set `client_id` explicitly.

### 3.3 Access control

- **Access roles** (per tenant) hold permission keys like `hr.employees.update`; a role is **assigned at a node**
  of the tree (client, organization, company, branch). `AccessMap::branches($key)` returns the `BranchSet` a key
  reaches.
- **Route level:** `access:key1|key2` middleware (holds the key anywhere).
- **Record level:** controllers check the record against the reach (`authorizeEmployee`, `authorizeBranch`,
  `allowsWholeCompany`, `authorizeAccount` for account-wide records).
- **Suite view filter:** URLs are `/suite/{scope}/…` where scope is `all`, `o-{org}` or `c-{company}`; queries are
  narrowed to the view (`SuiteScope::branchesFor`).
- Feature enums (`*Feature`) declare every key; the role editor shows them as a matrix.
- Granting and revoking are both guarded: you can only give or take away what you yourself reach; only an owner
  removes an owner; the last owner can't be removed.

### 3.4 Packages, feature flags and limits

- `Platform/Access/FeatureCatalog.php` lists **limits** (organizations, companies, branches, employees, users,
  registers), **web features** (`suite.*`, `hr.*`, `finance.*`) and **mobile features** (`mobile.*`).
- Admin builds **packages** and **add-ons**; a **client plan** = package + add-ons + overrides
  (`EffectivePlan`). Applying a plan syncs the per-module `platform__subscriptions` rows.
- Routes use `feature:key` middleware; the frontend hides items whose feature is off (`SuiteNav`, `/me`).
- An account **without a plan** gets every feature and no limits (useful for demos; see AUDIT for the risk).

| Feature key | Turns on |
|---|---|
| `suite.board` | Board of branch health |
| `suite.customers` | Customers, wallet, client registration, branch QR sign-up |
| `suite.theme` | Owner branding (colours, logo, name, radius, dark mode) |
| `suite.ai` | AI HR assistant and document scanning |
| `suite.gov` | Government hub, Qiwa / Muqeem / GOSI trackers |
| `suite.privacy` | PDPL request register, customer export and erasure |
| `hr.tasks`, `hr.breaks`, `hr.shifts`, `hr.advances`, `hr.letters`, `hr.compliance` | HR extras of the same names |
| `hr.remote` | Remote and hybrid work |
| `hr.recruitment`, `hr.performance` | Hiring with careers page; performance reviews |
| `hr.expenses`, `hr.violations`, `hr.offboarding` | Expense claims; disciplinary record; resignations and exit |
| `hr.overtime`, `hr.loans` | Overtime paid through payroll; employee loans (advances of the `loan` kind) |
| `hr.insurance`, `hr.training` | Medical insurance (policies, members, uninsured list); training and certificates |
| `finance.accounting` | Ledger, chart of accounts, reports, VAT return |
| `finance.purchasing` | Suppliers, purchase orders, bills, payments |
| `mobile.*` | Screens of the employee app (attendance, selfie, leave, tasks, breaks, shifts, advances, payslips, letters, expenses, remote, assets audit, resignation, reviews, overtime, insurance, training) |

### 3.5 One shared concept per idea

These are deliberately single, shared across modules — never add a second one:

- **Branch** (`platform__branches`): HR placements, POS registers, invoices, bookings, memberships, assets,
  journal entries all hang off the same branches.
- **Customer** (`platform__customers`, one per phone per account) with **registrations** at branch / company /
  organization level (chosen per company) and one **wallet**.
- **Ledger** (`finance__journal_*`): every module posts through the `Ledger` port; each source posts once.
- **Deductions** (`hr__deductions`): penalties, cash shortages, staff meals, damage all go through `SaveDeduction`
  and its Labor-Law caps.
- **Tasks** (`hr__tasks`): manager tasks and onboarding tasks.

`docs/AUDIT.md` lists where this is not yet fully true (e.g. invoicing buyers, members' phone rule).

## 4. Features by module

### Platform (suite)
- Structure: organizations, companies (CR, VAT, unified number, GOSI number, Qiwa ID, address, loyalty %,
  client scope), branches (codes, cities, head office), brands, work locations (geofence + Wi-Fi).
- Access: roles, members, invitations, assignments at any node; audit log.
- Home, ⌘K search across modules, saved/recent views, board (2D) of branch health.
- Customers: one per phone, wallet (top-up, adjust, spend, loyalty), activity across modules, **client
  registration** by staff, at POS checkout, from bookings, or **self sign-up via a branch QR code** (`/join/{token}`)
  with marketing consent.
- Theme: per-account colours/logo/name/roundness/mode; applied to suite, `/me` and the mobile API.
- Government hub (`/gov`): official numbers per company and links to Qiwa, GOSI, Mudad, Muqeem, Absher, HRSD,
  ZATCA, Fatoora, MC, Business Center, Balady, National Address.
- Privacy (PDPL): request register with 30-day deadline, customer data export (JSON) and erasure across modules.

### HR
- Employees (bilingual, encrypted ID and IBAN with blind index, dual control for sensitive changes), org chart,
  sections, jobs, catalog of allowances/bonuses/deductions/leave/document types.
- Contracts with allowances; Qiwa authentication status.
- Documents with expiry radar and alerts; AI document capture.
- Attendance: mobile punches matched to geofences, flags (other branch, other company, mocked location), selfie
  option, device binding, PIN; **remote/hybrid days accepted away from the branch**.
- Shifts and open shifts, holidays, Ramadan hours; breaks (scheduled/granted/requested, live board, Art. 101).
- Leave with balances and accrual; movements (transfers, secondments with monthly recharge invoices).
- Pay: bonuses, deductions (capped), advances and **loans** (same installment engine, `kind` column; loans have
  owner-set limits and an installment cap, default 10 % of wage); **overtime** (request/record → approve → an approved
  bonus of the system "Overtime" type, paid at hourly wage + 50 % of basic hourly wage, yearly hour cap); payroll runs with GOSI (legacy and 2024-law rates),
  WPS/Mudad file, end-of-service award, final settlement.
- Letters (salary/experience) with QR verification page.
- Tasks (web board + mobile) with photo proof; onboarding tasks for new hires.
- Compliance: Nitaqat meter, government wage check (GOSI/Qiwa/Mudad export comparison).
- Government trackers: Qiwa contract documentation, Muqeem exit/re-entry visas, GOSI contributions.
- **Offboarding** (2025 amendments): resignation deemed accepted after 30 days, one postponement up to 60 days,
  7-day withdrawal; exit checklist (assets, access, settlement, letter, Qiwa, GOSI, Muqeem, interview).
- **Violations**: repeats counted over 180 days, employee notified and answers before a penalty, deductions within
  caps, late arrivals detected from the roster.
- **Hiring**: openings with public careers link and CV upload, candidate stage board, hire → employee + contract +
  onboarding tasks.
- **Performance reviews**: rounds with weighted criteria, self and manager scores, goals, probation endings.
- **Remote work**: on-site / remote / hybrid weekdays, work-from-home requests.
- **Expense claims** with receipt and input VAT, approval and payback.
- **Medical insurance**: policies per company, employees and dependants by class and card, renewals due in 60 days,
  employees without cover (CCHI).
- **Training**: sessions per company, enrolment, attendance, certificates with expiry and a renewal list.
- AI HR assistant (read-only tools over the user's reach).
- Employee self-service `/me` and mobile API `/api/v1` (leave, advances, loans, overtime, insurance, training, tasks, breaks, shifts, punches,
  expenses, remote, reviews, resignation, violations).

### Point of sale
- Catalog with modifiers and branch prices, registers, cashiers (linked to HR staff), shifts with cash counts
  (shortage → HR deduction, commission → HR bonus), orders and payments (cash, mada, card, STC Pay, transfer,
  staff account, wallet), kitchen screen, QR menus, simplified ZATCA receipts, reports.

### Invoicing
- Standard and simplified invoices, credit/debit notes, ZATCA onboarding of units (CSID), clearance/reporting,
  hash chain, QR, PDF, payment links, intercompany recharges.

### Reservations
- Services, resources, staff availability (from HR), calendar, bookings, online booking page with deposit,
  reminders, checkout with ZATCA receipt.

### Subscriptions
- Plans, members (QR card door access), memberships, freezes, check-ins, zones, corporate memberships linked to
  HR employees.

### Assets
- Fixed assets with custody, maintenance, disposal, audits (stock-takes); inventory items, units, warehouses, stock
  receipts, transfers, handovers, incidents (damage charged to employee via HR).

### Finance
- Chart of accounts (created on first use), journal (automatic + manual, must balance), trial balance, profit and
  loss by company/branch, VAT return; suppliers, purchase orders (draft → approved → received → billed), bills
  (expense accounts, input VAT), payments.
- What posts to the ledger automatically: POS sales (per payment method, output VAT), invoices (not POS receipts),
  invoice payments by link, booking and membership receipts paid at the counter, payroll approval and payment,
  expense claims and paybacks, supplier bills and payments.

### Admin (Baydaq staff)
- Clients, client plans, packages and add-ons, module subscriptions, projects, Baydaq invoices, requests, services,
  products, team, settings.

## 5. Running locally

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # in local env also seeds the demo and the test account
npm run dev                         # or npm run build
php artisan test --compact          # full suite
php artisan docs:er                 # after any migration
```

## 6. Test accounts

All passwords: **`Baydaq@2026`** (seeded by `Database\Seeders\TestAccountSeeder`; run on its own with
`php artisan db:seed --class=TestAccountSeeder`).

**Al Noor Group** — organization *Al Noor Holding*:

| Company | Branches |
|---|---|
| Al Noor Cafes (مقاهي النور), VAT 310000000000003 | Head office, Olaya (OLY), Al Nakheel (NKH) |
| Al Noor Trading (النور للتجارة), VAT 310000000000013 | Head office, Jeddah (JED) |

| Login | Who | Where | Notes |
|---|---|---|---|
| `owner@alnoor.test` | Faisal Alnoor, owner | Whole account | Opens `/suite` with every module and feature |
| `manager@alnoor.test` | Sara Alqahtani (N-001) | Cafes head office | Saudi, hybrid (Sun, Mon remote) |
| `barista1@alnoor.test` | Ahmed Alzahrani (N-002) | Olaya branch | Saudi, on site |
| `barista2@alnoor.test` | Maria Santos (N-003) | Al Nakheel branch | Philippines, on site |
| `accountant@alnoor.test` | Omar Farooq (N-004) | Trading head office | Pakistan, fully remote |
| `storekeeper@alnoor.test` | Reem Alshehri (N-005) | Jeddah branch | Saudi, on site |

Employees sign in to `/me` on the web and to the mobile API; they have no suite role until the owner gives one.
Geofences: Olaya HQ (24.6950, 46.6850), Al Nakheel (24.7690, 46.6340), Jeddah (21.5430, 39.1730).

Older demo logins (`DemoSeeder`): `client@baydaq.test`, `manager@baydaq.test`, `employee@baydaq.test`, password
`password`. Baydaq admin (`/admin`): set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before seeding
(`SystemAdminSeeder`); the first sign-in asks for a new password.

## 7. Conventions (summary)

- Follow `AGENTS.md` (Laravel Boost rules), `.ai/rules` if present, sibling files.
- Actions hold business rules; controllers validate, authorize and call actions.
- Every one-time state change (pay, approve, issue, close) locks the row inside a transaction and re-checks the
  status; see `docs/SECURITY.md` §3.
- Every new feature: package key in `FeatureCatalog` (+ `suite.catalog.*` labels), permission keys in the module's
  `*Feature` enum (+ labels), Arabic and English copy, feature test, docs update.
- Pint (`vendor/bin/pint --dirty`), `npx tsc --noEmit`, full test suite before pushing.
