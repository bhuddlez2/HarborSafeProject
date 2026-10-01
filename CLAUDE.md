# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

Three independent applications live in one repo, on purpose — keep them that way rather than merging shared code across the boundary unless there's a concrete, simple win:

- `portal/backend/` — Laravel 13 (PHP ^8.3) API. Backs **both** the portal and the public website, and hosts the Filament staff panel (see "Staff panel" below).
- `portal/frontend/` — Next.js 16 (App Router), server mode. Just the anonymous civilian assessment flow (`app/page.js`). Every officer screen — sign-in, the officer home, the law-enforcement assessment wizard — moved into the Filament panel; the old `/login`, `/admin`, `/police` and `/police/*` URLs redirect there (`next.config.mjs`).
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
**Laravel 13 requires PHP ^8.3, so XAMPP for Windows cannot run this project** — it ships PHP 8.2.12 at most. On Windows, PHP is installed separately from XAMPP (which is then used only for MariaDB); see `PORTAL_SETUP.md`. Filament also requires `ext-intl`, without which `composer install` refuses to resolve; a standalone Windows PHP needs seven extensions enabled by hand (`curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`) plus `extension_dir`, since the zip ships no `php.ini` at all. On Fedora/Debian `intl` is a separate package.
`composer test` runs `artisan config:clear` then `artisan test`. The `vite`/`laravel-vite-plugin` setup **does** matter now: it compiles the staff panel's custom theme (`resources/css/filament/staff/theme.css`, the `->viteTheme()` in `StaffPanelProvider`). Run `npm install && npm run build` in `portal/backend` before using the panel, and again after changing the theme or the panel's Blade views (Tailwind only emits classes it finds in them); `npm run dev` recompiles live. The rest of the app is a JSON API.

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

A fifth and sixth connection exist: `Content` (events, newsletters, event categories and `content_files`, the uploaded-file blobs) and `ContentPublic` (same physical database, intended for a restricted SELECT-only user that **does not exist in any environment yet** — see `Filament_CMS_Design.md` §6.5). `Content` is live and migrated; the public read endpoints that would use `ContentPublic` are not built. Note for anything touching spatie/laravel-permission: it has no documented connection setting and defaults to the `mariadb` connection, which is exactly the unreachable one described above. It's pointed at `Portal` via its migration (`*_create_permission_tables.php`) and the local subclasses `App\Models\Role` / `App\Models\Permission` (set in `config/permission.php`) — always use those, never `Spatie\Permission\Models\*` directly.

**See `Schema_Reference.md` at the repo root for the full current schema** (ER diagram + table-by-table notes) — it's kept in sync with `database/migrations/*.php` and is much easier to read than the migration files themselves. Regenerate it after schema changes rather than trusting this file's prose to stay current.

**Always migrate with `--database=Portal`, regardless of what a migration actually targets.** Portal's `migrations` table is the single canonical ledger for every migration in this project, even ones whose `Schema::connection('Feedback')->...` call routes their DDL elsewhere. Running `migrate --database=Feedback` (or any other) against a database whose own `migrations` table is empty makes Laravel try to replay the *entire* migration history into it, not just the ones meant for that database — this happened once while building the Feedback schema and had to be cleaned up.

**Migrations vs. the live dev database can drift.** Laravel's `migrations` table only tracks which migration *files* have run by name; editing an already-applied migration's contents doesn't retroactively change a database that was migrated before the edit. If you change a migration that has already run somewhere, that environment needs a deliberate `migrate:fresh --database=Portal` (or a new migration), not just a re-run of `migrate`. This bit the project once already — the dev database was rebuilt from scratch to match the migration files, and as of this schema pass it's confirmed reconciled — but it's a recurring risk any time an already-applied migration gets edited, not a one-time fix. If in doubt, check with `php artisan migrate:status --database=Portal` rather than assuming the live schema matches `database/migrations/*.php`.

