# Security, data integrity and race conditions

Status legend: ✅ checked and OK · 🔧 was an issue, fixed (with regression test where noted) · ⚠️ open issue ·
📝 must be set at deployment · ➖ not applicable.

Last full review: 2026-10-25 (code review of every module + `tests/Feature/Security/HardeningTest.php`).
Re-run this review after any large feature, and update the tables.

## 1. Security checklist

### 1.1 Injection and input

| Topic | Status | Notes |
|---|---|---|
| SQL injection | ✅ | All raw fragments (`selectRaw`, `orderByRaw`, `whereRaw`) are fixed strings or bound values; no `DB::statement`/`unprepared`; no user-chosen `orderBy` column. |
| XSS (React) | ✅ | Only `dangerouslySetInnerHTML` is a constant (`TechStrip.jsx`). React escapes everything else. |
| XSS (Blade/PDF) | ✅ | Every `{!! !!}` goes through `e()` first (invoice and letter PDFs). |
| Mass assignment | ✅ | Models declare `#[Fillable]`; requests are validated; no `$request->all()`. |
| Command injection, unsafe deserialization, `eval` | ✅ | None in `app/`. |
| SSRF | ✅ | Outbound HTTP only to configured hosts (ZATCA, Moyasar, WhatsApp, Anthropic). |
| Open redirect | ✅ | Payment return URLs are set by the server. |
| AI prompt injection | ✅ (low) | Assistant tools are read-only and re-check the user's reach on the server; document scans fill a form a person reviews. |

### 1.2 Authentication and sessions

| Topic | Status | Notes |
|---|---|---|
| Password hashing | ✅ | bcrypt via Laravel. |
| Password policy | ⚠️ low | `min(8)->letters()->numbers()`. Add `mixedCase()` and `uncompromised()` (needs outbound access to the breach API). |
| Web login brute force | ✅ | 5/min per email+IP (Fortify). Forgot-password throttled. |
| Mobile API login brute force | ⚠️ low | `throttle:10,1` per IP only; add a per-account limiter. |
| Two-factor authentication | ⚠️ medium | Not enabled. Enable Fortify 2FA, at least for Baydaq admins and account owners. |
| API tokens (Sanctum) | 🔧 / ⚠️ | Leavers are now signed out everywhere and refused by the API (`ResolvesEmployee`, test `a_leaver_loses_the_mobile_app`). Tokens still never expire (`config/sanctum.php` `expiration => null`): set an expiry (e.g. 90 days) with refresh. |
| Forced password change | ✅ | `password.changed` middleware; invited users must set a password. |
| CSRF | ✅ | No exceptions; webhooks are outside the `web` group. |

### 1.3 Transport (man-in-the-middle)

| Topic | Status | Notes |
|---|---|---|
| HTTPS links in production | 🔧 | `URL::forceScheme('https')` in production (`AppServiceProvider`). |
| HSTS | 🔧 | `SecurityHeaders` middleware sends `Strict-Transport-Security` on HTTPS. |
| Secure session cookie | 📝 | Set `SESSION_SECURE_COOKIE=true` in production `.env` (kept off locally so http dev works). |
| Clickjacking / MIME sniffing / referrer | 🔧 | `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` (test checks headers). |
| Content-Security-Policy | ⚠️ low | Not set yet (Vite + inline Inertia data need a nonce-based policy). |
| Trusted proxies | 📝 | `trustProxies(at: '*')` is right behind Laravel Cloud's load balancer, which overwrites `X-Forwarded-For`. On any other host, list the proxy IPs, or clients can fake their IP and dodge IP rate limits. |
| Mobile app certificate pinning | ➖ | For the future native app. |

### 1.4 Authorization and tenancy

