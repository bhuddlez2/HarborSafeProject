# Filament staff panel — design and setup guide

**Status:** design settled. Done (§11): Phases 1–6, 8 (content management), 10 (public
content API and website cutover), 11 (officer screens and wizard in Filament) and 12
(Next.js cleanup); Phases 7 and 13 partly. Phase 9 is not yet built.

**The CMS is end to end as of 2026-10-01.** Staff publish an event in the panel and it
appears on the public website without a deploy, image included. What remains is admin
user management (§11 phase 8; police admins already provision their own officers) and the
change-log observer (§8). Phase 7's permissions were settled on 2026-10-24 without Shield:
it was removed unused, and every screen lists the actions it allows (§4, Authorization).

The content side of the panel is now built and under test: events, newsletters,
categories, the two public-form submission lists and the three form-option lookups, plus
an assessment review page (admin: every agency; police admin: own agency only, §5 rule
5). See §13 for the navigation layout and §4
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
`^4.0|^5.0` on Laravel `^13`, so Shield does not constrain the version choice. (Shield
was removed on 2026-10-24, unused; see §3.3.)

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
Windows ships PHP 8.2.12 at most, and `laravel/framework` v13 requires `^8.3` (as does
Pest 4), so there is no version to lower to. PHP
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

What replaced it is a set of size limits, and an upload has to clear **four** of them.
The lowest one wins, and the first two are the ones that actually bite:

| Limit | Where | Default | Why it matters |
|---|---|---|---|
| `upload_max_filesize` | php.ini | **2M** | Rejects the file before Laravel runs |
| `post_max_size` | php.ini | **8M** | Bounds the whole request; must exceed the above |
| temporary upload rule | Livewire | 12M | `max:12288` unless the config is published |
| `max_allowed_packet` | MariaDB | 16M | Caps a single row in **both** directions |

`App\Filament\Forms\Components\DatabaseFileUpload` asks for 4 MB on an image and 8 MB
on a PDF, and this project's php.ini therefore needs `upload_max_filesize = 8M` and
`post_max_size = 12M` (see `PORTAL_SETUP.md`).

**The first two are nastier than they look.** PHP discards an oversized upload before any
application code runs, so none of Filament's validation messages appear and the failure
looks like the panel being broken rather than the file being too big. This was reported
as exactly that: a 3.5 MB event photo failing against a stock `upload_max_filesize` of
2M, while the panel's own helper text promised 4 MB.

So the component no longer hardcodes its cap. `effectiveLimitKb()` clamps the intended
limit to `min(upload_max_filesize, post_max_size)` and the helper text prints whatever
survives, which means a misconfigured server shows "up to 2 MB" and enforces it cleanly
rather than advertising a size it cannot accept. `tests/Feature/Staff/UploadLimitsTest.php`
asserts the arithmetic and, separately, asserts that the machine running the suite can
actually carry the intended limits.

`max_allowed_packet` remains the outer bound: do not raise the application's limits past
it, because a blob that squeezes in can still fail to read back out.

`storage:link` is still worth running on a new environment — Laravel's own tooling
assumes it, and `PORTAL_SETUP.md` lists it — but nothing in this feature depends on it
any more.

### 3.3 spatie/laravel-permission's default connection — no longer applies (removed)

Filament Shield and spatie/laravel-permission were installed in Phase 3 and **removed on
2026-10-24 without ever being used**: no role or permission was ever created or assigned,
and every screen already decided access on `users.role`. Migration
`2026_10_24_120000_drop_permission_tables` dropped their five empty tables (`roles`,
`permissions` and the three pivots); the migration that created them stays, rewritten to
no longer read the deleted `config/permission.php` so a fresh database still migrates.

The trap this section used to describe was real while they were installed: spatie has no
connection setting, so its migration and models used the unreachable default `mariadb`
connection, and `shield:setup` ran a bare `migrate` (and, with `--fresh`, `DROP TABLE`)
through it. It was worked around with a Portal-pinned migration and local `Role` /
`Permission` subclasses. All of that is gone. If permissions are ever wanted again,
re-read this history first — but see §4: access is changed by developers in code on
purpose, never from a screen.

## 4. Settled decisions

**Panel scope.** Filament is the single authenticated home for every staff dashboard —
admin, secretary, police admin and officer. It owns events, newsletters, event
categories, the form dropdown options, submission review, assessment review and user
management.

**What stays in Next.js.** Only the anonymous civilian assessment, now a page of the
public website (`website/frontend/src/app/assessment/`, moved there from
`portal/frontend/app/page.js` on 2026-10-11, when `portal/frontend` was retired). It
cannot move into Filament because it is anonymous and public. Everything officer-facing — the officer home, My Assessments, Account and the law-enforcement assessment *wizard* — lives in the panel, carried over
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
**dropped**: nothing outside the panel needs the staff session any more. The old
`/api/law-enforcement-assessments` routes were removed in team plan task 29 (2026-10-21),
with every other signed-in API route: the wizard is the only way in.

**Look and feel.** The panel's theme (`resources/css/filament/staff/theme.css`,
compiled by Vite) carries over the officer portal's design for every role: `#5C0F8B`
primary, Tailwind's gray, Nunito/Montserrat, a dark full-height sidebar, no topbar,
no dark mode. The session lifetime is 15 idle minutes for every role
(`SESSION_LIFETIME`) and ends when the browser quits (`SESSION_EXPIRE_ON_CLOSE`); the
officer home tells officers their session logs out after that long. See §12.

**Authorization: `users.role`, in code, and nothing else.** *(Settled 2026-10-24; this
replaced the original plan of Filament Shield + spatie/laravel-permission, which were
installed but never used, and then removed — §3.3.)* One source of truth, the
`users.role` column (`App\Enums\UserRole`), decides everything, in two layers:

1. **Who may open a screen**: each screen's `canAccess()`, on `users.role` (plus the police
   admin's agency where it matters). Pinned role by role in `PanelAccessTest` (§5).
2. **What they may do there**: each screen lists its actions and refuses the rest. This
   is separate because Filament asks a screen before every built-in button (Create,
   View, Edit, Delete, bulk delete, restore, replicate, reorder) through one method,
   `getAuthorizationResponse()`, and when the model has no policy it answers *allowed*.
   Before this was settled, read-only screens such as Civilian assessments were
   read-only only because no Edit or Delete button had been added. Now every screen
   answers explicitly — `App\Filament\Concerns\AllowsOnlyListedActions`
   (`allowedActions()`, plus an optional per-row `allowsRecord()`), or the same rule
   written out by hand (Accounts, Officers, Agencies), or the assessment policy below —
   and the panel runs in Filament's **strict mode** (`->strictAuthorization()`), so a new
   screen without rules throws instead of allowing. Overriding `canDelete()` and friends
   alone is not enough; the buttons skip them. `ActionAccessTest` lists every screen's
   actions and fails on anything extra, anything missing, or an unlisted screen. Custom
   actions (`Action::make(...)`) are outside this, and check permission themselves.

