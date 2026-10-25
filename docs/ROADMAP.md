# Roadmap and change log

Add a line to the change log for every merged change, and move items from "Planned" to the log when done.
Priorities: **P0** = needed before the first paying client (MVP), **P1** = soon after launch, **P2** = later.

## 1. Change log

| Date | Change |
|---|---|
| 2026-10 | Platform kernel: tenancy, organizations/companies/branches, access roles at any node, audit, module subscriptions; HR core (employees, contracts, documents, attendance with geofences, leave, payroll, GOSI, WPS); Invoicing with ZATCA; POS; Reservations; Subscriptions; Assets and inventory; client portal and Baydaq admin. |
| 2026-10 | HR batches: org chart, dual control, document radar, bonuses/deductions/advances, end-of-service and final settlement, shifts and open shifts, Ramadan hours, holidays, Nitaqat meter, government wage check, selfie option, WhatsApp notices, payment links. |
| 2026-10 | POS and menu extras, reservations/subscriptions/assets/invoicing extras; ⌘K search and saved views, board view, shared customer and wallet; AI document capture and HR assistant; verified letters with QR, member QR door access. |
| 2026-10 | Tasks (web + mobile), breaks (scheduled/granted/requested, live board). |
| 2026-10-25 | **A** Packages, add-ons, client plans, feature flags (web and mobile), limits. |
| 2026-10-25 | **B** Theming (brand, dark mode, mobile API) and client registration (by phone at branch/company/organization, QR self sign-up, consent). |
| 2026-10-25 | **C** Government hub; GOSI 2024-law rates; Qiwa contract tracker; Muqeem visa tracker; Mudad file with GOSI number. |
| 2026-10-25 | **D** Offboarding under the 2025 Labor Law amendments; violations and disciplinary record; PDPL toolkit. |
| 2026-10-25 | **E** Hiring with public careers page and onboarding tasks; performance reviews. |
| 2026-10-25 | **G** Remote and hybrid work. |
| 2026-10-25 | **F** Finance: one ledger, chart of accounts, trial balance, P&L, VAT return; purchasing and bills; expense claims. |
| 2026-10-25 | Security and race-condition hardening (see `SECURITY.md`), conflict fixes (see `AUDIT.md`), test account seeder, project documentation, generated ER diagram. |
| 2026-10-25 | **H1** Overtime (request/record → approve → payroll bonus at the Art. 107 rate, yearly cap) and employee loans (advances of the `loan` kind, installment cap); owner-set loan/overtime rules in HR settings. |
| 2026-10-25 | **H2** Medical insurance (policies, employees and dependants, renewals, uninsured list) and training (sessions, attendance, certificates with expiry). |
| 2026-10-25 | **H3** Policies and circulars with acknowledgement, staff surveys (anonymous, eNPS), recognition wall with rewards through payroll. |
| 2026-10-25 | **H4** Message builder: templates with placeholders, weekly/monthly/event triggers, inbox + email + WhatsApp, sent once per occasion. |
| 2026-10-25 | **H5** One calendar of all events through the tagged `CalendarSource` port (HR dates, government deadlines, supplier bills due), "coming up" on `/me`, calendar in the app API. |
| 2026-10-25 | Direction: Baydaq is a general HR and company system. Branches can be any business (café, gym, shop, office); no more industry-specific features — POS, reservations and memberships stay as optional add-ons. |

## 2. Planned — fixes from the review (do these first)

| Pri | Item | Ref |
|---|---|---|
| P0 | Booking double-booking lock | SECURITY R12 |
| P0 | ZATCA call outside the DB transaction (commit chain first) | SECURITY R2 |
| P0 | Sanctum token expiry; 2FA for admins and owners; secure cookie in prod | SECURITY 1.2, 1.3 |
| P0 | Suspended plan turns features off; no-plan accounts get a default package | AUDIT F4 |
| P0 | Role editing needs account-wide access | SECURITY 1.4 |
| P1 | Remaining locks: payroll rebuild, advances/repayments, breaks limit, booking checkout, void vs pay, seats on hire | SECURITY R13–R19 |
| P1 | Ledger gaps: wallet top-ups/loyalty, deposits, POS refunds, advance payouts, asset purchases | AUDIT B6 |
| P1 | Company timezone for business dates (POS) | AUDIT B7 |
| P1 | One phone/VAT helper; invoicing buyers linked to the shared customer | AUDIT D1, D2, B8 |
| P1 | HR calendar conflicts (leave / remote / shift) | AUDIT B2 |
| P1 | Webhook amount re-check with Moyasar; OTP before recording marketing consent | SECURITY 1.5, 1.6 |
| P2 | Plan as the single source of module subscriptions | AUDIT D3 |
| P2 | Expiring items in one radar (documents + visas); exit checklist as tasks | AUDIT D5, D6 |
| P2 | Mobile flags without endpoints (payslips, letters) | AUDIT F3 |

## 3. Ideas — the main system (Platform)

