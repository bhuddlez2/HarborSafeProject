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
`config/database.php` defines `mariadb` (default — stock Laravel tables: `users`, `sessions`, `cache`, `jobs`, `personal_access_tokens`), `Portal` (the app's actual business data), and `Feedback` (placeholder, not yet referenced by any model/migration — remove if the feedback feature isn't happening). Every model under `app/Models/` that isn't `User` sets its own `protected $connection = 'Portal'` explicitly — `BaseModel` (which they all extend except `User`) throws at boot time if a subclass omits `$connection`, so that mistake surfaces immediately rather than silently querying the wrong database. When adding a new Portal-connection model, follow the existing pattern (extend `BaseModel`, set `$connection`, use `HasUuids` with a `uniqueIds()` override since primary keys here are custom-named, e.g. `AssessmentDocID`, `DocumentID`, `SubmissionID` — not `id`).

**Migrations vs. the live dev database can drift.** Laravel's `migrations` table only tracks which migration *files* have run by name; editing an already-applied migration's contents doesn't retroactively change a database that was migrated before the edit. If you change a migration that has already run somewhere, that environment needs a deliberate `migrate:fresh --database=Portal` (or a new migration), not just a re-run of `migrate` — this bit the project once already (`_`-prefixed table renames and `submitter_info` column changes landed in migration files without any local dev DB being rebuilt to match, until it was reconciled). Don't assume the live schema matches `database/migrations/*.php` without checking with `php artisan migrate:status --database=Portal`.

### The three assessment-related models are linked, not independent
`PrivateAssessment` (offender/victim PII) `belongsTo` both `AssessmentAnswers` (11 risk-indicator booleans, via `AssessmentDocID`) and `SubmitterInfo` (submitter contact info, via `SubmissionID`). The submit flow in `portal/frontend/app/lib/api.js`'s `submitAssessment()` reflects this: it POSTs to `/api/assessments` first (creates the risk-answer row, returns `AssessmentDocID`), optionally POSTs to `/api/submitter-info` (returns `SubmissionID`), then POSTs both IDs to `/api/private-assessments` to create the linking record. If you touch field names on any of these three tables, update the migration, the model's `$fillable`, the controller's validation rules, and this frontend payload together — they've fallen out of sync with each other more than once.

### No authentication or authorization exists yet
There's no role column on `users`, no policies, no middleware beyond Laravel/Sanctum defaults, and Sanctum's `personal_access_tokens` table exists but nothing issues or checks tokens. Every route in `routes/api.php` is currently open (the only auth-gated route is `GET /api/user` via stock `auth:sanctum`, which nothing can reach yet since nothing logs in). Do not treat any existing route as an example of "how auth should look" — that work hasn't started. When it does: three roles (`law_enforcement`, `secretary`, `admin`), law-enforcement users may only ever see their own submissions, and ownership must be enforced server-side (there's currently no column linking a submission to the user who created it — that's a schema gap, not just a missing check).

### Routing
`bootstrap/app.php` only registers `routes/web.php`, `routes/api.php`, and `routes/console.php`. `routes/api.php` uses `Route::apiResource(...)` for `/assessments`, `/private-assessments`, and `/submitter-info` — the naming is a little counterintuitive: `/api/assessments` is the risk-indicator table (`AssessmentAnswers`), and `/api/private-assessments` is the offender/victim PII table (`PrivateAssessment`).