Why not Shield: its permissions can say "may view assessments" but not "only this
agency's" or "only their own", which are the rules that protect victim information, so
those would have stayed hand-written anyway; it would have added a second description of
roles that could drift from `users.role`; it came with traps (`shield:setup` migrating
the unreachable connection, `shield:generate` overwriting hand-written policies, a
spatie `super_admin` role that skips every check); and its Roles screen would let
whoever runs the system widen access from inside the panel. This project will be handed
over and maintained by others, so **access changes are a developer's code change,
reviewed and tested, never a screen**.

**Law-enforcement assessments: a hand-written policy.** Access to
`LawEnforcementAssessment` is enforced by the hand-written
`App\Policies\LawEnforcementAssessmentPolicy`, registered with `Gate::policy()` in
`AppServiceProvider`. It decides on `users.role` (through `User::hasActiveRole()`) plus
ownership and agency, because "own submissions only" and "own agency only" are
per-record rules a permission string cannot express. Every write method returns `false`
except `update()`: the submitting officer, or an active police admin of the submitter's
agency (asked through `visibleTo()`), §5 rule 1.

**The rule lives in one place: the `LawEnforcementAssessment::visibleTo($user)` query
scope.** Admin: everything. Police admin: assessments whose submitting officer is in the
agency of the police admin's own `law_enforcement_agents` row (`User::agencyId()`), and
nothing at all without one (§5 rule 5). Officer: `submitted_by` is them. Anyone else:
nothing. The policy's `view()` asks that scope (`visibleTo($user)->whereKey(...)`), and
both screens' `getEloquentQuery()` apply it, so a list and a single-record check cannot
disagree. The query scope is what matters most: Filament lists do not run the policy per
row, so the query is what keeps another agency's records out of a list.

The two screens split by role. **My Assessments** (`LawEnforcementAssessmentResource`) is
officers only, so another officer's record is a 404 rather than a 403. **Assessment
review** (`AssessmentReviewResource`) is admin (all agencies, view-only) and police_admin
(own agency, view and edit), and its Officer filter only offers officers whose submissions
the user can see.

**Never add a `Gate::before`** that answers yes for some role: it runs before every
policy, so that role would bypass this one entirely — including the `update()` rule and
the `false` on delete (§5 rule 1). Super admin is granted screen by screen instead (§5).

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

## 5. Access matrix — five roles

```
                       LE      PoliceAdm    Secretary  Admin        SuperAdmin
Panel access           yes     yes          yes        yes          yes
Events / Newsletters   —       —            edit       edit         edit
Event categories       —       —            edit       edit         edit
Form options           —       —            edit       edit         edit
  (services/resources/counties)
Service feedback       —       —          view,      view,        view,
  (staff-entered forms)                   add form   add form     add form
Resource requests      —       —          view       view         view
Civilian assessments   —       —          —          view         view
LE assessments         own     own agency —          view all     view all
  submit               own     own (needs —          —            —
                               an agency)
  edit (with reason)   own     own agency —          —  (view-only)  —  (view-only)
LE change logs         own     own agency —          view all     view all
Access log             —       own agency —          view all     view all
  (who opened what)            LE only
Follow-ups             —       —          resource   assessments, assessments,
                                          requests   requests     requests
Analytics (totals)     —       own agency feedback + all 4 tabs   all 4 tabs
                               LE only,   requests
                               own page   tab only
                               (needs an
                               agency)
---------------------------------------------------------------------------
Manage officers        —       own agency —          NO           any agency
  (create/edit/deactivate)                                        (Accounts)
Create police admins   —       —          —          NO           YES
Create secretaries     —       —          —          YES          YES
Create admins          —       —          —          NO           YES
Create super admins    —       —          —          NO           YES (never itself;
                                                                  first one by artisan)
Agencies (add/rename)  —       —          —          —            YES (never delete)
```

Settled 2026-10-06; super admin added 2026-10-21 (team plan task 15). "Form options" is
what admin edits on the feedback and resource-request forms: the dropdown choices. The
submissions themselves stay view-only, since they are statements from the public. Police
admins, admins and agencies are created by super admin only, never through admin; super
admins by other super admins (never themselves; the first by
`php artisan staff:create-super-admin`). Every account screen takes first
and last name as separate fields, 50 characters each.

**Super admin is the one superset of admin, and only of admin.** It is granted screen by
screen (`UserRole::SuperAdmin` in each `hasActiveRole()` list), never by a `Gate::before`,
which would skip the `false` on assessment edits.
It has no Officer Portal and is view-only on assessments, like admin. An account's role
and agency never change after creation: `LawEnforcementAssessment::visibleTo()` follows
the submitter's *current* agency, so moving a person would hand their old assessments to
the new agency's police admin. A move is a deactivated account plus a new one.

Five rules that are easy to get wrong and must be enforced explicitly:

1. **An assessment may be edited by its submitting officer, or by an active police admin
   of the submitter's agency.** Widened deliberately (team plan task 7, 2026-10-21) for
   police admins of the **same** agency only — their officers' assessments and their own
   (a police admin with an agency submits their own through the same wizard).
   `LawEnforcementAssessmentPolicy::update()`: an officer matches on
   `law_enforcement_assessment.submitted_by`; a police admin is asked through
   `LawEnforcementAssessment::visibleTo()`, never a second rule. `admin` stays
   **view-only**. Nobody else can change a submitted form, ever, and every edit goes
   through `App\Services\AssessmentEditor` with a required reason.
2. **Admin cannot create officers.** Officer account provisioning belongs exclusively to
   `police_admin`, for their own agency (`OfficerAccountResource`). Admin creates
   secretaries only.
3. **Secretary never sees assessment PII** — not civilian, not law-enforcement.
4. **`is_active = false` denies panel access regardless of role.**
5. **Police admins are scoped to their own agency.** On every screen, existing and
   future, a police admin sees only officers, assessments and change logs of the agency
   in their `law_enforcement_agents` row (`User::agencyId()`), via
   `LawEnforcementAssessment::visibleTo()`. No agency → nothing: empty lists, no create.
   Concretely: assessments are reviewed in Assessment review, scoped to the agency (My
   Assessments is officers only); officer accounts are managed in
   `OfficerAccountResource`, which lists only that agency's `law_enforcement` accounts —
   never admins, secretaries, other police admins or the police admin themself. Officers
   are created with role and agency forced server-side, and are deactivated
   (`is_active = false`), never deleted, so assessment history survives. **Filter
   options are scoped too:** every dropdown on an assessment table offers only officers
   and agencies behind rows `visibleTo()` already returns (`App\Filament\Tables\AssessmentFilters`),
   because a dropdown can leak names the table itself hides.

Rule 2 is the one most likely to be "helpfully" widened by someone who assumes admin is
a superset. It is not.

