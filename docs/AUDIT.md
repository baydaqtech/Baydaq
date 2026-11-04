# Conflicts and overlaps between subsystems

Owner's rule: **one shared concept per idea** — no second system that can disagree with the first.
This file lists where the code follows that and where it doesn't yet. Status: ✅ fine · 🔧 fixed · ⚠️ open.

Last review: 2026-10-25.

## 1. Duplicate concepts

| # | Overlap | Status | Resolution |
|---|---|---|---|
| D1 | Invoicing has its own buyer table (`inv__customers`) | 🔧 | A buyer with a phone is linked to the shared customer (`platform_customer_id`, through `Customers::identify`); `BuyerCustomerData` exports and erases buyers (issued invoices stay as filed). The table stays: buyers also carry VAT and address for ZATCA. |
| D2 | Members (subscriptions) matched by a different phone rule than customers | 🔧 | One rule, `Platform\Data\PhoneNumber::normalize`, for customers, members, corporate enrolment and WhatsApp. |
| D3 | Which modules a client has: per-module subscriptions **and** the client plan; two admin screens write them | ⚠️ | Make the plan the single source; subscriptions derived and read-only (retire manual editor). |
| D4 | Contract Qiwa state: boolean `qiwa_authenticated` + `qiwa_status` | 🔧 / ⚠️ | Kept in sync by the model; plan: drop the boolean. Onboarding task "Qiwa" is a third, unlinked tracker. |
| D5 | Muqeem visa expiry not in the document radar / alerts / board | ⚠️ | One "expiring items" query feeding radar, notifier and board. |
| D6 | Exit checklist (JSON) vs onboarding tasks (`hr__tasks`) | ⚠️ | Generate exit items as tasks, like onboarding. |
| D7 | Asset maintenance `vendor` text and asset `cost` not linked to Finance suppliers/bills; PO "received" not linked to stock receipts | ⚠️ | Link assets/inventory receiving to Finance purchasing. |
| D8 | Legacy core tables `invoices`, `services`, `products` (Baydaq's own billing) overlap module invoices | ✅ by design | They are Baydaq-to-client billing, not tenant data. Keep separate, but name clearly. |
| D9 | Wallet vs loyalty | ✅ | Loyalty credits the same wallet. Note: the wallet is account-wide while loyalty % is per company. |

## 2. Behavioural conflicts

| # | Conflict | Status | Resolution |
|---|---|---|---|
| B1 | Terminating via the employee status button skipped the departure flow | 🔧 | Refused when a departure is in progress; completing a departure no longer terminates twice. Still open: reactivating a leaver doesn't reopen placements; final settlement reason comes from the form, not the departure type. |
| B2 | Leave, remote day and shift can all exist for one employee on one date | 🔧 | `Hr\Support\ScheduleConflicts`: a day with leave (asked for or approved) can't become a remote day, an assigned shift or a claimed open shift; bookable staff already skip approved leave. Leave may still be asked for over planned work (the manager moves the shift). |
| B3 | All deductions share the Labor-Law cap; staff meals can block disciplinary penalties; violations call `SaveDeduction` directly (no spreading to later months) | ⚠️ | Route violations through `PayrollAdjustments`; treat staff meals as purchases outside the penalty cap. |
| B4 | POS receipts counted twice in the ledger (sale + invoice) | 🔧 | POS-issued invoices carry `source = pos.order` and aren't posted again. |
| B5 | Booking and membership receipts posted as receivables though paid at the counter | 🔧 | Posted to cash / card / bank by payment means. Deposit paid online earlier is not posted separately (open). |
| B6 | Ledger gaps | 🔧 / ⚠️ | Now posted: wallet top-ups (with company and how it was paid), loyalty credit and corrections; booking deposits (held as customer deposits until checkout) and bookings checked out without Invoicing; advance/loan payouts, cash/transfer repayments and payroll installments (due from employees); the final settlement; the buyer side of group invoices. Asset and stock purchases go through supplier bills to the new *fixed assets* / *stock* accounts. The wallet is one balance across the group, so a top-up at one company spent at another leaves the two companies' wallet accounts uneven (settle between them by hand). POS refunds reverse the sale (`pos.refund`), and their credit notes are not posted twice. |
| B7 | Daily business dates | 🔧 / ⚠️ | `OrgDirectory::today(companyId)` gives the company's local date. POS order dates and numbers, the POS order, kitchen and menu screens, and the new wallet, deposit and checkout postings use it (test `orders_are_dated_and_numbered_by_the_local_business_day`). Open: HR still uses `Asia/Riyadh` or UTC `today()` in places. |
| B8 | VAT handling differs (fraction vs percent, allowed values, per-line rounding in POS vs invoicing) | 🔧 / ⚠️ | `Platform\Data\Vat`: one rate list (15, 5, 0 as percentages) for every form, and one split of a VAT-inclusive price (POS lines, bookings). Open: the agency invoices (`config('baydaq.vat_rate')`, a fraction) and ZATCA unit prices (4 decimals) keep their own. |

## 3. Feature flags and permissions

| # | Issue | Status | Resolution |
|---|---|---|---|
| F1 | Mobile API screens didn't require the matching web feature | 🔧 | Tasks, breaks, shifts, advances, reviews now need both `mobile.*` and `hr.*`. |
| F2 | Violation answers gated by `mobile.resignation` | 🔧 | Now `hr.violations`. |
| F3 | `mobile.payslips`, `mobile.letters` had no API route; `mobile.assets_audit` and `mobile.selfie` are never checked | 🔧 / ⚠️ | Payslips and letters now have endpoints (`/api/v1/payslips`, `/api/v1/letters`) and app screens. Open: gate the assets API with `mobile.assets_audit`, and make the selfie follow `mobile.selfie`. |
| F4 | Plan status (suspended) ignored by features; accounts without a plan got everything | 🔧 | Suspended/ended plans give nothing; no plan → the default package (admin flag), or nothing in production. Limits are enforced for organizations, companies, branches, users and registers (`EnsureWithinLimit`). |
| F5 | Every `access:` key is declared by a feature enum | ✅ | Checked by script. `suite.audit.view` is declared but not enforced. |
| F6 | Finance tables outside the boundary test | 🔧 | Added. Core `ClientController` still reads `hr__employees` directly (move behind an HR port). |