| Topic | Status | Notes |
|---|---|---|
| Cross-tenant access | ✅ | `TenantScope` fails closed; tenant middleware runs before route-model binding; public pages use random 32/40-char tokens + `runFor`. |
| Record-level reach (HR, Finance, Privacy, Join, Careers, self-service) | ✅ | Every action checks the record against the user's reach or ownership. |
| Catalog records of another company (POS, reservations, membership plans) | 🔧 | Editing now also checks the record's current company (test `catalog_editors_cannot_reach_another_companys_products`). |
| Revoking access | 🔧 | Same reach rule as granting; only an owner removes an owner (test `a_branch_access_admin_cannot_revoke_the_owner`). |
| Editing roles | ⚠️ medium | `SaveAccessRole` checks the editor holds each key somewhere; roles apply account-wide. Require account-wide `suite.access.update` to edit roles. |
| Supplier bank details | 🔧 | Only account-wide bill approvers can change a supplier IBAN. |
| Segregation of duties | 🔧 / ⚠️ | Nobody approves their own expense claim (test). Still open: payroll creator = approver, bill creator = approver are allowed. |
| Hiring into a branch | 🔧 | The target branch must be in the user's reach. |
| Plan suspended / no plan | ⚠️ medium | `hasFeature` ignores `ClientPlan.status`, and an account with no plan gets every feature (see AUDIT). |
| Admin area | ✅ | Spatie permissions; system admin gate. |

### 1.5 Files and personal data

| Topic | Status | Notes |
|---|---|---|
| Upload validation | ✅ | `mimes` + `max` on every upload (documents, receipts, CVs, logos, selfies). |
| Storage | ✅ | `hr` and `assets` disks are private; files served through authorized controllers or short signed URLs. Names generated by Laravel (no path traversal). |
| Encryption at rest | ✅ | National ID, IBAN (employees, suppliers), document numbers, ZATCA private keys, service API keys use `encrypted` casts; ID searched by blind index. |
| Sensitive data in pages | ✅ | ID/IBAN only with `view_national_id`/`view_iban`; IBAN masked otherwise. Letter verification page shows salary to the token holder (intended: banks verify salary letters). |
| PDPL | ✅ / ⚠️ | Request register, export, erasure, marketing consent per registration. Open: public QR sign-up records consent for any phone without an OTP; invoicing buyers are not covered by export/erase. |
| Logging | ✅ | No personal data logged. 📝 production must set `APP_DEBUG=false`, `LOG_LEVEL=warning`. |
| Secrets | ✅ | `.env` ignored; `.env.example` has empty keys. |

### 1.6 Payments and webhooks

| Topic | Status | Notes |
|---|---|---|
| Fake payment driver | 🔧 | Refused in production (driver and `/pay/{token}` page). 📝 set `PAYMENTS_DRIVER=moyasar`. |
| Webhook authentication | 🔧 / ⚠️ | Empty secret now refused. Open: re-fetch the payment from Moyasar and compare the amount before marking paid. |
| Public endpoints throttling | ✅ / ⚠️ low | Forms throttled (join, careers, booking). Some GET pages (menu, booking page, member card, payment return) are not throttled. |

### 1.7 Dependencies

| Topic | Status | Notes |
|---|---|---|
| Composer | ✅ | `composer audit` clean. |
| npm | ⚠️ low | 2 advisories in build tooling only (not shipped). Run `npm audit fix`. |

