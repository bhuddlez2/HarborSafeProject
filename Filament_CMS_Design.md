# Filament staff panel — design and setup guide

**Status:** design settled, not yet implemented. Nothing in this document has been built.

## 1. What this is

HarborSafe needs one authenticated home for every staff dashboard: content management
(events, newsletters, the dropdown options behind the public forms), review of the
public feedback and resource-request submissions, and review of lethality assessments.
The decision is to build that with [Filament](https://filamentphp.com) v5 inside
`portal/backend`, rather than hand-building it in Next.js.

This document is written for whoever implements it, including Claude agents working in
this repo. **The decisions in §4 are settled architecture, not open options.** They came
out of a long design pass; the rationale is recorded so you can understand them, not so
you can re-open them. If something here turns out to be wrong in practice, raise it —
don't quietly do something else, because several of these choices are load-bearing for
each other.

Read `CLAUDE.md` and `Schema_Reference.md` first. This document assumes both.

## 2. Compatibility — verified, not assumed

Confirmed by running a real `composer require --dry-run` against this project's actual
`composer.lock`, not from documentation:

| | This project | Filament v5.8.4 requires |
|---|---|---|
| Laravel | 13.4.0 | `illuminate/support: ^11.28\|^12.0\|^13.0` |
| PHP | `^8.3` | `^8.2` |
| Livewire | not installed | pulls `v4.4.5` |

The full dependency tree resolves cleanly against the existing Sanctum 4 / Pest 4 /
Tinker 3 setup with no conflicts. `bezhansalleh/filament-shield` 4.3.1 supports Filament
`^4.0|^5.0` on Laravel `^13`, so Shield does not constrain the version choice.

One thing not to be alarmed by: `composer audit` reports 34 advisories across 10
packages. That was verified against the **current** lock file, before Filament. It is
pre-existing and unrelated — worth addressing on its own schedule, but it is not
something Filament introduced.

## 3. Three prerequisites that will block you

Each of these was confirmed against this machine and this lock file. Handle all three
before writing any code.

### 3.1 `ext-intl` is not enabled

`filament/support` requires it, and composer refuses to resolve without it:

```
- filament/support[v5.7.6, ..., v5.8.4] require ext-intl * -> it is missing from
  your system. Install or enable PHP's intl extension.
```

The DLL is already present at `C:\xampp\php\ext\php_intl.dll`; line 925 of
`C:\xampp\php\php.ini` just has `;extension=intl` commented out. Uncomment it and
restart.

**Every teammate's machine and the deploy target needs this.** It is a one-line fix with
an intimidating failure mode, so put it in the README rather than letting each person
rediscover it.

### 3.2 `league/flysystem-aws-s3-v3` is not installed

File storage is S3 (§4). Laravel does not ship the S3 adapter — `laravel/framework`
only *suggests* it at `^3.25.1`. Verified absent from `composer.lock`. You need:

```
composer require league/flysystem-aws-s3-v3
```

### 3.3 spatie/laravel-permission will silently target the wrong database

**This is the single most likely thing to go wrong in the whole install.**

spatie/laravel-permission has no documented setting for choosing a database connection.
Its published migration and its `Role`/`Permission` models use the application's
**default** connection.

In this project the default connection is `mariadb`, and per `CLAUDE.md` that connection
is effectively unreachable — every real table, `users` included, lives on `Portal`. If
you publish spatie's migration and run it without intervening, the permission tables
either fail to create or get created somewhere nothing else can see them, and every
permission check queries a database that isn't there.

Before running spatie's migration:

- Edit the published migration to target `Portal` explicitly (`protected $connection =
  'Portal';` and `Schema::connection('Portal')->...`), matching how every other
  migration in `database/migrations/` already does it.
- Extend spatie's `Role` and `Permission` models with local subclasses that declare
  `protected $connection = 'Portal';`, and point `config/permission.php` at your
  subclasses.
- Run it with `--database=Portal` like everything else (§6.4).

Verify before moving on: `php artisan migrate:status --database=Portal` lists the
permission tables, and a `php artisan tinker` role lookup returns without a connection
error.

## 4. Settled decisions

**Panel scope.** Filament is the single authenticated home for every staff dashboard —
admin, secretary, police admin and officer. It owns events, newsletters, event
categories, the form dropdown options, submission review, assessment review and user
management.

**What stays in Next.js.** Only the two assessment *submission wizards*: the
law-enforcement one at `portal/frontend/app/police/portal/` and the anonymous civilian
flow at `portal/frontend/app/page.js`. Everything else in `portal/frontend` is retired
(§9). The civilian flow cannot move into Filament because it is anonymous and public.

**Auth: Filament's built-in login, and nothing else.** There is exactly one sign-in
screen. The Next.js `/login` page, the `/api/auth/*` route handlers and `proxy.js` are
deleted. This replaces a placeholder that never authenticated anyone (§10).

**Officer identity for the Next.js wizard.** The wizard still has to write
`submitted_by`, so it reads Filament's session cross-origin:

- `SESSION_DOMAIN` set to the parent domain shared by the panel and the portal.
- `supports_credentials` flipped to `true` in `portal/backend/config/cors.php` — it is
  currently `false`.
- The portal origin listed in CORS (already env-driven via `PORTAL_URL`).
- The wizard's fetches sending `credentials: 'include'`. They currently send neither
  credentials nor a token.

Cookies ignore port, so a single `SESSION_DOMAIN=localhost` works across
`localhost:3001` and `localhost:8000` in local development. In production both must sit
under one registrable domain — e.g. `portal.example.org` and `api.example.org` with
`SESSION_DOMAIN=.example.org`. Filament links officers out to the wizard; the wizard
redirects to the Filament login when no session resolves.

**Authorization: Filament Shield + spatie/laravel-permission.**

**Role storage: both systems, deliberately.** `users.role` stays as the coarse identity
— it is what `canAccessPanel()` and navigation switch on. spatie handles fine-grained
per-resource permissions on top. Be honest that this means two things describe access
and they can drift; keep `role` authoritative for "which dashboard is this person" and
spatie authoritative for "may they perform this action on this resource."

**New role: `police_admin`.** `App\Enums\UserRole` goes from three cases to four. The
column is already a string, so this is an enum change, not a migration.

**Filament version: v5.**

**Newsletters ship with events** — same page, same connection, same access rule.

**Schema shape: flattened relational columns**, with a Laravel API Resource re-nesting
them into the JSON the website already consumes (§6).

**Connection: a new dedicated `Content` database**, with a paired `Content` /
`ContentPublic` arrangement mirroring `Feedback` / `FeedbackPublic`.

**File storage: S3 / object storage** for event images and newsletter PDFs. Needs §3.2,
plus a bucket and credentials before anyone can work on content locally.

## 5. Access matrix — four roles

```
                       LE      PoliceAdm  Secretary  Admin
Panel access           yes     yes        yes        yes
Events / Newsletters   —       —          edit       edit
Event categories       —       —          edit       edit
Form options           —       —          edit       edit
  (services/resources/counties)
Service feedback       —       —          view       view
Resource requests      —       —          view       view
Civilian assessments   —       —          —          view
LE assessments         own     view all   —          view
LE change logs         own     view all   —          view
---------------------------------------------------------
Create officers        —       YES        —          NO
Create police admins   —       —          —          YES
Create secretaries     —       —          —          YES
Create admins          —       —          —          YES
```

Four rules that are easy to get wrong and must be enforced explicitly:

1. **Only the submitting officer may edit an assessment.** Matched on
   `law_enforcement_assessment.submitted_by`. `police_admin` and `admin` are view-only.
   Nobody else can change a submitted form, ever.
2. **Admin cannot create officers.** Officer account provisioning belongs exclusively to
   `police_admin`. Admin creates secretaries, admins and police admins.
3. **Secretary never sees assessment PII** — not civilian, not law-enforcement.
4. **`is_active = false` denies panel access regardless of role.**

Rule 2 is the one most likely to be "helpfully" widened by someone who assumes admin is
a superset. It is not.

## 6. Schema to add

Three tables on the `Content` connection: `events`, `newsletters`, `event_categories`.

### 6.1 The columns are already specified

The public Events & News page is **already built** and reads from mock JSON. Those mocks
are the schema spec — match them field for field:

- `website/frontend/src/app/lib/mock-events.json`
- `website/frontend/src/app/lib/mock-newsletters.json`
- `website/frontend/src/app/lib/content.js` — the `getEvents()` / `getNewsletters()`
  seam the API replaces. **Its consumers must not change.** Only the loader inside those
  two functions changes; every component downstream keeps working.

### 6.2 `events`

`EventID` (uuid PK), `title`, `summary` (nullable), `description` (json — a paragraph
array), `starts_at`, `ends_at` (nullable), `all_day` (bool), `recurrence` (nullable free
text, e.g. "Annually in September"), `location_name`, `location_address`,
`location_is_virtual` (bool), `location_virtual_note`, `image_path`, `image_alt`,
`registration_url`, `registration_label`, `category_id` (FK → `event_categories`),
`is_published` (bool), `is_cancelled` (bool).

### 6.3 `newsletters`

`NewsletterID` (uuid PK), `title`, `issue_date`, `summary` (nullable), `file_path`,
`file_size_bytes` (nullable), `file_pages` (nullable), `is_published` (bool).

### 6.4 Conventions you must follow

These come from `CLAUDE.md` and are the things an agent unfamiliar with this repo gets
wrong:

- Extend `BaseModel` and declare `protected $connection` — `BaseModel` throws at boot if
  you forget, which is deliberate.
- `HasUuids` with a `uniqueIds()` override for the uuid-keyed tables, since primary keys
  here are custom-named rather than `id`.
- **Always migrate with `--database=Portal`**, regardless of which connection the
  migration actually targets. Portal's `migrations` table is the single canonical ledger
  for the whole project. Running `migrate --database=Content` against a database whose
  own `migrations` table is empty makes Laravel replay the *entire* migration history
  into it. This has already happened once on this project, with the Feedback schema.

### 6.5 `ContentPublic` grants

The website reads content; every add, change and delete happens in the panel. Mirror the
`FeedbackPublic` pattern documented in `Schema_Reference.md` — SELECT on the three
tables, nothing else, no write access of any kind:

```sql
-- Replace CHANGE_ME with a strong, generated password. Scope the host portion
-- to the actual application server in production rather than '%'.
CREATE USER 'harborsafe_content_public'@'%' IDENTIFIED BY 'CHANGE_ME';

GRANT SELECT ON content_db.events            TO 'harborsafe_content_public'@'%';
GRANT SELECT ON content_db.newsletters       TO 'harborsafe_content_public'@'%';
GRANT SELECT ON content_db.event_categories  TO 'harborsafe_content_public'@'%';

FLUSH PRIVILEGES;
```

Credentials go in `DB_USERNAME_CONTENT_PUBLIC` / `DB_PASSWORD_CONTENT_PUBLIC`.

### 6.6 File upload security

`image_path` and `file_path` accept staff uploads, which makes them the highest-risk
surface in this feature. A Phase 1 audit earlier in the project established these
requirements; they predate the move to S3 and still apply:

- **Allow-list both MIME type and extension**, and check them independently — a
  browser-supplied MIME type is not trustworthy on its own. Images: PNG/JPEG/WebP.
  Newsletters: PDF only.
- **Randomize stored filenames.** Never persist the user-supplied name as the storage
  key; keep it in a separate display column if it needs showing.
- **Reject executables outright**, including files that merely rename an executable to
  an allowed extension. Validate by content, not by name.
- **Decide public vs. private deliberately.** Event images are genuinely public.
  Newsletters may or may not be — if any issue should not be world-readable by URL,
  they belong in a private bucket served through a controlled route with an expiring
  signed URL, not a public one.
- **Cap file size** in both Filament's `FileUpload` and the server-side request
  validation. Client-side limits alone are not a control.

## 7. Public content API

`GET /api/public/events` and `GET /api/public/newsletters` — published records only,
read through `ContentPublic`, shaped by a Laravel API Resource that reconstructs the
nested `location` / `image` / `registration` / `file` objects exactly as the mock JSON
has them. Add them to the existing `Route::prefix('public')` group in
`portal/backend/routes/api.php` alongside the feedback endpoints.

No officer-scoped API is needed. Officers review and edit inside Filament, so policies
handle it and no new authenticated endpoints are required.

## 8. Change logging

`assessment_change_log` and `assessment_answer_change_log` already exist, already have
models with working relations, and have never been used. They are **field-level**: one
row per changed field, with `ChangeField`, `PreviousValue`, `NewValue`, `ChangedBy` and
`TimeStamp`.

Implement as an Eloquent observer walking `getDirty()` on update, writing one row per
changed attribute.

Sizing is already verified safe: every editable column on `law_enforcement_assessment`
is `varchar(50)` or smaller, so the log's `varchar(50)` value columns cannot truncate,
and the longest field name is `OffenderVictimRelationship` at 26 characters against a
`varchar(32)` `ChangeField`.

`police_admin` and `admin` read these logs. Only the owning officer generates them,
since only the owning officer can edit (§5, rule 1).

## 9. Next.js cleanup

Delete once the panel is live:

- `portal/frontend/app/login/`
- `portal/frontend/app/api/auth/login/`, `portal/frontend/app/api/auth/logout/`
- `portal/frontend/proxy.js`
- `portal/frontend/app/admin/` — an empty placeholder reading "Admin tools and reports
  will appear here"; that job is now the Filament panel's
- `portal/frontend/app/police/page.js` — the landing page, replaced by the panel

Keep: `portal/frontend/app/police/portal/` (the LE wizard) and
`portal/frontend/app/page.js` (the civilian flow).

**Deleting `proxy.js` removes the only route protection in the portal.** It is correctly
named — Next 16.2.4 ships `PROXY_FILENAME` alongside `MIDDLEWARE_FILENAME`, so `proxy.js`
is the current convention, not a mistake. But once it is gone, `/police/portal` must do
its own session check and redirect to the Filament login, or it ships unprotected.

## 10. Current state of the portal code

Two things you will run into. Both are statements of fact with paths, not criticism.

### 10.1 The existing login authenticates nobody

`portal/frontend/app/api/auth/login/route.js` carries
`// TODO: replace with real Laravel backend auth call`. It accepts **any** non-empty
email and password, sets a hardcoded `session=dev-token` cookie, and derives the role
from `email.includes("admin")`. `proxy.js` only checks that the cookie exists.

Retiring it per §9 closes this. Worth stating plainly so nobody preserves it out of
caution — it would be a serious hole if it ever reached production.

### 10.2 Officer submissions land in the anonymous civilian table — hard blocker

`portal/frontend/app/police/portal/page.js:5` imports `submitAssessment` — the
**civilian** function — which POSTs to `/api/private-assessments`.
`submitLawEnforcementAssessment()` was deleted along with
`portal/frontend/app/lib/api/law-enforcement.js` during the frontend restructure.

The consequences:

- `law_enforcement_assessment` receives no rows at all.
- `submitted_by` is never written.
- Officer identity is still three hardcoded empty strings at
  `portal/frontend/app/police/portal/page.js:17-19`.

**The officer dashboard, the owner-only edit rule and the entire change log have no data
until this is fixed.** This is the largest single dependency in the plan. Phase 11 is
blocked on it. Fixing it means restoring an LE submit path that POSTs to
`/api/law-enforcement-assessments` with `submitted_by` derived from the authenticated
session rather than the request body — deriving it server-side also closes a spoofing
hole, since `LawEnforcementAssessmentController::store()` currently validates
`submitted_by` as `exists:Portal.users,id` but never ties it to the caller.

## 11. Implementation phases

Each phase has a gate. Don't start the next one until the gate passes.

**1 — Prerequisites.** Enable `ext-intl` (§3.1); `composer require
league/flysystem-aws-s3-v3` (§3.2).
*Gate:* `php -r "var_dump(extension_loaded('intl'));"` prints `true`.

**2 — Install Filament.** `composer require filament/filament:"^5.0"`, then
`php artisan filament:install --panels`.
*Gate:* a panel provider appears in `portal/backend/bootstrap/providers.php`, which
currently lists only `AppServiceProvider`.

**3 — Shield + spatie.** Install, publish config, and **point the migration and models at
`Portal`** — re-read §3.3 before running anything.
*Gate:* `php artisan migrate:status --database=Portal` lists the permission tables, and a
tinker role lookup returns without a connection error.

**4 — Add `police_admin`** to `App\Enums\UserRole`.
*Gate:* all four cases resolve and cast correctly on `User`.

**5 — Content connection.** `Content` + `ContentPublic` in `config/database.php`, env
vars, grants from §6.5.
*Gate:* `php artisan db:show --database=Content`.

**6 — Content schema.** Migrations and models per §6.
*Gate:* `php artisan migrate:status --database=Portal`.

**7 — Auth and access.** Filament login, `User implements FilamentUser` with
`canAccessPanel()`, Shield permissions encoding the §5 matrix, Shield super-admin plus a
seeder creating one user per role for local development.
*Gate:* each of the four roles sees exactly its matrix row; admin is refused when
creating an officer; `police_admin` has no edit action on assessments.

**8 — Resources.** Events, newsletters, event categories, form options, submissions,
assessments, users.
*Gate:* CRUD works; assessments expose no edit action except to the owning officer; and an
upload renamed to an allowed extension is still rejected (§6.6).

**9 — Change-log observer** (§8).
*Gate:* editing an owned assessment writes one row per changed field, attributed to the
editing user.

**10 — Content API + website cutover.** Public endpoints and API Resource (§7), then
repoint `content.js` and delete the two mock files.
*Gate:* the response shape matches `mock-events.json` field for field, and the events
page renders real data with no component changes.

**11 — Cross-origin session.** `SESSION_DOMAIN`, `supports_credentials`, CORS origins,
wizard sending `credentials: 'include'`.
*Gate:* an officer logs into Filament, opens `/police/portal`, submits, and the row lands
in `law_enforcement_assessment` with the correct `submitted_by`.
**Blocked on §10.2.**

**12 — Next.js cleanup** per §9.
*Gate:* no portal route is reachable without a Filament session.

**13 — Tests.** Extend the pattern already documented in `portal/backend/tests/Pest.php`
— run-token tagging, `afterEach` cleanup, deliberately no `RefreshDatabase` — with
per-role user helpers and an `actingAs` wrapper. Cover panel access per role, the
four-role matrix, the change-log observer and the public content endpoints. Note there
is currently **no** scaffolding for authenticated or Portal-connection tests at all, so
this is new ground.
*Gate:* `php artisan test` passes with one pre-existing failure.

> `tests/Feature/ExampleTest.php` fails by design — it is Pest's stock scaffold asserting
> `GET /` returns 200, and `routes/web.php` is empty. Leave it failing; don't "fix" it.
> Note that installing Filament adds routes under the panel path but does not add `/`, so
> this stays failing. See `CLAUDE.md`.

## 12. Open items

Neither was settled during the design pass. Decide before implementing:

- **Whether §10.2 gets fixed as part of this work** or handed back to whoever owns the
  wizard. Phase 11 cannot be verified until it is done by someone.
- **Production domain layout.** The cross-origin session design in §4 assumes the panel
  and the portal share a registrable domain. If they end up on unrelated domains, the
  shared-cookie approach does not work and the officer identity mechanism needs
  revisiting — most likely by moving the wizard into Filament after all.