### The assessment tables are linked, not independent
Two parallel assessment paths share the same `_assessment_answers` table (11 risk-indicator booleans): `PrivateAssessment` (public/anonymous submissions, `belongsTo` `SubmitterInfo` via `SubmissionID` for optional contact info) and `LawEnforcementAssessment` (authenticated law-enforcement submissions, `belongsTo` `User` via `submitted_by`). See `Schema_Reference.md` for the full relationship diagram. **The civilian flow** is `portal/frontend/app/page.js` → `submitAssessment()` in `app/lib/api.js` (the only function left there): it POSTs risk answers to `/api/assessments`, optionally submitter info to `/api/submitter-info`, then the offender/victim record to `/api/private-assessments`. **The law-enforcement flow** is the Filament page `App\Filament\Pages\NewAssessment` (`/staff/police/new-assessment`), which creates the `AssessmentAnswers` row and the `LawEnforcementAssessment` row itself, in one `Portal` transaction, with `submitted_by = auth()->id()` — no API round trip. Its question wording is duplicated in `NewAssessment::QUESTIONS` and `portal/frontend/app/lib/lethality-questions.js`; change both together. Watch the response shapes: `/api/assessments` wraps the created record in `{ data: ... }`, but `/api/law-enforcement-assessments` returns it bare. If you touch field names on any assessment-related table, update the migration, the model's `$fillable`, the controller validation rules, the `NewAssessment` wizard fields, and the civilian frontend payload together — they've fallen out of sync with each other more than once.

### Auth: Filament login and route-level auth exist; fine-grained policies do not
`users` has `role` (a native `App\Enums\UserRole` enum: `law_enforcement`/`secretary`/`admin`/`police_admin`, cast — not a raw string), `is_active`, and Fortify-compatible 2FA columns; `law_enforcement_agents` holds badge/agency data for law-enforcement accounts only (1:1, keyed by `user_id`; `User::officerIdentity()` reads it with the agency name); `law_enforcement_assessment.submitted_by` is a real FK to `users.id`. **Sign-in is Filament's login** at `/staff/login` (`App\Filament\Pages\Auth\Login`, Filament's authentication with the old Next.js page's look), gated by `User::canAccessPanel()` (`is_active` plus a valid role); each panel page gates itself with `canAccess()` on `role`. On the API, the law-enforcement, agent and agency routes and every non-`store` action on the civilian tables sit behind `auth:sanctum` (see Routing). `LawEnforcementAssessmentController::store()` requires a `law_enforcement` user (403 otherwise) and sets `submitted_by` from `$request->user()`, ignoring the body. **Not built yet:** Shield permissions and model policies — so no per-record ownership checks (`update()` on that controller still accepts `submitted_by` from any authenticated caller), and nothing issues Sanctum tokens (`User` has no `HasApiTokens`; `statefulApi()` is off), so in practice no client can call the `auth:sanctum` routes today.

The old placeholder Next.js login (`app/api/auth/login/route.js`, which accepted any credentials and set `session=dev-token`) and `proxy.js` are deleted — don't resurrect either.

Shield + spatie/laravel-permission are the decided authorization layer — see `Filament_CMS_Design.md`. `submitted_by` is what "law enforcement sees only their own submissions" checks against, and `role` is what coarse panel/navigation gating switches on.

### Staff panel (Filament) — officer screens and content management built
Every staff dashboard lives in one Filament v5 panel inside `portal/backend`, behind Filament's own login. Only the anonymous civilian flow (`portal/frontend/app/page.js`) stays in Next.js. Three sidebar groups, each with its own address space:

- **Content management** (admin, secretary) — three Filament **clusters**, which render their resources as tabs across the top of each page: `Content` (`/staff/content/{events,newsletters,categories}`), `Submissions` (`/staff/submissions/{service-feedback,resource-requests}`, both read-only — they are statements from the public) and `Form options` (`/staff/form-options/{services,resource-types,counties}`, the dropdown choices behind the public forms, sharing one `LookupResource` base).
- **Assessments** (admin, police_admin) — `Assessment review` (`/staff/assessment-review`), all law-enforcement submissions, view-only.
- **Officer Portal** (law_enforcement, police_admin — *not* admin) — Home (`Pages/Police.php`, slug `police`), New assessment (the LAP wizard, officers only), My Assessments (a static mockup with hardcoded rows), Search Records ("Coming soon"), Account.

**Clusters must be registered with `->discoverClusters(...)`** in `StaffPanelProvider`; an unregistered cluster resolves as a class but registers no routes, so every resource inside it 404s. A cluster root redirects to the first tab the user can open (`Cluster::mount()`), and hides itself when every resource inside refuses the user.

Still not built: user management, Shield permissions, model policies, the change-log observer, the public content API.

