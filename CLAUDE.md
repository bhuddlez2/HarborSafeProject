# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

Three independent applications live in one repo, on purpose — keep them that way rather than merging shared code across the boundary unless there's a concrete, simple win:

- `portal/backend/` — Laravel 13 (PHP ^8.3) API. Backs **both** the portal and the public website, and is where the Filament staff panel will live (see "Staff panel" below).
- `portal/frontend/` — Next.js 16 (App Router), server mode. Currently the staff/law-enforcement portal; being reduced to just the two assessment submission wizards as the Filament panel takes over every dashboard.
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
Filament (the `staff` panel at `/staff`, `app/Providers/Filament/StaffPanelProvider.php`) and Shield are installed — see `Filament_CMS_Design.md`:
```
php artisan make:filament-resource ModelName         # generate a panel resource
php artisan shield:generate                          # regenerate permissions after adding resources
```
**Never run `php artisan shield:setup` here.** It runs a bare `migrate` on the default (unreachable) `mariadb` connection, and `--fresh` drops tables through it. Shield/spatie were installed by publishing manually (`vendor:publish --tag=filament-shield-config`, `--tag=permission-config`, `--tag=permission-migrations`), pointing the migration at `Portal`, migrating, then `shield:install staff` — see `Filament_CMS_Design.md` §3.3.
Filament requires `ext-intl`, which is **not currently enabled** in this project's PHP — `composer require` fails outright without it. The DLL ships with XAMPP; uncomment `extension=intl` in `php.ini`.
`composer test` runs `artisan config:clear` then `artisan test`. There's a `vite`/`resources/{css,js}` setup wired via `laravel-vite-plugin`, but it only serves the stock, unused `welcome.blade.php` — the app itself is a JSON API, not Blade-rendered, so you generally don't need `npm run build` here for anything that matters.

**`php artisan test` currently fails one test, and that's expected.** `tests/Feature/ExampleTest.php` is Pest's stock scaffold test (`GET /` should return 200); `routes/web.php` is empty, so `/` genuinely 404s. Known and intentional to leave failing for now — the plan is to update it to test a real route once the API surface is stable, not delete it.

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

### Backend: one Laravel app, four MySQL/MariaDB connections
`config/database.php` defines `mariadb` (default — the connection stock Laravel tables were *intended* for), `Portal` (the app's actual business data), `Feedback` (full access to the website's feedback/resource-request forms and their lookup tables), and `FeedbackPublic` (same physical database as `Feedback`, meant for a restricted, INSERT-only-on-submissions MySQL user — see `Schema_Reference.md` for the exact grants — used by the public submission endpoint once it exists, not by Eloquent models). In practice, **every table including `users` currently lives on the `Portal` connection** — migrations were originally run with `--database=Portal`, which overrides the connection for any migration that doesn't set its own, and `User.php` now explicitly declares `protected $connection = 'Portal'` to match that reality (it didn't used to, which would have made any real login attempt query the wrong, unreachable `mariadb` connection). Every Portal-connection model extends `BaseModel` except `User` (which extends Laravel's `Authenticatable` but still declares `$connection` manually) — `BaseModel` throws at boot time if a subclass omits `$connection`, so that mistake surfaces immediately rather than silently querying the wrong database. When adding a new Portal-connection model, follow the existing pattern (extend `BaseModel`, set `$connection`, use `HasUuids` with a `uniqueIds()` override for the uuid-keyed tables — primary keys here are custom-named, e.g. `AssessmentDocID`, `DocumentID`, `SubmissionID` — not `id`; the auto-increment tables like `users`/`agencies`/`law_enforcement_agents` don't need that).

A fifth and sixth connection (`Content` / `ContentPublic`, for events and newsletters) are designed but not yet built — see `Filament_CMS_Design.md`. Note for anything touching spatie/laravel-permission: it has no documented connection setting and defaults to the `mariadb` connection, which is exactly the unreachable one described above. It's pointed at `Portal` via its migration (`*_create_permission_tables.php`) and the local subclasses `App\Models\Role` / `App\Models\Permission` (set in `config/permission.php`) — always use those, never `Spatie\Permission\Models\*` directly.

**See `Schema_Reference.md` at the repo root for the full current schema** (ER diagram + table-by-table notes) — it's kept in sync with `database/migrations/*.php` and is much easier to read than the migration files themselves. Regenerate it after schema changes rather than trusting this file's prose to stay current.

**Always migrate with `--database=Portal`, regardless of what a migration actually targets.** Portal's `migrations` table is the single canonical ledger for every migration in this project, even ones whose `Schema::connection('Feedback')->...` call routes their DDL elsewhere. Running `migrate --database=Feedback` (or any other) against a database whose own `migrations` table is empty makes Laravel try to replay the *entire* migration history into it, not just the ones meant for that database — this happened once while building the Feedback schema and had to be cleaned up.

**Migrations vs. the live dev database can drift.** Laravel's `migrations` table only tracks which migration *files* have run by name; editing an already-applied migration's contents doesn't retroactively change a database that was migrated before the edit. If you change a migration that has already run somewhere, that environment needs a deliberate `migrate:fresh --database=Portal` (or a new migration), not just a re-run of `migrate`. This bit the project once already — the dev database was rebuilt from scratch to match the migration files, and as of this schema pass it's confirmed reconciled — but it's a recurring risk any time an already-applied migration gets edited, not a one-time fix. If in doubt, check with `php artisan migrate:status --database=Portal` rather than assuming the live schema matches `database/migrations/*.php`.