## 2. Deployment checklist (production `.env`)

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
PAYMENTS_DRIVER=moyasar
MOYASAR_WEBHOOK_SECRET=<long random>
HR_BLIND_INDEX_KEY=<separate from APP_KEY>
ADMIN_EMAIL / ADMIN_PASSWORD   (first run only)
```

Also: a queue worker and the scheduler (`php artisan schedule:work` or cron) on **one** server; backups of MySQL
and of the private disks; rotate `APP_KEY` only with a re-encryption plan (encrypted columns depend on it).

## 3. Race conditions and data integrity

**Rule for every one-time action** (pay, approve, issue, close, decide): inside `DB::transaction`, re-read the row
with `lockForUpdate()` **as the first statement**, re-check its status, then write. Never trust the status on the
route-bound model (it may be stale). MySQL runs REPEATABLE READ: a plain read before the lock freezes the snapshot.

| # | Case | Status | How it is protected |
|---|---|---|---|
| R1 | POS order paid twice (two cashiers / double tap) | 🔧 | Order row locked, status re-checked (test). |
| R2 | Invoice issued twice; hash chain | 🔧 / ⚠️ | Invoice row locked after the unit lock. Open: the ZATCA call happens inside the DB transaction; if a later step fails, ZATCA has accepted an invoice the database rolled back. Plan: commit counter/hash/submission first, do follow-ups after. |
| R3 | Supplier bill overpaid by two payments | 🔧 | Bill locked; balance re-checked (test). |
| R4 | Same source posted twice to the ledger | 🔧 | Unique index `(source, source_id, is_reversal)`; duplicate silently ignored (test). |
| R5 | Payroll approved / marked paid twice | 🔧 | Run locked, status re-checked. |
| R6 | POS shift closed twice (double shortage deduction) | 🔧 | Shift locked. |
| R7 | Expense claim approved and refused at once, paid twice | 🔧 | Claim locked. |
| R8 | Purchase order billed twice | 🔧 | Order locked inside a transaction. |
| R9 | Two open departures for one employee | 🔧 | Employee row locked during submission. |
| R10 | Monthly deduction cap overrun by concurrent deductions | 🔧 | Employee row locked; sum is a locking read. |
| R11 | Scheduled jobs overlapping (duplicate reminders, movements, recharges) | 🔧 | `withoutOverlapping()->onOneServer()` on every job. |
| R12 | Booking double-booking of the same slot | ⚠️ high | A plain read happens before the lock in `BookAppointment`. Fix: lock first (or READ COMMITTED). |
| R13 | Seat limit when hiring | ⚠️ medium | Count after lock can miss a concurrent activation inside the hire transaction. Make the count a locking read. |
| R14 | Rebuilding a payroll run while it is approved | ⚠️ medium | Lock the run in `BuildPayrollRun`. |
| R15 | Two open advances / loans; repayment overpay | 🔧 / ⚠️ | Request locks the employee and checks one open request per kind (advance, loan). Open: repayments still need an advance lock. |
| R25 | Overtime approved twice; yearly cap passed by two approvals | 🔧 | Overtime row locked and status re-checked; employee row locked before the cap sum. |
| R16 | Break limit per branch exceeded by two approvals | ⚠️ medium | Wrap `BreakFlow::decide` in a transaction with a lock. |
| R17 | Plan limits (companies, branches, users) exceeded by concurrent creates | ⚠️ low | Lock the client plan row before counting. |
| R18 | Booking checkout twice (two ZATCA receipts) | ⚠️ medium | Lock the booking. |
| R19 | Void vs pay, edit vs pay of a POS order | ⚠️ medium | Atomic status transitions (`update … where status = open`). |
| R20 | Customer created twice for one phone | ✅ (low) | Unique `(client_id, phone)` stops duplicates; the loser gets an error → use `createOrFirst`. |
| R21 | Count+1 numbering (POs, members, assets, letters) | ⚠️ low | Can deadlock on the first row or collide after deletes; move to a locked per-company counter. |
| R22 | Wallet balance | ✅ | Customer row locked, balance re-read, never negative, idempotent by unique key. |
| R23 | Membership overlap, check-ins, stock ledger, open shifts | ✅ | Locked and/or unique keys. |
| R24 | One payroll run per company and month; one review per employee per round; one remote request per day | ✅ | Unique keys. |

## 4. Database rules

- Money: `decimal(12–14,2)` (invoice unit price 14,4) and BigDecimal in code; never floats. (ZATCA XML sums still
  use `(float)` for display totals: low risk, move to BigDecimal.)
- Every tenant table has `client_id` with a foreign key to `clients`.
- Cross-module ids are kept without foreign keys on purpose (modules stay separable); the ER diagram draws them as
  dotted links. Inside a module, use real foreign keys.
- Long index names: give MySQL explicit short names (64-char limit).
- Timezone: the app runs in UTC; business dates must use the company timezone (see AUDIT, POS dates).
