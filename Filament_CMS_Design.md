# Filament staff panel — design and setup guide

**Status:** design settled. Done (§11): Phases 1–6, 8 (content management), 11 (officer
screens and wizard in Filament) and 12 (Next.js cleanup); Phase 7 partly. Phases 9, 10
and 13 are not yet built.

The content side of the panel is now built and under test: events, newsletters,
categories, the two public-form submission lists and the three form-option lookups, plus
an admin/police-admin assessment review page. See §13 for the navigation layout and §4
for the file-storage reversal that came with it.

One thing the Content work still needs from outside the codebase: the restricted
`harborsafe_content_public` MySQL user (§6.5) does not exist in any environment. The
`storage:link` dependency is **gone** — uploads are database rows now (§4), which is what
removed it.

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

### 3.2 `storage:link` — no longer required (resolved)

**This prerequisite is retired.** It used to say that uploads landed in
`storage/app/public` behind a `php artisan storage:link` symlink, and that the Ionos
shared host had never been proven to allow that. Uploads are now rows in
`content_files` (§4, §6.7), so there is no symlink, no document-root arrangement to
confirm, and nothing to re-run after a deploy.

What replaced it is a different constraint, and it is a real one: **`max_allowed_packet`
caps a single upload**, server-side. It is 16 MB on the dev box and on a default MariaDB
install, and an oversized blob fails both on INSERT and on every later SELECT, with an
error that does not name the cause.
`App\Filament\Forms\Components\DatabaseFileUpload` therefore caps uploads at 4 MB for
an image and 8 MB for a PDF. Do not raise those without raising `max_allowed_packet` on
the server first.

`storage:link` is still worth running on a new environment — Laravel's own tooling
assumes it, and `PORTAL_SETUP.md` lists it — but nothing in this feature depends on it
any more.

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

**File storage: the database.** Event images and newsletter PDFs are rows in
`content_files` on the `Content` connection (§6.7), not files on a disk.

This is the second reversal on this question, so the whole history is worth stating
plainly:

1. **Amazon S3** — dropped. The reasons to want object storage (files surviving
   ephemeral containers, several servers sharing one store, CDN-scale traffic) apply to
   none of this, and it required an AWS account, which is ruled out.
2. **The local public disk** — dropped. It depended on `php artisan storage:link`
   working on a shared Ionos host, which was never proven and sat as an open blocker in
   §3.2 for exactly that reason.
3. **The database** — chosen. It removes the deploy-target unknown entirely, needs no
   cloud account, and makes a database dump a complete backup rather than half of one.
   Authorization also becomes ordinary application code: a file is served by a
   controller that can check whether the caller should see it, which a public disk
   cannot do at all.

The volume is what makes this reasonable — a few dozen images and a quarterly PDF, on one
persistent server. It is not a general recommendation, and two things keep it honest:

- **`max_allowed_packet` caps a single file** (§3.2). Uploads are capped at 4 MB for an
  image and 8 MB for a PDF, well under the 16 MB default.
- **The blob is never in a listing query.** `App\Models\ContentFile` adds a global scope
  that selects every column except `contents`, so a table page costs kilobytes rather
  than megabytes. There is a test asserting the generated SQL, because removing the
  scope would not break anything visibly until a page timed out in production.

**Fallback, if the database turns out not to suit the deploy target:** an S3-compatible
provider that is not AWS — Cloudflare R2 (no egress charge), Backblaze B2, or
DigitalOcean Spaces. Laravel's `s3` driver speaks to all of them via an endpoint setting.
The retreat stays cheap because **`events.image_path` and `newsletters.file_path` were
deliberately left in place** alongside the new `image_file_id` / `file_id` columns:
exactly one of each pair is populated, and `Event::imageUrl()` / `Newsletter::fileUrl()`
decide which to serve. So a move back to a disk is a config change plus a backfill, not a
rewrite.

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

**Rule 2 was in fact widened, and has been corrected.** `Police`, `MyAssessments` and
`SearchRecords` each granted `UserRole::Admin`, which put the entire Officer Portal in an
admin's sidebar — and since content management was still a "Coming soon" stub, the panel
an admin actually saw was the officer portal with one empty page bolted on. Admins now
reach assessments through `App\Filament\Resources\AssessmentReview` (view-all,
read-only, which is what the matrix grants) and the Officer Portal is
`law_enforcement` + `police_admin` only.