### The assessment tables are linked, not independent
Two parallel assessment paths share the same `_assessment_answers` table (11 risk-indicator booleans): `PrivateAssessment` (public/anonymous submissions, `belongsTo` `SubmitterInfo` via `SubmissionID` for optional contact info) and `LawEnforcementAssessment` (authenticated law-enforcement submissions, `belongsTo` `User` via `submitted_by`). See `Schema_Reference.md` for the full relationship diagram. **Both flows are wired, but law-enforcement ownership is fake.** The frontend API layer is a single `portal/frontend/app/lib/api.js` — it was briefly split into `app/lib/api/{client,civilian,law-enforcement}.js`, but commit d9a6574 reverted that, dropped `zod`, and flattened the step components. There is no `apiRequest()` helper any more. `submitAssessment()` POSTs risk answers to `/api/assessments`, optionally submitter info to `/api/submitter-info`, then the offender/victim record to `/api/private-assessments`. `submitLawEnforcementAssessment()` (restored in 0326f7f; `app/police/portal/page.js` imports it) POSTs risk answers to `/api/assessments`, then the record to `/api/law-enforcement-assessments` — but `submitted_by` is the client-side `PLACEHOLDER_OFFICER_USER_ID = 1`, and the server doesn't tie it to the caller, so every officer submission is attributed to user 1. Anything depending on per-officer ownership has no trustworthy data until that's fixed, which is scheduled for Filament Phase 11 (derive it from the session server-side; see `Filament_CMS_Design.md` §10.2). Watch the response shapes: `/api/assessments` wraps the created record in `{ data: ... }`, but `/api/law-enforcement-assessments` returns it bare. If you touch field names on any assessment-related table, update the migration, the model's `$fillable`, the controller validation rules, and the frontend payload together — they've fallen out of sync with each other more than once.

### Auth: schema exists, a placeholder exists, real enforcement does not
As of the latest schema pass, `users` has `role` (a native `App\Enums\UserRole` enum: `law_enforcement`/`secretary`/`admin`/`police_admin`, cast — not a raw string), `is_active`, and Fortify-compatible 2FA columns; `law_enforcement_agents` holds badge/agency data for law-enforcement accounts only (1:1, keyed by `user_id`); `law_enforcement_assessment.submitted_by` is a real FK to `users.id`, so per-user ownership is now representable. **None of this is enforced yet** — there are no policies, no role-check middleware, and no login/logout controller. `LawEnforcementAssessment`, `LawEnforcementAgent`, and `Agency` now have `apiResource` routes (see Routing below), but they're wide-open, exactly like every other route — no `auth:sanctum`, no ownership check — so law-enforcement assessment PII is currently world-readable and world-writable. `LawEnforcementAssessmentController::store()` validates `submitted_by` as `exists:Portal.users,id` but nothing ties it to the caller. Do not treat any existing open route as an example of "how auth should look."

**There is also a placeholder login in the portal frontend that authenticates nobody** — `portal/frontend/app/api/auth/login/route.js` accepts any non-empty email and password, sets a hardcoded `session=dev-token` cookie, and derives the role from `email.includes("admin")`. `proxy.js` (correctly named — Next 16 renamed `middleware` to `proxy`) only checks that the cookie exists. It carries a `// TODO: replace with real Laravel backend auth call`. Treat it as scaffolding, never as a pattern, and do not let it reach production.

The decided direction is Filament's own login as the single sign-in, with Shield + spatie/laravel-permission for authorization — see `Filament_CMS_Design.md`. `submitted_by` is what "law enforcement sees only their own submissions" checks against, and `role` is what coarse panel/navigation gating switches on.

### Staff panel (Filament) — installed, mostly not built
Every staff dashboard is to live in one Filament v5 panel inside `portal/backend`, behind Filament's own login: content management (events, newsletters, event categories), the dropdown options behind the public forms, review of feedback/resource-request submissions, assessment review, and user management. Only the two assessment *submission wizards* stay in `portal/frontend` — the law-enforcement one at `app/police/portal/` and the anonymous civilian flow at `app/page.js`. Four roles: `law_enforcement` (own submissions only, the only role that may edit a submitted assessment), `police_admin` (provisions officer accounts, views all law-enforcement submissions and change logs, edits nothing), `secretary` (content and submissions, never assessment PII), `admin` (everything except creating officers and editing assessments).

**See `Filament_CMS_Design.md` at the repo root** for the full design: the verified version matrix, three prerequisites that will otherwise block the install (`ext-intl`, the S3 flysystem adapter, and spatie's default-connection trap), the complete access matrix, the `Content` connection schema, and the ordered implementation phases with verification gates. Phases 1–3 are done (prerequisites, Filament installed, Shield + spatie on `Portal`); the panel has no resources, no `canAccessPanel()`, and no role mapping yet.

### Routing
`bootstrap/app.php` only registers `routes/web.php`, `routes/api.php`, and `routes/console.php`. `routes/api.php` uses `Route::apiResource(...)` for `/assessments`, `/private-assessments`, `/submitter-info`, `/law-enforcement-assessments`, `/law-enforcement-agents`, and `/agencies` — the naming is a little counterintuitive: `/api/assessments` is the risk-indicator table (`AssessmentAnswers`), and `/api/private-assessments` is the offender/victim PII table (`PrivateAssessment`). All of these are open — no auth middleware (see the login section above). Separately, a `Route::prefix('public')` group exposes a deliberately narrow surface for the public website: `GET /api/public/{services,resources,counties}` and throttled (`throttle:10,1`) `POST /api/public/{service-feedback,resource-requests}`. Those controllers route through the restricted `FeedbackPublic` connection, never the full-access `Feedback` one. Still no routes for either change-log table — those are schema-only so far.
