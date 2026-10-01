# Filament staff panel — design and setup guide

**Status:** design settled. Done (§11): Phases 1–6, 11 (officer screens and wizard in
Filament) and 12 (Next.js cleanup); Phase 7 partly. Phases 8, 9, 10 and 13 are not yet
built.

Two things the Content work still needs from outside the codebase: the restricted
`harborsafe_content_public` MySQL user (§6.5) does not exist in any environment, and
`php artisan storage:link` has not been proven on the Ionos deploy target (§3.2).

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

On Fedora/Debian it is a separate package (`php-intl`). On Windows it is a `php.ini`
line — the DLL ships in PHP's `ext/` directory, commented out as `;extension=intl`.

**Related, and worse on Windows: XAMPP cannot run this project at all.** XAMPP for
Windows ships PHP 8.2.12 at most, and `laravel/framework` v13 requires `^8.3` (as do
`spatie/laravel-permission` 8.3.0 and Pest 4), so there is no version to lower to. PHP
has to be installed separately from XAMPP, which is then useful only for MariaDB. A
standalone Windows PHP also ships **no `php.ini` at all** and has every optional
extension off, so `intl` is one of eight lines to uncomment rather than one — the others
being `extension_dir`, `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql` and `zip`.

**Every teammate's machine and the deploy target needs this.** The failure modes are
intimidating and don't name the cause clearly, so the full Windows path is written up in
`PORTAL_SETUP.md` rather than left for each person to rediscover.

### 3.2 `storage:link` has to work on the deploy target

File storage is the local public disk (§4), which means uploads land in
`storage/app/public` and are served through a symlink at `public/storage` created by:

```
php artisan storage:link
```

Run it once per environment, and **again after any deploy that rebuilds the tree** — the
symlink is not part of the repo.

The open risk is the deploy target: the website and portal are going on **a shared server
through Ionos** (see `Public_Forms_Backend_Design.md`), and shared hosts vary in whether
they permit symlinks and in how the document root is arranged. Confirm `storage:link`
works there, and that the plan has disk headroom, before building the upload UI. If it
turns out symlinks are blocked, see §4 for the fallback — it is a config change, not a
rewrite.

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

**Do not run `shield:setup` in this project.** It publishes spatie's migration and then
immediately calls a bare `migrate` — default connection, before you can edit anything —
and with `--fresh` it also runs `DROP TABLE` statements through the default connection.
Publish by hand instead:

```
php artisan vendor:publish --tag=filament-shield-config
php artisan vendor:publish --tag=permission-config
php artisan vendor:publish --tag=permission-migrations
```

Then edit the migration and models as above, migrate, and only *then* run
`php artisan shield:install staff`. `shield:install` registers the plugin on the panel
but also quietly runs `shield:generate --resource=RoleResource`, which writes a
`super_admin` role and its permissions — so the tables must already exist on `Portal`.

As implemented: Shield 4.3.1 resolved spatie/laravel-permission **8.3.0**, which still has
no connection setting. The subclasses are `App\Models\Role` and `App\Models\Permission`
(extending spatie's models, not `BaseModel`, so they declare `$connection` manually like
`User`), and `User` uses `HasRoles`. spatie's cache (`store => 'default'`) needs no
change — `CACHE_STORE=database` with `DB_CACHE_CONNECTION=Portal` already puts it on
Portal. When probing in tinker, query through `App\Models\Role`, not
`Spatie\Permission\Models\Role`.

Verify before moving on: `php artisan migrate:status --database=Portal` lists the
permission tables, and a `php artisan tinker` role lookup returns without a connection
error.

## 4. Settled decisions

**Panel scope.** Filament is the single authenticated home for every staff dashboard —
admin, secretary, police admin and officer. It owns events, newsletters, event
categories, the form dropdown options, submission review, assessment review and user
management.

**What stays in Next.js.** Only the anonymous civilian assessment at
`portal/frontend/app/page.js`. It cannot move into Filament because it is anonymous and
public. Everything officer-facing — the officer home, My Assessments, Search Records,
Account and the law-enforcement assessment *wizard* — lives in the panel, carried over
from the former Next.js officer portal (`app/police/`, `components/portal/`) with the
same copy, layout and colours. Everything else in `portal/frontend` is retired (§9).

**Auth: Filament's built-in login, and nothing else.** There is exactly one sign-in
screen, restyled to look like the old Next.js one (`App\Filament\Pages\Auth\Login`).
The Next.js `/login` page, the `/api/auth/*` route handlers and `proxy.js` are deleted.
This replaced a placeholder that never authenticated anyone (§10).

