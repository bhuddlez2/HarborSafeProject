# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

Three independent applications live in one repo, on purpose — keep them that way rather than merging shared code across the boundary unless there's a concrete, simple win:

- `portal/backend/` — Laravel 13 (PHP ^8.3) API. Backs **both** the portal and the public website.
- `portal/frontend/` — Next.js 16 (App Router), server mode. The authenticated staff/law-enforcement portal.
- `website/frontend/` — Next.js 16 (App Router), static export (`output: 'export'`). The public informational site. Has no backend calls today and must never gain direct DB access — it should only ever reach a narrow, purpose-built slice of the Laravel API.

## Commands

### Backend (`portal/backend/`)
```
composer install
cp .env.example .env && php artisan key:generate   # first-time setup
php artisan serve                                   # dev server
php artisan test                                    # full Pest/PHPUnit suite
php artisan test --filter=testName                  # single test
php artisan test tests/Feature/SomeTest.php          # single file
php artisan migrate                                  # run migrations (default connection)
php artisan migrate --database=Portal                # run migrations against the Portal connection
php artisan migrate:all                              # custom command: creates DBs then migrates the Portal connection (app/Console/Commands/MigrateAll.php)
php artisan db:create                                # custom command: creates the Portal DB if missing (app/Console/Commands/CreateDatabases.php)
```
`composer test` runs `artisan config:clear` then `artisan test`. There's a `vite`/`resources/{css,js}` setup wired via `laravel-vite-plugin`, but it only serves the stock, unused `welcome.blade.php` — the app itself is a JSON API, not Blade-rendered, so you generally don't need `npm run build` here for anything that matters.

### Portal frontend (`portal/frontend/`) and website frontend (`website/frontend/`)
Same script names in both, run from within each directory:
```
npm install
npm run dev      # dev server
npm run build    # next build (website/frontend exports fully static; portal/frontend does not — see next.config.mjs)
npm run lint      # eslint
```
No test runner is configured in either frontend's package.json.

### End-to-end tests (repo root)
```
npm install
npx playwright test              # runs tests/*.spec.js against website/frontend (see playwright.config.js)
npx playwright test -g "name"    # run tests matching a title
```
Playwright's `webServer` boots `website/frontend` only (`npm run dev --prefix website/frontend`, or `serve website/frontend/out` in CI) — it does not exercise the portal or the backend.

**Root `package.json` is not a runnable app.** It declares `next` as a dependency and has `dev`/`build`/`start` scripts left over from before the repo was split into `website/frontend` and `portal/frontend`, but there's no `app/`/`pages/` directory at root — those scripts don't work. Root's real jobs are hosting the shared ESLint flat config (`eslint.config.js`, which lints both frontends and the Playwright specs together) and the Playwright config/tests. Use `npm run lint` at root only if you want the combined lint pass; otherwise lint from inside the specific frontend you're changing.

## Architecture

### Backend: one Laravel app, three MySQL/MariaDB connections
`config/database.php` defines `mariadb` (default — the connection stock Laravel tables were *intended* for), `Portal` (the app's actual business data), and `Feedback` (placeholder, not yet referenced by any model/migration — remove if the feedback feature isn't happening). In practice, **every table including `users` currently lives on the `Portal` connection** — migrations were originally run with `--database=Portal`, which overrides the connection for any migration that doesn't set its own, and `User.php` now explicitly declares `protected $connection = 'Portal'` to match that reality (it didn't used to, which would have made any real login attempt query the wrong, unreachable `mariadb` connection). Every Portal-connection model extends `BaseModel` except `User` (which extends Laravel's `Authenticatable` but still declares `$connection` manually) — `BaseModel` throws at boot time if a subclass omits `$connection`, so that mistake surfaces immediately rather than silently querying the wrong database. When adding a new Portal-connection model, follow the existing pattern (extend `BaseModel`, set `$connection`, use `HasUuids` with a `uniqueIds()` override for the uuid-keyed tables — primary keys here are custom-named, e.g. `AssessmentDocID`, `DocumentID`, `SubmissionID` — not `id`; the auto-increment tables like `users`/`agencies`/`law_enforcement_agents` don't need that).

**See `Schema_Reference.md` at the repo root for the full current schema** (ER diagram + table-by-table notes) — it's kept in sync with `database/migrations/*.php` and is much easier to read than the migration files themselves. Regenerate it after schema changes rather than trusting this file's prose to stay current.

**Migrations vs. the live dev database can drift.** Laravel's `migrations` table only tracks which migration *files* have run by name; editing an already-applied migration's contents doesn't retroactively change a database that was migrated before the edit. If you change a migration that has already run somewhere, that environment needs a deliberate `migrate:fresh --database=Portal` (or a new migration), not just a re-run of `migrate`. This bit the project once already — the dev database was rebuilt from scratch to match the migration files, and as of this schema pass it's confirmed reconciled — but it's a recurring risk any time an already-applied migration gets edited, not a one-time fix. If in doubt, check with `php artisan migrate:status --database=Portal` rather than assuming the live schema matches `database/migrations/*.php`.

### The assessment tables are linked, not independent
Two parallel assessment paths share the same `_assessment_answers` table (11 risk-indicator booleans): `PrivateAssessment` (public/anonymous submissions, `belongsTo` `SubmitterInfo` via `SubmissionID` for optional contact info) and `LawEnforcementAssessment` (authenticated law-enforcement submissions, `belongsTo` `User` via `submitted_by`). See `Schema_Reference.md` for the full relationship diagram. The frontend submit flow in `portal/frontend/app/lib/api.js`'s `submitAssessment()` currently only wires up the `PrivateAssessment` path (POSTs risk answers, optionally submitter info, then the offender/victim record) — it does not yet call the law-enforcement endpoints, because those don't have routes/controllers yet (see below). If you touch field names on any assessment-related table, update the migration, the model's `$fillable`, any controller validation rules, and the frontend payload together — they've fallen out of sync with each other more than once.

### Schema exists for login; the login system itself does not
As of the latest schema pass, `users` has `role` (a native `App\Enums\UserRole` enum: `law_enforcement`/`secretary`/`admin`, cast — not a raw string), `is_active`, and Fortify-compatible 2FA columns; `law_enforcement_agents` holds badge/agency data for law-enforcement accounts only (1:1, keyed by `user_id`); `law_enforcement_assessment.submitted_by` is a real FK to `users.id`, so per-user ownership is now representable. **None of this is enforced yet** — there are no policies, no role-check middleware, and no login/logout controller. `routes/api.php` still has zero authenticated routes for any of the new tables. Do not treat any existing open route as an example of "how auth should look." When building the actual login flow: Sanctum is already installed, `submitted_by` is what "law enforcement sees only their own submissions" should check against, and `role` is what coarse route/policy gating should switch on.

### Routing
`bootstrap/app.php` only registers `routes/web.php`, `routes/api.php`, and `routes/console.php`. `routes/api.php` uses `Route::apiResource(...)` for `/assessments`, `/private-assessments`, and `/submitter-info` — the naming is a little counterintuitive: `/api/assessments` is the risk-indicator table (`AssessmentAnswers`), and `/api/private-assessments` is the offender/victim PII table (`PrivateAssessment`). There are no routes yet for `LawEnforcementAssessment`, `Agency`, `LawEnforcementAgent`, or either change-log table — those are schema-only so far.