Rule 5 is the other one. It is the rule most likely to be "helpfully" widened back to
"police admins view all" — the matrix used to say exactly that. It was narrowed on
purpose: a police admin is one agency's supervisor, not a statewide one.
`tests/Feature/Staff/PoliceAdminAgencyScopeTest.php` enforces it, and a failure there
means the rule was broken, not that the test is wrong.

**Rule 2 was in fact widened, and has been corrected.** `Police`, `MyAssessments` and
`SearchRecords` (since removed) each granted `UserRole::Admin`, which put the entire Officer Portal in an
admin's sidebar — and since content management was still a "Coming soon" stub, the panel
an admin actually saw was the officer portal with one empty page bolted on. Admins now
reach assessments through `App\Filament\Resources\AssessmentReview` (view-all for
admin, read-only, which is what the matrix grants) and the Officer Portal is
`law_enforcement` + `police_admin` only (My Assessments within it: `law_enforcement`
only).

The matrix is now **enforced by test**, not by review:
`tests/Feature/Staff/PanelAccessTest.php` writes it out as a table and asserts that every
role can reach exactly its own row and nothing else, with a second pass for inactive
accounts. A component appearing in the wrong role's list is a failing test. Rule 5 sits
below the component level — which *rows* a police admin sees — so it has its own file,
`tests/Feature/Staff/PoliceAdminAgencyScopeTest.php`.

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

`issue_date` is labelled **Publish Date** in the panel. New or changed dates run from the
1st of the month six months back to today, or, with the form's **Schedule post** box
ticked, to the end of the month six months ahead; a published issue stays off the website
(PDF included) until that day. Schedule post is not a column.

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
| `GET /api/public/content-files/{file}` | the public site | served only while a **published** event, or a published newsletter whose publish date has arrived, points at the file |
| `GET /files/{file}` | the panel | any signed-in staff member who passes `canAccessPanel()` |

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

## 7. Public content API — built

`GET /api/public/events` and `GET /api/public/newsletters`. Published records only, read
through `ContentPublic`, in the existing `Route::prefix('public')` group. A newsletter also
waits for its publish date (`issue_date`, an Eastern day; `Newsletter::scopeOnWebsite()`),
so staff can schedule an issue ahead (Finishing Touches item 26).
`App\Http\Resources\PublicEventResource` and `PublicNewsletterResource` reconstruct the
nested `location` / `image` / `registration` / `file` objects from the flattened columns.

**Those two resources are now the contract.** `mock-events.json` and
`mock-newsletters.json` were the original specification and were deleted at cutover, so
the key structure asserted in `tests/Feature/Public/ContentEndpointsTest.php` is the only
written record of it. Changing a field there means changing
`website/frontend/src/app/lib/content.js` in the same commit.

Four things in the implementation that are not obvious:

- **The website fetches in the browser, at view time.** It is a static export, so there
  is no server to fetch on its behalf, and baking content in at build time would mean a
  rebuild and redeploy every time staff added an event — the thing the panel exists to
  avoid. The cost is that the page depends on the API being reachable when someone looks
  at it, which is why `EventsContent.js` has a loading state, an error state carrying the
  crisis line, and a hard 12-second timeout.
- **`PublicEventController` joins `event_categories` instead of eager-loading it.**
  Eloquent resolves a relation on the *related* model's connection, and `EventCategory`
  declares `Content` — so `->with('category')` would read the category through the
  full-access connection while the events came through the restricted one, defeating the
  point and leaving a query the restricted user is never tested against.
- **Times go out in Eastern with an offset**, e.g. `2026-10-22T18:00:00-04:00`, matching
  the mock. The columns are UTC. A `Z` string would render identically on the site, but
  it would stop matching the contract.
- **A nested object is null or whole, never partial.** The page guards on plain
  truthiness, and `{name: null}` is truthy — it would render an empty row with a map
  pin. The `has*` helpers on the models exist for that decision.

CORS needed no change: `config/cors.php` already covers `api/*`, allows localhost:3000 in
local, and uses `WEBSITE_URL` in production. `supports_credentials` stays `false`, which
is correct — these are unauthenticated public reads and the site has no credentials.

The website reads `NEXT_PUBLIC_API_URL` (default `http://127.0.0.1:8000`), the same
pattern `forms.js` already used. It is inlined at build time, so it must be set before
`npm run build` on the deploy target.

No officer-scoped API is needed. Officers review and edit inside Filament, so policies
handle it and no new authenticated endpoints are required.

## 8. Change logging

**Built 2026-10-07.** Three tables:

- `assessment_edits`: **one row per save**, holding who (`ChangedBy`), when (`EditedAt`)
  and a **required reason**. This is what makes the history readable: one edit touching
  four fields reads as one entry with four changes, not four unrelated rows.
- `assessment_change_log` and `assessment_answer_change_log`: **one row per changed
  field** (`ChangeField`, `PreviousValue`, `NewValue`), linked to their save by `EditID`.
  Values are stored raw and only formatted for display (`App\Support\AssessmentFields`:
  "Victim last name", "Female", "Jun 30, 1988", "Q3: …"), so the log stays exact.

**One write path.** `App\Services\AssessmentEditor::apply()` is the only way an assessment
or its answers change. It reloads the record, discards non-changes ("" vs null, 1 vs true),
and writes the edit, its field rows and the record in one `Portal` transaction. A save
that changes nothing writes nothing. It is an explicit service rather than the observer
first planned here, because a reason and per-save grouping span two models and an
observer sees neither. To make it impossible to skip:
`EditedOnlyThroughAssessmentEditor` makes both models throw on any other `update()` (the
API controller, tinker, future code), and `AppendOnly` makes all three log models refuse
updates and deletes.

**Reading it.**
- Every law-enforcement assessment row has a **Change log** button, left of View, that
  opens the record's edits in their own pop-up, newest first. It is greyed out on a record
  never edited.
- **The View pop-up does not include the history.** View is the record as it is now,
  complete on its own, so the planned export from it is the whole current document. An
  export with history mixed in, or one that looks as though something was left out, is
  exactly what this avoids.
- Each list shows an **Amended** badge.
- **Logs** (`/logs`, §13) lists every edit, one row per save, alongside views and PDF
  downloads, for admins, super admins and police admins with an agency.

All of these come from `App\Filament\Assessments\ChangeHistory` and one Blade view.

Sizing is already verified safe: every editable column on `law_enforcement_assessment`
is `varchar(50)` or smaller, so the log's `varchar(50)` value columns cannot truncate,
and the longest field name is `OffenderVictimRelationship` at 26 characters against a
`varchar(32)` `ChangeField`.

`admin` reads all of these logs; `police_admin` reads only those for assessments its own
agency's members submitted (§5 rule 5) — scope any log screen through
`LawEnforcementAssessment::visibleTo()`. They are generated by whoever may edit (§5,
rule 1): the submitting officer, or a police admin of the submitter's agency, each
recorded as `ChangedBy`.

