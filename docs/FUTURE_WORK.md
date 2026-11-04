# Future work (owner's list)

Work the owner takes on personally, or that needs accounts and keys only the owner can get. Everything else that is
planned lives in `docs/ROADMAP.md`. Tick items off here as they are done and add a line to the ROADMAP change log.

Last updated: 2026-10-26.

## What remains

### 1. Mobile app parity (`../Baydaq-mobile`)

The server offers its employee API under `/api/v1`, each area switched on per package by a `mobile.*` feature (see
`FeatureCatalog::MOBILE`). The app now has a screen for almost every area, under five tabs (Attendance, Requests,
Updates, Custody, More), built with its own kit (`src/ui.tsx`) and hidden when the package leaves the area out.
What is left:

| Area | API | App screen | Package flag |
|---|---|---|---|
| Sign-in, attendance, leave, custody and stock | ✓ | ✅ | `mobile.attendance`, `mobile.leave` |
| Tasks, breaks, shifts, advances and loans, overtime, expenses, remote days | ✓ | ✅ | `mobile.*` |
| Reviews, record and resignation, violations | ✓ | ✅ | `mobile.reviews`, `mobile.resignation` |
| Insurance, training, announcements, surveys, recognition, messages, calendar | ✓ | ✅ | `mobile.*` |
| Payslips and letters (PDF through a short signed link) | ✓ | ✅ | `mobile.payslips`, `mobile.letters` |
| Selfie on punch | `attendance/punches` | ☐ | `mobile.selfie` |
| Asset stock-takes (scan tags) | `audits/*` | ☐ | `mobile.assets_audit` |
| Announcement attachments | web only | ☐ (shows the file name) | `mobile.announcements` |

Also for the app:

- [x] Hide screens whose `mobile.*` feature is off (the `me` response carries the account's features).
- [x] Renew the sign-in before it runs out (tokens last 90 days; `POST /api/v1/auth/refresh`).
- [ ] Try every screen on a real iPhone and Android phone (Arabic and English); check the right-to-left layout.
- [ ] Push notifications (Expo push, APNs and FCM keys) for approvals and handovers waiting.
- [ ] Offline queue for punches and handovers taken without network.
- [ ] Store builds: an Apple Developer account, a Google Play console account, an EAS project; set `extra.apiUrl`
      in `app.json` to the production API.

### 2. Keys and accounts to provide

Nothing below can be done in code alone. Until each key is set, the matching feature stays hidden or uses a safe
local stand-in, and everything else keeps working.

| What | Setting (`.env`) | Where to get it | Unlocks |
|---|---|---|---|
| Claude (AI) | `ANTHROPIC_API_KEY`, optional `BAYDAQ_AI_MODEL` | Anthropic console (console.anthropic.com) | HR assistant, document capture (iqama/passport scan) |
| WhatsApp | `MESSAGING_DRIVER=whatsapp`, `BAYDAQ_WHATSAPP_URL`, `BAYDAQ_WHATSAPP_TOKEN` | Baydaq's own WhatsApp service | Employee notices, message builder, payment links by WhatsApp, booking reminders |
| Online payments | `PAYMENTS_DRIVER=moyasar`, `MOYASAR_SECRET_KEY`, `MOYASAR_WEBHOOK_SECRET` | Moyasar merchant account (CR, VAT certificate, IBAN, website) | Payment links, booking deposits, portal statement payment |
| ZATCA production | Per e-invoicing unit, OTP from the Fatoora portal | Each company's Fatoora account | Clearance and reporting of real invoices (sandbox works today) |
| Mail | `MAIL_MAILER=ses`, `SES_KEY`, `SES_SECRET`, `SES_REGION` | AWS SES (verified sending domain) | Invitations, password resets, alerts |
| Storage | `HR_DISK=hr-s3`, `ASSETS_DISK=assets-s3`, `AWS_*`, buckets | Laravel Cloud object storage (KSA/GCC region for PDPL) | Private employee files and handover photos in production |
| Search index key | `HR_BLIND_INDEX_KEY` | Generate: `php -r "echo bin2hex(random_bytes(32));"` | National ID search while stored encrypted |
| First admin | `ADMIN_EMAIL`, `ADMIN_PASSWORD` (first run only) | Choose | Baydaq admin area |
| Mobile stores | `app.json` `extra.apiUrl`, EAS credentials | Apple Developer, Google Play, Expo | Installing the employee app |

Before going live also follow the deployment checklist in `docs/SECURITY.md` §2 (`APP_DEBUG=false`,
`SESSION_SECURE_COOKIE=true`, one queue worker and the scheduler, backups of MySQL and the private disks).

After pulling new code on any machine run `composer install` and `npm install`: missing packages (the QR library,
the Claude SDK) make tests and pages fail with "class not found".
