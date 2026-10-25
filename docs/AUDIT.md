# Conflicts and overlaps between subsystems

Owner's rule: **one shared concept per idea** — no second system that can disagree with the first.
This file lists where the code follows that and where it doesn't yet. Status: ✅ fine · 🔧 fixed · ⚠️ open.

Last review: 2026-10-25.

## 1. Duplicate concepts

| # | Overlap | Status | Resolution |
|---|---|---|---|
| D1 | Invoicing has its own buyer table (`inv__customers`) not linked to the shared customer | ⚠️ | Add `platform_customer_id`, resolve via `Customers::identify`, add an Invoicing `CustomerData` adapter (so privacy export/erase covers buyers). |
| D2 | Members (subscriptions) matched by a different phone rule than customers | ⚠️ | Use `Customer::normalizePhone` everywhere (one helper in Platform; WhatsApp has a copy too). |
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
| B2 | Leave, remote day and shift can all exist for one employee on one date | ⚠️ | One `HrCalendar::conflicts(employee, date)` check used by leave, remote requests, shifts and open-shift claims; booking availability should respect it. |
| B3 | All deductions share the Labor-Law cap; staff meals can block disciplinary penalties; violations call `SaveDeduction` directly (no spreading to later months) | ⚠️ | Route violations through `PayrollAdjustments`; treat staff meals as purchases outside the penalty cap. |
| B4 | POS receipts counted twice in the ledger (sale + invoice) | 🔧 | POS-issued invoices carry `source = pos.order` and aren't posted again. |
| B5 | Booking and membership receipts posted as receivables though paid at the counter | 🔧 | Posted to cash / card / bank by payment means. Deposit paid online earlier is not posted separately (open). |
| B6 | Ledger gaps | ⚠️ | Not posted yet: wallet top-ups and loyalty credit, online booking deposits, POS refunds/credit notes (reverse cash, wallet, loyalty), advance payouts, asset purchases, buyer side of intercompany recharges. |
| B7 | Daily business dates | ⚠️ | POS numbers/dates orders with UTC `today()`; between 00:00–03:00 Riyadh time orders get yesterday's date. Use one `OrgDirectory::today(companyId)`. |
| B8 | VAT handling differs (fraction vs percent, allowed values, per-line rounding in POS vs invoicing) | ⚠️ | One VAT helper in Platform; ledger posts invoice VAT when an invoice exists. |

## 3. Feature flags and permissions

| # | Issue | Status | Resolution |
|---|---|---|---|
| F1 | Mobile API screens didn't require the matching web feature | 🔧 | Tasks, breaks, shifts, advances, reviews now need both `mobile.*` and `hr.*`. |
| F2 | Violation answers gated by `mobile.resignation` | 🔧 | Now `hr.violations`. |
| F3 | `mobile.payslips`, `mobile.letters` have no API route; `mobile.assets_audit` and `mobile.selfie` are never checked | ⚠️ | Build the endpoints or remove the flags; gate the assets API; make one switch for selfies. |
| F4 | Plan status (suspended) ignored by features; limits other than HR seats not enforced everywhere | ⚠️ | `hasFeature` should return false for a suspended plan; enforce limits on registers/users/branches. |
| F5 | Every `access:` key is declared by a feature enum | ✅ | Checked by script. `suite.audit.view` is declared but not enforced. |
| F6 | Finance tables outside the boundary test | 🔧 | Added. Core `ClientController` still reads `hr__employees` directly (move behind an HR port). |