## 9. Next.js cleanup — done

**Update 2026-10-11:** the civilian flow moved to the public website
(`website/frontend/src/app/assessment/`), submitting through one throttled endpoint,
`POST /api/public/civilian-assessments`, on the INSERT-only `PortalPublic` connection
(`Schema_Reference.md`). `portal/frontend` no longer exists, and with it the redirects
described below. What follows is the state after Phase 12.

`portal/frontend` keeps **only the civilian flow**: `app/page.js` and what it imports
(`app/lib/{api,validation,validators,lethality-questions}.js`, `app/layout.js`,
`app/globals.css`). `api.js` is down to `submitAssessment()`.

Deleted (Phase 12): `app/login/`, `app/api/auth/`, `proxy.js`, `app/admin/`, the whole
officer side under `app/police/` (layout, home, wizard), and `components/portal/`
(sidebar, mobile header, icons). `next.config.mjs` redirects the old URLs — `/login` to
`/login`; `/admin`, `/police` and `/police/*` to the panel root `/` — built from
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
(Phase 11); the placeholder is gone with `submitLawEnforcementAssessment()`. Officer
name, badge and agency are read from `law_enforcement_agents` / `agencies`
(`User::officerIdentity()`).

The old API controller, whose `update()` took `submitted_by` from the request, was
removed with the rest of the signed-in API in team plan task 29 (2026-10-21). Editing is
the panel's `AssessmentEditForm`, governed by `LawEnforcementAssessmentPolicy`.

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

**3 — Shield + spatie.** *(Done, then undone: both removed unused on 2026-10-24 — §3.3, §4.)*

**4 — Add `police_admin`** to `App\Enums\UserRole`. *(Done.)*
*Gate:* all four cases resolve and cast correctly on `User`.

**5 — Content connection.** *(Done, except the restricted MySQL user.)* `Content` + `ContentPublic` in `config/database.php`, env
vars, grants from §6.5.
*Gate:* `php artisan db:show --database=Content`.

**6 — Content schema.** *(Done.)* Migrations and models per §6.
*Gate:* `php artisan migrate:status --database=Portal`.

**7 — Auth and access.** Filament login, `User implements FilamentUser` with
`canAccessPanel()`, the §5 matrix enforced on every screen, a super admin, plus a
seeder creating one user per role for local development.
*(Partly done: `canAccessPanel()` — `is_active` plus a valid role — and the local-only
`LocalStaffUserSeeder` exist, with two placeholder pages (`/content`,
`/police`) gated by `canAccess()`. The seeder also creates a second officer, with
both officers badged in a "Local Test PD" agency, and `LocalAssessmentSeeder` gives each
officer two assessments. The law-enforcement assessment row of the matrix is enforced by
`LawEnforcementAssessmentPolicy` and the `LawEnforcementAssessment::visibleTo()` scope
(§4), agency-scoped for police admins through `User::agencyId()` (§5 rule 5). The seeder
has since gained a second agency, "Second Test PD", with its own police admin
(`police-admin2@`) and officer (`officer3@`), and gives both police admins a
`law_enforcement_agents` row. Super admin was added in team plan task 15, and on 2026-10-24
every screen was given an explicit list of allowed actions in place of Shield permissions
(§4).)*
*Gate:* each of the four roles sees exactly its matrix row; admin is refused when
creating an officer; `police_admin` has no edit action on assessments.

**8 — Resources.** *(Done, except user management.)* Built, with the navigation layout in
§13:

- **Content** cluster — events (full create/edit pages), newsletters and categories
  (modal), plus database-backed uploads (§6.7).
- **Submissions** cluster — service feedback and resource requests, never edited. Resource requests are read-only; service feedback also has **New feedback form** (admin, secretary) for forms filled in by staff, typically from paper, marked `Source = staff`, with an optional scan kept in `feedback_scans` (see `CLAUDE.md`).
- **Form options** cluster — services, resource types and counties, sharing one
  `LookupResource` base.
- **Assessment review** — law-enforcement submissions: admin sees all of them, view-only;
  police_admin only its own agency's (§5 rule 5), including its own, with **Edit** after
  View. Edit is the shared `App\Filament\Assessments\AssessmentEditForm` (the same form and
  action as My Assessments: required reason, `AssessmentEditor`, a logged view on open),
  shown only where `LawEnforcementAssessmentPolicy::update()` allows and re-checked with
  `Gate::authorize()` at save (§5 rule 1, 2026-10-21).
- **New assessment for police admins** — `NewAssessment::canAccess()` is an active
  officer **or** a police admin with an agency (`managedAgencyId()`); without an agency, a
  403 and no sidebar item. The wizard is the officers' own, unchanged (same steps, same
  step positions), and `submitted_by` is always the signed-in user. The Submitting
  officer step shows the police admin's name and agency, with **"Police admin"** where the
  badge goes: `User::displayBadge()`, the one rule for any screen showing a submitter's
  badge (display only — the stored `badge_number` is never written). Their submissions
  fall inside `visibleTo()` for their agency, so they appear in their Assessment review and
  never in an officer's My Assessments. The police admin Home shows the officer's purple
  **Start New Lethality Assessment** card above Assessment Review and Staff Management
  (`Police::canStartAssessment()`, which is `NewAssessment::canAccess()`).
- **Civilian assessments** — submissions from the public civilian flow, view-only, for
  admin alone (§5). Each record's View pop-up has **Download PDF**
  (`/civilian-assessments/{id}/pdf`, the same template as the law-enforcement PDF), and
  every View and download is logged in `assessment_access_log` (2026-10-19).
- **My Assessments** — `App\Filament\Resources\LawEnforcementAssessments`, at
  `/police/assessments`, replacing the old mockup page. List with view and edit
  modals, **officers only**, each seeing their own submissions. Edit (the shared
  `AssessmentEditForm`) is the submitting officer's here and was first live **before** the Phase 9 change log, so edits are not yet
  recorded. Police admins and admins are kept
  out by the resource's `canAccess()`, while `LawEnforcementAssessmentPolicy` still grants
  them record view (admin all, police admin own agency), because `Assessment review`
  authorizes through the same policy. On the officer Home page, an officer's
  **My Assessments** card (`Police::pastAssessmentsUrl()`) opens My Assessments; a
  police admin's card has the same name but opens Assessment review instead.
- **Officers** — `App\Filament\Resources\OfficerAccounts` (on `User`), at
  `/police/officers` in a "Police Admin" navigation group: officer provisioning,
  list, create and edit, no delete. Police admins with an agency only, and only that
  agency's `law_enforcement` accounts (§5 rule 5). It authorizes itself on the resource
  (`getAuthorizationResponse()` and the `can*()` methods) rather than through a
  `UserPolicy`, because the future admin "Users" screen needs different rules for the
  same model. Create and
  edit write `users` and `law_enforcement_agents` in one `Portal` transaction, with role
  and agency forced server-side; passwords are 15–64 characters, with a capital, a lowercase letter, a number and a symbol, keyboard characters only (`KeyboardCharactersOnly`), and checked against
  breached passwords (`Password::uncompromised()`). First and last name are separate
  fields, as on Accounts below.

