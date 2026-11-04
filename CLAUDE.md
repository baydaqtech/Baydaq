@AGENTS.md

# Baydaq

Saudi business suite (HR, POS, invoicing with ZATCA, reservations, memberships, assets, finance, customers)
sold as packages to owners with organizations → companies → branches. Laravel 13 modular monolith +
Inertia/React, Arabic and English. The full handbook is imported below.

@docs/PROJECT.md

## Documentation is part of every change

- **Feature added/changed/removed** → update `docs/PROJECT.md` (what exists) and add a line to the change log in
  `docs/ROADMAP.md`; move finished items out of "Planned".
- **Migration added/changed** → `php artisan migrate && php artisan docs:er`, commit `docs/ER.md`.
  `tests/Feature/Docs/ErDiagramTest.php` fails while the diagram is stale.
- **Security- or concurrency-relevant change** → update the tables in `docs/SECURITY.md`; add a regression test
  to `tests/Feature/Security/HardeningTest.php`.
- **A second way of doing something that already exists** (customers, branches, ledger, deductions, tasks) →
  don't; extend the shared one. Record any unavoidable overlap in `docs/AUDIT.md`.
- New ideas from the owner go to `docs/ROADMAP.md` with a priority.

| File | Contents |
|---|---|
| `docs/PROJECT.md` | Architecture, modules, features, shared concepts, packages, test accounts |
| `docs/ER.md` | ER diagram per module (generated: `php artisan docs:er`) |
| `docs/SECURITY.md` | Security checklist, race conditions, deployment settings |
| `docs/AUDIT.md` | Conflicts and overlaps between subsystems |
| `docs/ROADMAP.md` | Change log, planned fixes, ideas per subsystem, MVP scope |
| `docs/FUTURE_WORK.md` | The owner's own list: mobile app parity, keys and accounts to provide |

## Rules that bite

- Modules talk only through `Ports`, `Data`, `Events` (Platform also `Enums`, `Access`, `Tenancy`,
  `Models\Concerns`, `Http\Controllers\Concerns`). `tests/Architecture/ModuleBoundariesTest.php` enforces it.
- One-time state changes (pay, approve, issue, close, decide): lock the row with `lockForUpdate()` as the **first**
  statement inside `DB::transaction` and re-check the status. Eloquent `value('status')` returns the enum, compare
  to enum cases.
- Seeders run without model events: set `client_id` explicitly when an action may run in a seeder.
- `Model::shouldBeStrict()` outside production: eager-load relations; fill only `#[Fillable]` fields
  (`firstOrNew([...])` with guarded columns throws).
- Ledger postings go through the `Ledger` port with a unique `source`; never write `finance__*` tables from
  another module.
- MySQL index names are limited to 64 characters: name long composite indexes explicitly.
- Every feature: package key in `FeatureCatalog` (+ `suite.catalog.*` labels in ar/en), permission keys in the
  module's `*Feature` enum (+ labels), Arabic and English copy, a feature test.
- UI: no browser pop-ups (`alert`/`confirm`/`prompt`), native date/time/colour/file inputs or `title` tooltips. Use
  the Baydaq kit in `components/platform` (`DatePicker`, `MonthPicker`, `TimeField`, `DateTimePicker`, `SelectInput`,
  `Combobox`, `Autocomplete`, `ColorPicker`, `FileField`, `CodeField`, `ConfirmAction`, `Hint`) and `components/ui`.
- Tests need more memory than the host PHP gives: run them in Sail (`docker compose exec -T laravel.test php artisan
  test --compact`).
- Before pushing: `vendor/bin/pint --dirty --format agent`, `npx tsc --noEmit`, `php artisan test --compact`,
  `npm run build`.