**Officer identity: server-side, from the session.** The wizard is a Filament page
(`App\Filament\Pages\NewAssessment`), so it writes `submitted_by = auth()->id()`
directly, inside one `Portal` transaction with the `_assessment_answers` row. The
cross-origin session design this section used to describe — a shared `SESSION_DOMAIN`,
`supports_credentials`, the Next.js wizard sending `credentials: 'include'` — is
**dropped**: nothing outside the panel needs the staff session any more. The
`/api/law-enforcement-assessments` routes remain, behind `auth:sanctum`, and their
`store()` likewise takes `submitted_by` from `$request->user()`, never the body.

**Look and feel.** The panel's theme (`resources/css/filament/staff/theme.css`,
compiled by Vite) carries over the officer portal's design for every role: `#5C0F8B`
primary, Tailwind's gray, Nunito/Montserrat, a dark full-height sidebar, no topbar,
no dark mode. The session lifetime is 15 minutes (`SESSION_LIFETIME`), because the
officer home tells officers their session locks after that long.

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

**File storage: the local public disk** for event images and newsletter PDFs —
`storage/app/public` served via `php artisan storage:link` (§3.2). No cloud account, no
credentials, and nothing for a teammate to wait on before working on content locally.

This reverses an earlier decision to use Amazon S3. S3 was dropped for two reasons. The
reasons to use object storage — files surviving ephemeral containers, several servers
sharing one store, CDN-scale traffic — none apply to a single persistent Ionos server
holding a few dozen images and a quarterly PDF. And it required an AWS account, which is
ruled out.

**Fallback if `storage:link` cannot work on Ionos:** use an S3-compatible provider that
is not AWS — Cloudflare R2 (no egress charge), Backblaze B2, or DigitalOcean Spaces.
Laravel's `s3` driver speaks to all of them by setting an endpoint, so the change is
`composer require league/flysystem-aws-s3-v3`, the disk config, and
`FILESYSTEM_DISK`. **No application code changes**: `image_path` and `file_path` hold a
path on whichever disk is configured, so the models and migrations are already
storage-agnostic. Do not reach for this until §3.2 has actually been tested on the
deploy target.

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

GRANT SELECT ON content_app_db.events            TO 'harborsafe_content_public'@'%';
GRANT SELECT ON content_app_db.newsletters       TO 'harborsafe_content_public'@'%';
GRANT SELECT ON content_app_db.event_categories  TO 'harborsafe_content_public'@'%';