**The civilian page was missed on the first pass, and the matrix caught nothing.** This
phase shipped with `Assessment review` only, so a civilian submission landed in
`_private_assessment` and was invisible to every role in the panel — reported as
"submitted assessments aren't showing on the admin page". The §5 matrix had granted admin
`Civilian assessments: view` all along. The lesson is narrow and worth keeping: the
matrix-enforcement test in `tests/Feature/Staff/PanelAccessTest.php` can only check the
components that exist, so it proves no role sees too much and says nothing about a row
that was never built. A matrix line with no resource behind it fails silently.

- **Accounts** — `App\Filament\Resources\StaffAccounts`, at `/accounts` in the
  Content management group, admin only: create, edit and deactivate secretaries. Who
  manages which roles is `StaffAccountResource::manageableRoles()`; the role is forced on
  save, nothing is deleted, and nobody edits their own account here.

First and last name (50 characters each) are separate fields on both account screens, and
are shown read-only on the Account (profile) page (§13). They are the source of truth; `users.name` is kept, rebuilt
as "First Last" on every save, because tables search and sort on it.

**Still to build: the super admin role**, which creates police admins, admins and
agencies (§5).
*Gate:* CRUD works; assessments expose no edit action at all outside the owning officer's
own screens; an upload renamed to an allowed extension is still rejected (§6.6).
*Status:* covered by `tests/Feature/Staff/` — 56 tests over the access matrix, page
rendering, authoring and file storage.

**9 — Change log** (§8). *(Done, 2026-10-07.)* Built as an explicit editor service rather
than a model observer, because a required reason and one-entry-per-save grouping span two
models (§8).
*Gate passed:* editing an owned assessment writes one `assessment_edits` row and one row
per changed field, attributed to the editing user; a no-change save writes nothing; any
other update is refused (`tests/Feature/Staff/AssessmentChangeLogTest.php`).

**10 — Content API + website cutover.** *(Done.)* Public endpoints and API Resources
(§7), `content.js` repointed, both mock files deleted.
*Gate passed:* the response shape matched `mock-events.json` key for key, and the events
page rendered a real event — title, category badge, Eastern time, registration link and a
database-stored image — with **no component changes**; only the two loader functions in
`content.js` were touched, as §6.1 required.

One knock-on for the Playwright suite: `playwright.config.js` boots the website only, so
specs that expect content now stub the two endpoints via `tests/support/content-api.js`
rather than depending on a developer having `php artisan serve` running. The response
shape itself is pinned on the backend instead.