The panel's look (dark sidebar, `#5C0F8B`, Tailwind gray, Nunito/Montserrat, no topbar, no dark mode) comes from the custom theme plus `StaffPanelProvider`; `SESSION_LIFETIME` is 15 in `.env.example` because the officer Home promises a 15-minute lock — but check your own `.env`, which has been seen at 120, and note that it is an *idle* timeout, not a re-auth on every navigation (see `Filament_CMS_Design.md` §12). Four roles: `law_enforcement` (own submissions only, the only role that may edit a submitted assessment), `police_admin` (provisions officer accounts, views all law-enforcement submissions and change logs, edits nothing), `secretary` (content and submissions, never assessment PII), `admin` (content, submissions and assessment *review* — but **not** the officer portal, and it cannot create officers or edit assessments; admin is deliberately not a superset).

**Sign-in and landing.** Everyone sees the same login screen at `/staff/login`. Where they land is decided in exactly one place, `App\Filament\StaffLanding` — admin/secretary to content management, officers/police admins to the officer portal — consulted by both `StaffLoginResponse` (after sign-in) and `RedirectStaffHome` (opening `/staff` directly). Don't reach for `$panel->homeUrl()`: it only sets the sidebar brand link and does not affect either redirect.

**Uploads are database rows, not files on a disk.** Event images and newsletter PDFs live in `content_files` on the `Content` connection, written through `App\Filament\Forms\Components\DatabaseFileUpload` and served by `ContentFileController` — publicly at `/api/public/content-files/{file}` (only while a *published* event or newsletter points at the file) and to staff at `/staff/files/{file}`. Two rules to respect: `App\Models\ContentFile` has a global scope excluding the `contents` blob from every query (use `->contents()` for the bytes, never the attribute), and uploads are capped at 4 MB/8 MB because `max_allowed_packet` is 16 MB and caps a single row in both directions. `events.image_path` / `newsletters.file_path` are kept but unused, so a move to R2/B2 stays a config change.

**See `Filament_CMS_Design.md` at the repo root** for the full design: the verified version matrix, the prerequisites (`ext-intl` and spatie's default-connection trap — `storage:link` is no longer one), the complete access matrix, the `Content` connection schema including `content_files`, the navigation layout (§13), and the ordered implementation phases with verification gates. Done: Phases 1–6, 8 (content management), 11 (officer screens and wizard) and 12 (Next.js cleanup); 7 and 13 partly.

**The access matrix is enforced by test.** `tests/Feature/Staff/PanelAccessTest.php` writes §5 out as a table and asserts each role reaches exactly its own row — it exists because `Police`, `MyAssessments` and `SearchRecords` had all quietly granted `admin`, which put the whole Officer Portal in an admin's sidebar. If you change who sees what, that file is the place it has to agree. Two gotchas for authenticated tests: cleanup sweeps on the fixed `peststaff` prefix (so a fatal-killed run self-heals, but don't run these in parallel), and `UploadedFile::fake()` is unusable here — `fake()->image()` needs GD, which isn't installed, and `fake()->create()` writes zero bytes while reporting a size. Use `Illuminate\Http\Testing\File` with a real `tmpfile()`.

### Routing
`bootstrap/app.php` only registers `routes/web.php`, `routes/api.php`, and `routes/console.php`. `routes/api.php` uses `Route::apiResource(...)` for `/assessments`, `/private-assessments`, `/submitter-info`, `/law-enforcement-assessments`, `/law-enforcement-agents`, and `/agencies` — the naming is a little counterintuitive: `/api/assessments` is the risk-indicator table (`AssessmentAnswers`), and `/api/private-assessments` is the offender/victim PII table (`PrivateAssessment`). Only `store` on the three civilian tables (`/assessments`, `/private-assessments`, `/submitter-info`) is public, because the anonymous civilian flow needs it; their other actions, and every action on the three law-enforcement tables, are behind `auth:sanctum`. Separately, a `Route::prefix('public')` group exposes a deliberately narrow surface for the public website: `GET /api/public/{services,resources,counties}` and throttled (`throttle:10,1`) `POST /api/public/{service-feedback,resource-requests}`. Those controllers route through the restricted `FeedbackPublic` connection, never the full-access `Feedback` one. Also in that group: `GET /api/public/content-files/{file}`, which streams an event image or newsletter PDF out of the database — and only while a *published* record points at it, since `content_files` has no published flag of its own to consult. Its staff-side twin is `GET /staff/files/{file}` in `routes/web.php`, which is guarded by `App\Http\Middleware\EnsureStaffPanelAccess` rather than `auth`: Laravel's `auth` redirects to a route named `login` that doesn't exist here (sign-in is Filament's), so `auth` would turn an anonymous request into a 500. Still no routes for either change-log table — those are schema-only so far. The staff panel's routes live under `/staff` (Filament registers them itself), with content management under `/staff/content`, `/staff/submissions` and `/staff/form-options`.