FLUSH PRIVILEGES;
```

Credentials go in `DB_USERNAME_CONTENT_PUBLIC` / `DB_PASSWORD_CONTENT_PUBLIC`.

### 6.6 File upload security

`image_path` and `file_path` accept staff uploads, which makes them the highest-risk
surface in this feature. A Phase 1 audit earlier in the project established these
requirements; they predate the storage decision and apply whichever disk is used:

- **Allow-list both MIME type and extension**, and check them independently — a
  browser-supplied MIME type is not trustworthy on its own. Images: PNG/JPEG/WebP.
  Newsletters: PDF only.
- **Randomize stored filenames.** Never persist the user-supplied name as the storage
  key; keep it in a separate display column if it needs showing.
- **Reject executables outright**, including files that merely rename an executable to
  an allowed extension. Validate by content, not by name.
- **Decide public vs. private deliberately.** Event images are genuinely public. On
  the local public disk (§4) *everything* in `storage/app/public` is world-readable by
  URL to anyone who guesses the filename — which is why randomized names matter. If any
  newsletter issue must not be openly reachable, it does not belong on that disk: put it
  on the private disk (`storage/app/private`) and serve it through a controlled route
  that checks authorization, rather than linking the file directly.
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

## 9. Next.js cleanup — done

`portal/frontend` keeps **only the civilian flow**: `app/page.js` and what it imports
(`app/lib/{api,validation,validators,lethality-questions}.js`, `app/layout.js`,
`app/globals.css`). `api.js` is down to `submitAssessment()`.

Deleted (Phase 12): `app/login/`, `app/api/auth/`, `proxy.js`, `app/admin/`, the whole
officer side under `app/police/` (layout, home, wizard), and `components/portal/`
(sidebar, mobile header, icons). `next.config.mjs` redirects the old URLs — `/login` to
`/staff/login`; `/admin`, `/police` and `/police/*` to `/staff` — built from
`NEXT_PUBLIC_API_URL`.

With `proxy.js` gone the portal has no route protection, and needs none: nothing left in
it is behind a login.

## 10. Former state of the portal code (resolved)

Kept for the record; both are closed.

### 10.1 The old login authenticated nobody — removed

`portal/frontend/app/api/auth/login/route.js` accepted **any** non-empty email and
password, set a hardcoded `session=dev-token` cookie, and derived the role from
`email.includes("admin")`; `proxy.js` only checked that the cookie existed. Both were
deleted in Phase 12. Filament's login is the only sign-in.

### 10.2 Officer identity — resolved

Officer submissions used to send `submitted_by` from the client as a hardcoded
`PLACEHOLDER_OFFICER_USER_ID = 1`, so every one was attributed to user 1, and
`LawEnforcementAssessmentController::store()` never tied it to the caller.

Now the wizard is a Filament page and sets `submitted_by = auth()->id()` server-side
(Phase 11); the placeholder is gone with `submitLawEnforcementAssessment()`. The API's
`store()` sits behind `auth:sanctum`, accepts only `law_enforcement` users (403
otherwise) and takes `submitted_by` from `$request->user()`, ignoring any value in the
body. Officer name, badge and agency are read from `law_enforcement_agents` / `agencies`
(`User::officerIdentity()`).

Still open: `update()` on that controller still accepts `submitted_by` from an
authenticated caller. Owner-only editing belongs to the policies in Phases 7–8.

## 11. Implementation phases

Each phase has a gate. Don't start the next one until the gate passes.

**1 — Prerequisites.** Enable `ext-intl` (§3.1); run `php artisan storage:link` and
confirm it works on the deploy target, not just locally (§3.2).
*Gate:* `php -r "var_dump(extension_loaded('intl'));"` prints `true`, and a file written
to `storage/app/public` is reachable under `/storage/...`.

**2 — Install Filament.** `composer require filament/filament:"^5.0"`, then
`php artisan filament:install --panels`.
*Gate:* a panel provider appears in `portal/backend/bootstrap/providers.php`, which
currently lists only `AppServiceProvider`.

**3 — Shield + spatie.** *(Done.)* Install, publish config, and **point the migration and
models at `Portal`** — re-read §3.3 before running anything, especially the
`shield:setup` warning.
*Gate:* `php artisan migrate:status --database=Portal` lists the permission tables, and a
tinker role lookup returns without a connection error.

**4 — Add `police_admin`** to `App\Enums\UserRole`. *(Done.)*
*Gate:* all four cases resolve and cast correctly on `User`.

**5 — Content connection.** *(Done, except the restricted MySQL user.)* `Content` + `ContentPublic` in `config/database.php`, env
vars, grants from §6.5.
*Gate:* `php artisan db:show --database=Content`.

**6 — Content schema.** *(Done.)* Migrations and models per §6.
*Gate:* `php artisan migrate:status --database=Portal`.

**7 — Auth and access.** Filament login, `User implements FilamentUser` with
`canAccessPanel()`, Shield permissions encoding the §5 matrix, Shield super-admin plus a
seeder creating one user per role for local development.
*(Partly done: `canAccessPanel()` — `is_active` plus a valid role — and the local-only
`LocalStaffUserSeeder` exist, with two placeholder pages (`/staff/content`,
`/staff/police`) gated by `canAccess()`. Shield permissions and the super-admin are
still to do.)*
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

**11 — Officer screens and wizard in Filament.** *(Done.)* Carry the officer portal over
from `portal/frontend` faithfully: a custom Vite theme for the panel; Home (the existing
`Police` page), My Assessments and Search Records (placeholders — they had no design),
Account (Filament's profile page), all in an "Officer Portal" navigation group; the LAP
wizard as a `Wizard` page writing both assessment rows in one `Portal` transaction with
`submitted_by = auth()->id()`; the login page restyled. Lock the assessment API down:
LE/agent/agency routes fully behind `auth:sanctum`, and only `store` public on the three
civilian tables. The cross-origin session previously planned here is dropped (§4).
*Gate:* an officer signs in, opens New assessment, submits, and both rows land linked
with the correct `submitted_by`; secretaries get 403 on officer pages; the protected API
routes return 401 unauthenticated.

**12 — Next.js cleanup** per §9. *(Done.)*
*Gate:* no officer screen is served by `portal/frontend`; its old URLs redirect to the
panel.

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

- ~~**Whether §10.2 gets fixed as part of this work.**~~ Resolved: yes. The routing half
  was fixed in `0326f7f`; the identity half is folded into Phase 11.

- ~~**Production domain layout.**~~ No longer a constraint. It only mattered for the
  cross-origin session, which was dropped when the wizard moved into Filament (§4).
  The panel, API and portal can sit on any domains.

Still undecided:

- **View logging.** The officer Home page says "Records are in My Assessments. Each view
  is logged with your name and a timestamp." — carried over verbatim from the old
  portal. Nothing logs views: it is neither designed nor built. The change log (§8)
  records edits only. Either design and build view logging (per record, per user, with a
  timestamp) before My Assessments ships, or change that copy.