**11 — Officer screens and wizard in Filament.** *(Done.)* Carry the officer portal over
from `portal/frontend` faithfully: a custom Vite theme for the panel; Home (the existing
`Police` page), My Assessments and Search Records (placeholders — they had no design;
My Assessments has since become a resource, see Phase 8, and Search Records was removed,
replaced by filters on the assessment tables — see §13),
Account (Filament's profile page), all in an "Officer Portal" navigation group; the LAP
wizard as a `Wizard` page writing both assessment rows in one `Portal` transaction with
`submitted_by = auth()->id()`; the login page restyled. Lock the assessment API down:
LE/agent/agency routes fully behind `auth:sanctum`, and only `store` public on the three
civilian tables (since superseded: every signed-in API route was removed in team plan
task 29, 2026-10-21). The cross-origin session previously planned here is dropped (§4).
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
`PoliceAdminAgencyScopeTest.php` enforces §5 rule 5 with two agencies, each with a police
admin and an officer: a police admin reviews only its own agency's assessments and is
offered only its own officers in the review filter and on the Officers screen, a police
admin with no agency sees nothing, police admins are refused My Assessments, officers see
only their own and are refused Assessment review, and admin sees every agency.

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
*Gate:* `php artisan test` passes in full.

> `tests/Feature/ExampleTest.php` checks Laravel's `/up` health route. It used to be Pest's
> stock scaffold asserting `GET /` returns 200 and failed by design, because `/` is the panel
> root and redirects an anonymous visitor to `/login` (302). See `CLAUDE.md`.

## 12. Open items

- ~~**Whether §10.2 gets fixed as part of this work.**~~ Resolved: yes. The routing half
  was fixed in `0326f7f`; the identity half is folded into Phase 11.

- ~~**Production domain layout.**~~ No longer a constraint. It only mattered for the
  cross-origin session, which was dropped when the wizard moved into Filament (§4).
  The panel, API and portal can sit on any domains.

Still undecided:

- Nothing at present. View logging, once listed here, was built 2026-10-20: every
  law-enforcement View, Edit and Change log pop-up, an edit row's View on Logs, every
  civilian View, every follow-up page and every PDF download writes a row in
  `assessment_access_log`, readable on Logs (§13). The officer Home's
  "Each view is logged with your name and a timestamp" is now true.

### Notes for the database architect

Officer provisioning and the police-admin agency scope (§5 rule 5) were built without any
schema change. These are what that left implicit or undecided:

- **Assessments follow the officer's *current* agency.** `law_enforcement_assessment`
  stores no agency; `visibleTo()` joins through `submitted_by` →
  `law_enforcement_agents.agency_id` at query time. So when an officer transfers, their
  whole assessment history moves with them: the old agency's police admin loses sight of
  it and the new one gains it. If assessments should stay with the agency they were
  taken under, the assessment needs its own `agency_id`, captured at submission.
- **Police admins are stored in `law_enforcement_agents`.** That row is the only link
  between a police admin and an agency. `Schema_Reference.md` now says so (it used to
  describe the table as `law_enforcement`-only). Either keep this, or give the link a
  home of its own (an `agency_id` on `users`, or an agency-admins table).
- **Assessments don't store the badge.** Officers can change their own `badge_number`
  on the Account page (`EditProfile`), and police admins can change it on Officers.
  The assessment View pop-up and its PDF show only the officer's name, so a badge
  change affects only the places that read the *current* badge: the sidebar identity
  and the wizard's officer step. If records ever need the badge as it was at
  submission time, that is a schema decision: snapshot it on the assessment.
- **`badge_number` is required and not unique.** It is `NOT NULL`, so every police admin
  must carry a badge number to have an agency at all, though they have no real badge:
  the panel shows a police admin's badge as "Police admin" (`User::displayBadge()`), never
  the stored value. When the super admin screen for creating police admins is built, it
  should fill a placeholder (e.g. "N/A") into their row itself rather than ask for a badge. There is no unique index on it,
  alone or per agency, and the officer form does not check for duplicates. Decide
  whether it should be unique, and at what scope.
- **`agency_id` is nullable and `nullOnDelete`.** Deleting an agency orphans its officers
  and police admins: the officers become invisible to every police admin, and the police
  admin loses all access. Nothing prevents it.
- **"At least one police admin per agency" is enforced nowhere.** An agency can have none,
  leaving its officers unmanageable.
- **No "must change password on first login" flag.** The police admin chooses an officer's
  first password and the officer is never made to replace it; forcing that needs a column
  on `users`.
- **Who creates police admins and agencies.** The §5 matrix gives that to `admin`; the
  current intent is a future HarborSafe "super admin". Neither is built — police admins,
  agencies and the police admins' `law_enforcement_agents` rows exist only through
  `LocalStaffUserSeeder`.

## 13. Panel navigation and addressing

Phase 8's layout. Three sidebar groups, and content management has **its own address
space** — the thing that was wrong before, when every destination an admin had was a
`/police/*` URL.

```
Content management                              roles
  Content        /content                 admin, secretary
    Events       /content/events
    Newsletters  /content/newsletters
    Categories   /content/categories
  Submissions    /submissions             admin, secretary
    Service feedback   /submissions/service-feedback
    Resource requests  /submissions/resource-requests
  Form options   /form-options            admin, secretary
    Services       /form-options/services
    Resource types /form-options/resource-types
    Counties       /form-options/counties

Assessments                                     admin
  Assessment review      /assessment-review     all agencies
  Civilian assessments   /civilian-assessments
  Logs                   /logs                  one list: views, downloads, edits; all rows

Reports                                         admin, super admin, secretary
  Analytics              /analytics             tabs across the top; totals only
    Law-enforcement assessments /analytics/officer-assessments   admin, super admin
    Civilian assessments        /analytics/civilian-assessments  admin, super admin
    Feedback and resource requests /analytics/feedback-and-requests  + secretary
    Follow-ups                  /analytics/follow-ups            admin, super admin

Officer Portal                                  law_enforcement
  Home            /police
  New assessment  /police/new-assessment
  My Assessments  /police/assessments
  Account         (Filament's profile page)

Police Admin                                    police_admin - its ONLY group
  Home               /police                    sort 10
  New assessment     /police/new-assessment     20  their own; needs an agency
  Assessment review  /assessment-review         30  own agency; Edit
  Logs               /logs                      40  own agency's officer assessments; needs an agency
  Analytics          /police/analytics          50  own agency's LE assessments, no By agency; needs an agency
  Officers           /police/officers           60  own agency's officers; needs an agency
  Account            (Filament's profile page)  70
```

**The Account page changes only the password and the badge.** `EditProfile` (Filament's
profile page) lets every role change their own password, under the same rule as account
creation (`AccountFormSections::passwordRule()`: 15–64 characters, a capital, a lowercase letter, a number and a symbol, keyboard characters only, not in a known breach),
and active officers their own badge number. First name, last name and email are shown
greyed out and never saved; the save is a whitelist (password, plus the officer's own
`law_enforcement_agents.badge_number`), so a tampered request changes nothing else. Name
and email are changed by whoever manages the account: an officer's by their agency's
police admin (Officers) or a super admin; a secretary's by an admin or super admin; a
police admin's or admin's by a super admin (Accounts); a super admin's by another super
admin. A lone super admin therefore can't change their own name or email in the panel.

**A police admin sees one group.** Home, New assessment, Assessment review, Logs and
Account are shared with other roles, which keep their own groups above. Each of those
items picks its group and position by role: `getNavigationGroup()` /
`getNavigationSort()` overrides on `Police`, `NewAssessment`, `AssessmentReviewResource`
and `LogResource`, and closures on the
Account `NavigationItem` in `StaffPanelProvider` (`group()` and `sort()` take closures in
v5). All of them read `App\Filament\PoliceAdminNavigation`, the one place the police
admin's group name and order are declared; sorts step by 10 so items can slot in.
Grouping and order only — access is still each item's own `canAccess()`.
`tests/Feature/Staff/NavigationTest.php` pins every role's sidebar.

Search Records (`/police/search`, a "Coming soon" placeholder) was removed: filtering on
the assessment tables replaces it.

**Analytics** (`/analytics`, `App\Filament\Clusters\Analytics\AnalyticsCluster`, team plan
task 28): Harbor Safe's totals and charts, one item in its own **Reports** group, four
custom pages as tabs (`Clusters/Analytics/Pages/`). Every agency is counted together, so
officers and police admins never reach it (police admins have their own page, below); a secretary sees only the Feedback and
resource requests tab and lands on it from `/analytics`. Totals only: the stats services
(`App\Services\Analytics\*`) count in SQL and never select names, phone numbers,
comments or dates of birth. One date range (presets or custom days, Eastern) and, on the
two assessment tabs, a filter by victim sex (required) and offender sex (optional), are
shared by the tabs through the session (`App\Filament\Analytics\AnalyticsPage`). High
risk is "yes to Q1–3, or 4 or more yes" (`App\Support\HighRisk`, approved by Harbor Safe
2026-10-25), the same rule behind the assessment tables' red Yes badge, the record view
and its PDF, and the Civilian assessments high-risk filter. The pages
authorize themselves with `canAccess()`. `AnalyticsTest`.

**Police admin Analytics** (#67, team decision, 2026-10-29 — this reverses the earlier
"police admins get no analytics tab"): `App\Filament\Pages\AgencyAnalytics` at
`/police/analytics`, in the Police Admin group after Logs, for an active police admin
**with an agency** (`User::managedAgencyId()`); everyone else gets no item and a 403. It
is Harbor Safe's Law-Enforcement Assessments tab (same `AnalyticsPage` base, date range,
sex filter, tiles, charts and Excel/PDF export) with every number scoped to the police
admin's own agency — `AssessmentStats::officer(..., viewer:)` narrows the base query to
`LawEnforcementAssessment::visibleTo()` before anything is read or decrypted (§5 rule 5)
— and **no By agency chart** (`AssessmentSections::build(..., byAgency: false)`), since it
would name other agencies. Law-enforcement assessments only: no civilian assessments,
feedback, resource requests or follow-ups. Its export adds an "Agency: …" line. Harbor
Safe's own Analytics is unchanged. `PoliceAdminAnalyticsTest`.

**Logs** (`/logs`, `App\Filament\Resources\Logs\LogResource`, list page `ListLogs`): one
sidebar item and **one list** of every view, PDF download and edit of an officer or
civilian assessment, newest first. Decided 2026-10-26; resource request rows dropped
2026-10-27 (Finishing Touches item 28: they are still recorded in `assessment_access_log`,
just not listed); it replaces the two tabs (Access log at `/logs/access`, Change log at
`/logs/changes`) and **the plan's "Change log page stays edits only"**.