The matrix is now **enforced by test**, not by review:
`tests/Feature/Staff/PanelAccessTest.php` writes it out as a table and asserts that every
role can reach exactly its own row and nothing else, with a second pass for inactive
accounts. A component appearing in the wrong role's list is a failing test.

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
GRANT SELECT ON content_app_db.content_files     TO 'harborsafe_content_public'@'%';

FLUSH PRIVILEGES;
```

Credentials go in `DB_USERNAME_CONTENT_PUBLIC` / `DB_PASSWORD_CONTENT_PUBLIC`.

`content_files` is on that list because uploads are database rows now (§4, §6.7) and the
public site has to be able to read an event's image. SELECT only, like the rest.

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

### 6.7 `content_files` — uploads as rows

Added with Phase 8. One row per uploaded event image or newsletter PDF:

`FileID` (uuid PK), `name` (as uploaded), `mime_type`, `size_bytes`, `contents`
(LONGBLOB), `checksum` (sha256), `created_at`.

Four decisions in that shape, each of which matters:

- **It is its own table, not columns on `events`/`newsletters`.** Those two are read
  constantly by the public API, and a blob on them could not be excluded from a
  `SELECT *` as cleanly.
- **`longText()->charset('binary')`, not `binary()`.** Laravel's `binary()` maps to
  plain `BLOB` on MySQL/MariaDB — 65 KB, far too small for a PDF.
- **`contents` is excluded by a global scope** on `App\Models\ContentFile`, so no
  listing or relation load ever pulls the bytes. `ContentFile::contents()` fetches that
  one column for one row with the query builder, without hydrating a model, so the blob
  never enters an attribute array.
- **`size_bytes` is derived, never supplied.** It comes from the bytes actually stored.
  `Newsletter` keeps `file_size_bytes` in step with a `saving` hook rather than a form
  callback, because a form callback is skipped by a programmatic fill, a seeder or
  tinker — and the column would then sit null while a file was plainly attached.

Serving them is two routes, with two different authorization rules:

| Route | For | Rule |
|---|---|---|
| `GET /api/public/content-files/{file}` | the public site | served only while a **published** event or newsletter points at the file |
| `GET /staff/files/{file}` | the panel | any signed-in staff member who passes `canAccessPanel()` |

The public rule is phrased that way because `content_files` has no owner and no published
flag of its own, so the row cannot answer "may this be served" — its reachability from
published content can. The consequence is deliberate: **unpublishing an event takes its
image offline**, which is what the public API already implies. Without it, a draft's
image would stay readable forever to anyone who had ever seen the URL.

The staff route needs `App\Http\Middleware\EnsureStaffPanelAccess` rather than plain
`auth`, for a reason worth recording: Laravel's `auth` redirects an anonymous visitor to
a route named `login`, which this application does not have — sign-in is Filament's at
`filament.staff.auth.login` — so `auth` turns an anonymous request into a 500 rather
than a redirect. Filament's own `Authenticate` is no good either, since it resolves its
guard from the current panel and there is no panel context on a plain web route.

**Orphan rows are not collected.** Clearing an upload in the panel clears the foreign key
and leaves the row, because an unpublish-then-republish would otherwise lose the file
irrecoverably and a stray row costs a few kilobytes. If cleanup is ever wanted it belongs
in a scheduled command that checks both referencing columns, not in a form hook.

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

**1 — Prerequisites.** *(Done.)* Enable `ext-intl` (§3.1). The old second half of this
phase — proving `php artisan storage:link` on the deploy target — is retired, because
uploads are database rows now (§3.2, §4).
*Gate:* `php -r "var_dump(extension_loaded('intl'));"` prints `true`.

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

**8 — Resources.** *(Done, except user management.)* Built, with the navigation layout in
§13:

- **Content** cluster — events (full create/edit pages), newsletters and categories
  (modal), plus database-backed uploads (§6.7).
- **Submissions** cluster — service feedback and resource requests, both read-only.
- **Form options** cluster — services, resource types and counties, sharing one
  `LookupResource` base.
- **Assessment review** — all law-enforcement submissions, view-only, for admin and
  police_admin (§5).

**Still to build: user management.** Creating and deactivating accounts is not in the
panel yet, and it is the phase's remaining piece — note that it carries §5 rule 2, which
says admin cannot create officers.
*Gate:* CRUD works; assessments expose no edit action at all outside the owning officer's
own screens; an upload renamed to an allowed extension is still rejected (§6.6).
*Status:* covered by `tests/Feature/Staff/` — 56 tests over the access matrix, page
rendering, authoring and file storage.

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

**13 — Tests.** *(Partly done.)* The scaffolding now exists: `tests/Pest.php` gained
`staffUser()` and `cleanupStaffData()`, following the same run-token tagging and
`afterEach` cleanup as the public-form helpers, and deliberately still no
`RefreshDatabase`. `tests/Feature/Staff/` covers panel access per role, the four-role
matrix, inactive accounts, page rendering, event and newsletter authoring (including the
DST round trip both sides of the boundary) and database file storage.

Two notes for whoever extends this:

- **Cleanup sweeps on the fixed `peststaff` prefix, not the per-run token**, so a run
  killed by a PHP fatal before `afterEach` does not leave rows behind forever. The
  trade-off is that these tests must not be run in parallel against one database.
- **`UploadedFile::fake()` is not usable here.** `fake()->image()` needs the GD
  extension, which this project does not require and the dev box lacks; and
  `fake()->create()` reports a size while writing no bytes, so anything stored from it
  lands as a zero-byte row and every size assertion is meaningless. Use
  `Illuminate\Http\Testing\File` with a real `tmpfile()` resource — Livewire's upload
  helper also reads `$file->name`, a property only that subclass has.

**Still to cover:** the change-log observer (Phase 9) and the public content endpoints
(Phase 10), neither of which is built.
*Gate:* `php artisan test` passes with one pre-existing failure. Currently 87 passing,
1 failing.

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

## 13. Panel navigation and addressing

Phase 8's layout. Three sidebar groups, and content management has **its own address
space** — the thing that was wrong before, when every destination an admin had was a
`/staff/police/*` URL.

```
Content management                              roles
  Content        /staff/content                 admin, secretary
    Events       /staff/content/events
    Newsletters  /staff/content/newsletters
    Categories   /staff/content/categories
  Submissions    /staff/submissions             admin, secretary
    Service feedback   /staff/submissions/service-feedback
    Resource requests  /staff/submissions/resource-requests
  Form options   /staff/form-options            admin, secretary
    Services       /staff/form-options/services
    Resource types /staff/form-options/resource-types
    Counties       /staff/form-options/counties

Assessments
  Assessment review  /staff/assessment-review   admin, police_admin

Officer Portal                                  law_enforcement, police_admin
  Home            /staff/police
  New assessment  /staff/police/new-assessment  law_enforcement only
  My Assessments  /staff/police/assessments
  Search Records  /staff/police/search
  Account         (Filament's profile page)
```

### Why clusters

The three content groups are Filament **clusters**, not loose resources. A cluster gives
one shared URL prefix and renders its members as **tabs across the top of each page**
(`SubNavigationPosition::Top`), so moving between events and newsletters is one click
rather than a round trip through the sidebar. It also collapses nine resources into three
sidebar entries.

Two cluster behaviours are load-bearing:

- `Cluster::shouldRegisterNavigation()` hides a cluster when every resource inside it
  refuses the user, so each resource carrying its own matrix rule is enough — the cluster
  needs no separate visibility logic. It still declares `canAccess()` for a direct URL hit.
- `Cluster::mount()` redirects the cluster root to the first tab the user can actually
  open. That is why `StaffLanding` can point at `ContentCluster::getUrl()` rather than at
  a specific page: `/staff/content` is never a dead end, and the landing target does not
  have to be kept in step with the matrix by hand.

**Clusters must be registered with `->discoverClusters(...)` in `StaffPanelProvider`.**
An unregistered cluster still resolves as a class but registers no routes, so every
resource inside it 404s.

### Sign-in and landing

Unchanged in principle, and now covered by tests: **everyone sees the same login screen
first** at `/staff/login`, and where they land afterwards is decided in exactly one place,
`App\Filament\StaffLanding` — admin and secretary to content management, officers and
police admins to the officer portal. Both entry points consult it: `StaffLoginResponse`
after a sign-in, and `RedirectStaffHome` when someone opens `/staff` directly.

Do not switch this to `$panel->homeUrl()`. That only sets the sidebar brand link and has
no effect on either redirect. Filament's own default is emergent rather than declared —
`RedirectToHomeController` sends the user to whichever navigation item sorts first — so
adding a page with a low `$navigationSort` would otherwise silently move every role's
landing page.

### The stub that was there before

`App\Filament\Pages\ContentManagement` (slug `content`, a "Coming soon" Blade view) is
deleted. It was ungrouped, which floated it above the sidebar, and being the only
non-officer destination it made the admin experience look like the officer portal with an
empty page attached. The `/staff/content` address it held is now the Content cluster's.