| Pri | Idea |
|---|---|
| P0 | **Self-service onboarding wizard** for a new owner: company data (CR, VAT), branches on a map, first employees, choose package — first value in 15 minutes. |
| P0 | **Notifications centre** (in-app + WhatsApp + email) with per-user preferences: approvals waiting, documents expiring, bills due, VAT deadline. |
| P0 | **Approvals inbox**: one page for everything waiting on me (leave, advances, expenses, bills, POs, remote days, resignations). |
| P1 | **Owner mobile dashboard**: today's sales by branch, staff present/late, cash position, VAT due, bills due. |
| P1 | **Data import** (Excel templates) for employees, products, customers, suppliers, opening balances. |
| P1 | **Usage-based billing** in admin: invoice clients from their plan and limits automatically. |
| P1 | **Audit trail viewer** with filters (who changed what), export. |
| P2 | Public API keys and webhooks for clients' own integrations. |
| P2 | White-label mobile app per owner (theme already exists). |
| P2 | Multi-currency for owners with branches outside KSA. |

## 4. Ideas per subsystem

### HR
| Pri | Idea |
|---|---|
| P0 | Payslip screen in the app (`mobile.payslips`) and letter requests (`mobile.letters`). |
| P0 | Approvals inbox across HR (leave, advances, loans, overtime, expenses, remote days, resignations). |
| P1 | Leave/remote/shift conflict check on the calendar (AUDIT B2). |
| P1 | End-of-service provision posted monthly to the ledger. |
| P1 | Message builder: more occasions (probation ending, document expiring) and WhatsApp template approval flow. |
| P1 | Calendar export (iCal feed per user) and company events (meetings, inventory days) added by hand. |
| P2 | Succession and skills matrix; internal job postings. |
| P2 | Org-wide policy library with versions (re-acknowledge on a new version). |

### Point of sale (optional add-on)
| Pri | Idea |
|---|---|
| P0 | Refunds and returns (credit note + reverse cash/wallet/loyalty + ledger). |
| P1 | Offline mode for the register (queue and sync). |
| P1 | Stock deduction per sale from Assets inventory. |

### Customers and loyalty
| Pri | Idea |
|---|---|
| P0 | **Points and rewards**: earn per riyal or visit, tiers, redeem as wallet credit or free item (the owner's next request). |
| P1 | Campaigns by WhatsApp/SMS to consenting clients (birthday, win-back after 30 days). |
| P1 | OTP verification for self sign-up. |
| P2 | Referral codes; gift cards. |

### Invoicing
| Pri | Idea |
|---|---|
| P1 | Recurring invoices and quotations → invoice. |
| P1 | Customer statements and payment reminders (automatic). |
| P2 | Credit limits and collections board. |

### Reservations and memberships (optional add-ons)
| Pri | Idea |
|---|---|
| P1 | Waitlist and no-show fees; auto-renew with a saved card. |
| P2 | Bookings and membership renewals on the shared calendar. |

### Assets and inventory
| Pri | Idea |
|---|---|
| P0 | Reorder points and purchase suggestions → Finance purchase orders. |
| P1 | Receiving against a purchase order updates stock and cost. |
| P1 | Depreciation schedule posted to the ledger. |
| P2 | Barcode/RFID stock-takes from the app (`mobile.assets_audit`). |

### Finance
| Pri | Idea |
|---|---|
| P0 | Opening balances and fiscal year close. |
| P1 | Bank reconciliation (statement import, match card settlements and payments). |
| P1 | Budgets per branch with alerts. |
| P1 | Zakat estimate and ZATCA VAT return export. |
| P2 | Fixed-asset register from Assets; cost centres; multi-company consolidation. |

### Government
| Pri | Idea |
|---|---|
| P1 | Saudization planner: what hires reach the next Nitaqat band. |
| P1 | Calendar of government deadlines (GOSI monthly, VAT quarterly, iqama renewals, CR renewal, Balady licence). |
| P2 | Direct integrations when public APIs become available (Qiwa, GOSI). |

### AI
| Pri | Idea |
|---|---|
| P1 | Monthly owner briefing ("sales up 12 %, Olaya lateness rising, 3 bills overdue"). |
| P1 | Receipt scanning for expense claims and supplier bills. |
| P2 | Arabic voice commands in the employee app. |

## 5. MVP scope suggestion

For the first paying clients (any company with 5–200 staff and one or more branches, whatever the branch does):

1. Platform: onboarding wizard, approvals inbox, notifications, packages (done), theming (done).
2. HR: employees, attendance, leave, shifts, payroll with GOSI/WPS, offboarding, letters, mobile app (done), payslips (to build).
3. HR engagement: policies, surveys, recognition, message builder, calendar (done).
4. Optional add-ons: POS + customers + loyalty, refunds (to build).
5. Invoicing with ZATCA (done).
6. Finance: ledger, VAT return, expenses, bills (done); opening balances (to build).
7. All P0 fixes in section 2.