**How the list is built.** The rows are `App\Models\LogEntry`, a read-only model with no
table of its own and **no database view**. `LogEntry::visibleTo($user)` UNIONs
`assessment_access_log` (views and downloads) and `assessment_edits` (one row per save) in
a subquery named `log_entries`, with common columns `EntryID` (the `AccessID` or
`EditID`), `OccurredAt`, `UserID`, `Action` (`App\Enums\LogAction`: view / download /
edit), the two assessment pointers, and `EditID` on edit rows (the access half leaves out
resource request rows with `whereNull('ResourceRequestFormID')` before the union); an edit's assessment sits in
`LawEnforcementDocumentID`, like an access row about an officer assessment. **Each half is
scoped before the union** by its own scope — `AssessmentAccessLog::visibleTo()` and
`AssessmentEdit::visibleTo()`, both built on `LawEnforcementAssessment::visibleTo()` (§5
rule 5) — so pagination, sorting, search, filters and Show, which all act on the outer
query, can only narrow it. A bare `LogEntry::query()` reads a missing table and fails, and
the model refuses writes.

**Who sees what** (unchanged): **admins and super admins** see every row — officer and
civilian assessments, edits; **police admins with an agency** see only rows about their
own agency's officers' assessments, whoever made them — never a civilian assessment,
never another agency's. Everyone else, and a police admin
with **no** agency, gets no Logs item and a 403 (`LogResource::canOpenLogs()`).

**Columns:** When (Eastern) · Who · Action (badge: Viewed / Downloaded PDF / Edited) ·
Record (the kind of form, with "Submitted … · last 8 characters of its id" under it) ·
Changes (fields changed, edit rows only). **No personal details are shown** — no victim,
offender or requester names, no phone numbers, no edit reason; the forms are loaded with
just their id and submitted time. The old Change log's names and Reason columns went for
that reason; both still show in an edit's View pop-up.

**Actions:** an edit row has **View**, the same one-edit card each record's Change log
pop-up uses (`ChangeHistory::entry()`), and opening it logs a view of the assessment
(`AssessmentViews::log()`, before the pop-up is sent, so a failed write stops it opening).
It has no Download PDF. **Every row's Record cell is clickable** (Finishing Touches item
35, 2026-10-29), with no new button: it opens the assessment's own View pop-up on the Logs
page, with Download PDF, looked up again through `LawEnforcementAssessment::visibleTo()` /
`PrivateAssessment::visibleTo()` and logged as a plain view before it is sent; it isn't
clickable when the form is gone (`LogResource::openAssessmentAction()`). No create, edit, delete or bulk
actions (`LogResource::allowedActions()` / `allowsRecord()`). Opening the page is not
itself logged.

**Above the table:** one plain **Show** dropdown — All / Viewed / Downloaded PDF / Edited,
default All, applied as soon as it changes. It is a Filament `Select` in the list page's
own layout (`ListLogs::content()`, bound to `ListLogs::$show`), not a table filter, so it
sits outside the Filters card. It only adds a `where` on `Action` to the scoped query, and
an unknown value means All. Its options come from one place, `ListLogs::showOptions()`:
All, then one per `LogAction` case, whose view and download labels come from
`AssessmentAccess`, so a future action is a new enum case. The **search box** searches
**only** the Who column — the name of the person who viewed, downloaded or edited (partial
match, any case) — within the viewer's scoped rows, never record ids or anything in the
forms. **Behind the Filters button:** the card below ("Logs filters").

The old addresses redirect to `/logs`, keeping the query string (`routes/web.php`):
`/logs/access`, `/logs/changes`, `/access-log` and `/assessment-changes`, each with any
sub-path. `LogsPageTest`, `AccessLogScreenTest`.

### Assessment table filters

Both law-enforcement assessment tables — My Assessments and Assessment review — get their
filters from one class, `App\Filament\Tables\AssessmentFilters::apply($table, $reviewScreen)`,
so the two cannot drift apart. **Any future assessment table must use it too.**

- **Layout:** a filter card above the table, always open (`FiltersLayout::AboveContent`).
- **Deferred:** changing a filter does nothing until **Apply** (Filament's own apply action,
  which renders in the card whenever filters are deferred); **Reset** sits beside it
  (`FiltersResetActionPosition::Footer`) and clears and applies at once.
- **Persisted in session**, so filters survive opening a record and coming back.
- **Filters** — the final set, and who sees each:

  | Filter | My Assessments (officer) | Assessment review |
  |---|---|---|
  | Date submitted — from/to on `DateCreated` | yes | admin, police admin |
  | Submitting officer | — | admin, police admin |
  | Agency — via `submitter.lawEnforcementAgent.agency` | — | **admin only** |
  | Victim first name, victim last name | yes | admin, police admin |
  | Offender first name, offender last name | yes | admin, police admin |

  Agency is shown to active admins only, decided by `users.role` through
  `User::hasActiveRole(UserRole::Admin)`. A
  hidden filter is neither rendered nor applied, so forcing its state does nothing. The
  four name filters are typed text: a case-insensitive partial match, with `%` and `_` in
  the input matched literally. Since 2026-10-27 the names are encrypted (Finishing
  Touches item 14), so the match runs in PHP on the decrypted names of the viewer's own
  `visibleTo()` rows (`AssessmentFilters::encryptedTextContains()`,
  `App\Support\EncryptedSearch`), never in SQL; blank turns a filter off, and each active filter shows a
  chip. There is deliberately no filter on the victim's safe phone number.
- **Layout:** `filtersFormColumns(3)`, one row per group, each opened with
  `columnStart(1)`: dates; officer and agency; victim first and last name; offender
  first and last name. An officer's card has three rows, a reviewer's four.
- **Scoped:** filters only narrow each screen's `visibleTo()` query, and the officer and
  agency dropdowns take their options from the database, limited to rows `visibleTo()`
  returns (§5 rule 5). The card is the only way to search: since 2026-10-29 the
  assessment tables have no search bar or per-column search (`AssessmentTableSearchTest`).
  Enforced by `tests/Feature/Staff/AssessmentFiltersTest.php`.
- **Civilian assessments** uses the same card through `AssessmentFilters::civilian($table, $extra)`
  (2026-10-26, Finishing Touches item 13): date submitted and the four name filters — no
  officer or agency, since the public submits these — then the screen's own High risk
  checkbox on a row of its own (its old Submitted with contact details filter was dropped).
  `CivilianAssessmentFiltersTest`.

### Logs filters

The Logs page (`/logs`, `LogResource`) gets its filters from
`App\Filament\Tables\ChangeLogFilters::apply($table)`, built from `AssessmentFilters`'
public helpers (`layout()`, `dateRange()`, `textContains()`, `nameFilters()`,
`visibleAssessments()`, `showsAgency()`), so the card is the same one: behind the Filters
button, three columns, deferred until **Apply**, **Reset** beside it, persisted in the
session. (The class keeps its name from when it served the edits-only Change log.)

The list mixes views, downloads and edits (`LogEntry`). An edit row is one save; both
field-level tables (`assessment_change_log`, `assessment_answer_change_log`) hang off it by
`EditID`, so the filters cover detail and answer changes alike.

| Filter | Rows it applies to | Admin / super admin | Police admin |
|---|---|---|---|
| Who — the person on the row (`UserID`) | every row | yes | yes, own agency's rows' people only |
| Date — from/to on the row's own time (`OccurredAt`) | every row | yes | yes |
| Original submitter — the officer assessment's `submitted_by` | officer-assessment rows | yes | yes, own agency's officers only |
| Assessment ID — any part of the officer assessment's `DocumentID` | officer-assessment rows | yes | yes |
| Agency — via `lawEnforcementAssessment.submitter.lawEnforcementAgent.agency` | officer-assessment rows | yes | **no** |
| Victim first/last name, offender first/last name | officer-assessment rows | yes | yes |

- Rows: who, original submitter and assessment ID; date and agency; victim names;
  offender names.
- **Officer-assessment filters** match through the row's `lawEnforcementAssessment`, so
  setting one hides civilian assessment and resource request rows, which have none.
- **Date** is an edit's own `EditedAt` or an access row's `AccessedAt` (the table's "When"
  column), not the child rows' `TimeStamp`, which is written in the same transaction.
- **Agency** follows the same rule as Assessment review: active admins and super admins
  only, by `users.role` (`AssessmentFilters::showsAgency()`); hidden from police admins,
  and a hidden filter is never applied.
- **Names** match the officer assessment's current name columns — case-insensitive,
  partial, `%` and `_` literal, decrypted and matched in PHP like the assessment tables'
  name filters (the names are encrypted). **An edit's logged `PreviousValue` / `NewValue` are
  deliberately not searchable or filterable**: the log is read per record, not mined for
  values.
- **Scoped:** the table's query is `LogEntry::visibleTo()` (each half scoped before the
  union, above), and every dropdown's options come from the same scoped rows, so a police
  admin is never offered another agency's people or the agency. Enforced by
  `ChangeLogFiltersTest` (edit rows) and `LogsPageTest` (the kinds mixed).

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
  a specific page: `/content` is never a dead end, and the landing target does not
  have to be kept in step with the matrix by hand.

**Clusters must be registered with `->discoverClusters(...)` in `StaffPanelProvider`.**
An unregistered cluster still resolves as a class but registers no routes, so every
resource inside it 404s.

### Sign-in and landing

Unchanged in principle, and now covered by tests: **everyone sees the same login screen
first** at `/login`, and where they land afterwards is decided in exactly one place,
`App\Filament\StaffLanding` — admin and secretary to content management, officers and
police admins to the officer portal. Both entry points consult it: `StaffLoginResponse`
after a sign-in, and `RedirectStaffHome` when someone opens the panel root `/` directly.

Do not switch this to `$panel->homeUrl()`. That only sets the sidebar brand link and has
no effect on either redirect. Filament's own default is emergent rather than declared —
`RedirectToHomeController` sends the user to whichever navigation item sorts first — so
adding a page with a low `$navigationSort` would otherwise silently move every role's
landing page.

### The stub that was there before

`App\Filament\Pages\ContentManagement` (slug `content`, a "Coming soon" Blade view) is
deleted. It was ungrouped, which floated it above the sidebar, and being the only
non-officer destination it made the admin experience look like the officer portal with an
empty page attached. The `/content` address it held is now the Content cluster's.

- **Staff sessions persist across navigation, and the local `.env` has drifted.**
  Reported 2026-10-01: navigating away from content management and back does not ask
  for a login again.

  Three separate things are tangled here, and they need separating before anyone
  "fixes" it:

  1. **A config drift, which is the immediate cause.** `.env.example` sets
     `SESSION_LIFETIME=15`, and `CLAUDE.md` says 15 because the officer Home promises a
     15-minute lock. The working `.env` on at least one machine said **120**. That is a
     two-hour idle window, so of course the session is still good on return.
     **Resolved 2026-10-19 (roadmap Phase V, team plan task 2):** 15 minutes for every
     role, content side included, decided deliberately. `config/session.php` now falls
     back to 15 (was 120) and `SESSION_EXPIRE_ON_CLOSE` to `true`, so a missing `.env`
     value can no longer mean two hours. Set both in your own `.env` anyway.

  2. **It is an idle timeout, not a per-navigation re-auth.** Even at 15, leaving the
     panel and coming back five minutes later will not prompt, because Laravel refreshes
     the session cookie on activity and `expire_on_close` is `false`. That is what being
     signed in means, and it is correct behaviour.

  3. **If re-authentication on return is genuinely wanted, that is a feature, not a
     setting.** The options are roughly: Filament's password-confirmation action on
     sensitive pages; a client-side idle lock driven by the Page Visibility API that
     clears the session after N minutes hidden; or `SESSION_EXPIRE_ON_CLOSE=true`, which
     only covers closing the browser and not navigating away. Each of these has a real
     cost to a secretary doing bulk content entry, so pick deliberately.
     **Chosen 2026-10-19:** `SESSION_EXPIRE_ON_CLOSE=true`, plus an idle-logout script
     (`resources/views/filament/partials/idle-logout.blade.php`, rendered for signed-in
     users by a `SCRIPTS_AFTER` hook in `StaffPanelProvider`). The server only notices
     an expired session on the next request, so the script reloads the page shortly
     after the deadline; the expired session redirects to `/login`, and signing in
     returns to the same page. The clock restarts on page loads and Livewire requests
     (not mouse movement, which doesn't extend the server session), shared between tabs
     through `localStorage`. Caveats: quitting the *browser* ends the session, not
     closing a tab, and a browser set to restore its last session can bring the cookie
     back, which the 15-minute limit still covers. No password confirmation per page.
     **Added 2026-10-24:** a warning two minutes before the deadline (half the lifetime
     if that is shorter) — Filament's own pop-up, closed only by its buttons: **Stay
     signed in** posts once to `POST /session/keep-alive` (`KeepSessionAliveController`,
     a 204 that restarts the clock; an already-expired session gets the sign-in
     redirect and the page reloads), **Sign out now** submits Filament's logout. Only
     that button extends the session, never a timer, so an unattended screen still
     signs out. Officers mid-assessment no longer lose a form to a logout they didn't
     see coming. Covered by `SessionTimeoutTest`.
     **Added 2026-10-25:** an unfinished officer assessment survives the logout itself,
     in the browser tab only (settled with Harbor Safe 2026-10-24: never a drafts
     table, and a police admin can't finish an officer's form). The wizard saves to
     the tab's `sessionStorage`, AES-GCM-encrypted in the browser under the person's
     own key (`App\Support\AssessmentDraft`), and the idle logout waits for that save
     before reloading. Signing back in, New assessment offers **Resume** or **Discard
     it**; Submit, Discard and Sign out wipe it, and it is dropped after 2 hours or when
     someone else signs in to the tab. Officers and police admins alike. Needs https.
     Covered by `AssessmentDraftTest`.
