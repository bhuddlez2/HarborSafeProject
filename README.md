# HarborSafe

Two applications in one repository, backing Harbor Safe House & Advocacy Center:

| Path | What it is | Dev URL |
|---|---|---|
| `portal/backend/` | Laravel 13 JSON API, **and** the Filament staff panel — every officer, admin and secretary screen. | http://127.0.0.1:8000 · sign-in at [/login](http://127.0.0.1:8000/login) |
| `website/frontend/` | Next.js 16, static export. The public informational site, including the civilian lethality assessment at `/assessment/`. Calls the API from the browser for the contact forms, the events page and the assessment. | http://localhost:3000 |

If you are here to work on the staff portal, the code is in `portal/backend`
(PHP, Blade and Tailwind) — every officer and staff screen is in the Filament
panel. (A third app, `portal/frontend`, used to hold the civilian assessment;
it moved to the website and is gone.) See [PORTAL_SETUP.md](PORTAL_SETUP.md) if you have not
run PHP before.

Nothing but the backend talks to the database. The website reaches only a
narrow `/api/public/*` slice of the API and can never read anything back —
see [Schema_Reference.md](Schema_Reference.md).

---

## 1. Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.4.1+ | **Not from XAMPP on Windows** — it ships 8.2 at most, and the locked packages need 8.4.1. See [PORTAL_SETUP.md](PORTAL_SETUP.md) |
| Composer | 2.x | |
| Node.js | 20+ | Next.js 16 and the staff panel's stylesheet build need it. Build time only: the running backend never calls Node (PDFs are pure PHP) |
| MariaDB or MySQL | 10.4+ / 8.0+ | **See the note below — you may already have this running.** On Windows, XAMPP is a fine way to get just this |

### PHP's `intl` extension

The Filament staff panel needs PHP's `intl` extension. Without it, `composer
install` refuses to resolve with:

```
- filament/support[v5.7.6, ..., v5.8.4] require ext-intl * -> it is missing from
  your system. Install or enable PHP's intl extension.
```

**It's a one-line fix, not a dependency problem.** Enable it for your platform:

- **Fedora/RHEL** — it's a separate package: `sudo dnf install php-intl`
- **Windows** — XAMPP's PHP is too old for this project, so PHP gets
  installed separately, and it arrives with *no* `php.ini` and every extension
  switched off. `intl` is one of eight lines to uncomment, not one. Full Windows
  setup is in [PORTAL_SETUP.md](PORTAL_SETUP.md).
- **Debian/Ubuntu** — `sudo apt install php-intl` (or `php8.x-intl` to match
  your PHP version)

Verify (must print `bool(true)`):

```bash
php -r "var_dump(extension_loaded('intl'));"
```

If it still prints `false`, run `php --ini` to see which `php.ini` the CLI
actually loads — it isn't always the one Apache uses.

### File uploads

Event images and newsletter PDFs are stored **in the database** (a `content_files` table
on the `Content` connection), not on a disk and not in cloud storage. No account, no
credentials, and nothing to re-run after a deploy — a database dump is a complete
backup. See `Filament_CMS_Design.md` §4 for why.

**You must raise two php.ini limits or uploads fail confusingly.** The defaults are
smaller than the panel allows, and PHP discards an oversized file *before* Laravel runs
— so you get an opaque error rather than the panel's own "file too large" message:

```ini
upload_max_filesize = 8M   ; default is 2M
post_max_size = 12M        ; default is 8M, and must stay above the line above
```

Caps are 4 MB for an image and 8 MB for a PDF. Both sit under MariaDB's
`max_allowed_packet` (16 MB by default), which bounds a single row in *both* directions
— a blob that squeezes in past it can still fail to read back out.

`php artisan storage:link` is no longer required by this feature. It is still worth
running on a new environment, since Laravel's own tooling assumes it.

### Is my database already running?

Very likely yes, and not as part of XAMPP. Check:

```bash
# Windows - is the service running, and does it start on boot?
powershell "Get-Service | Where-Object { $_.Name -match 'maria|mysql' } | Select Name,Status,StartType"
```

A standalone **MariaDB** install registers a Windows service with
`StartType: Automatic`, so it starts with Windows and stays running in the
background — you never open XAMPP's control panel and never notice it. That's
separate from (and independent of) XAMPP's own MySQL, even if you use XAMPP's
PHP. If the service says `Running`, you're set; skip installing anything.

To confirm the API can actually reach it:

```bash
cd portal/backend
php artisan migrate:status --database=Portal
```

---

## 2. Backend setup (`portal/backend/`)

Run these once:

```bash
cd portal/backend
composer install
cp .env.example .env
php artisan key:generate
```

Then edit `.env` and fill in the database section. **Three** physical databases
are needed — create them if they don't exist:

These are the names `.env.example` ships with and the ones every other
document here uses, so this works unedited:

```sql
CREATE DATABASE assessment_app_db;   -- the Portal connection
CREATE DATABASE feedback_app_db;     -- the Feedback + FeedbackPublic connections
CREATE DATABASE content_app_db;      -- the Content + ContentPublic connections
```

Or skip the SQL entirely: `php artisan db:create` reads those three
`DB_DATABASE_*` values and creates whichever are missing. On Linux, where
MariaDB authenticates root over a unix socket and the passwordless root in
`.env` fails, use [docs/local-db-setup.sql](docs/local-db-setup.sql) instead —
it creates the databases and a dedicated dev user.

Nothing in the application reads a database name — only `.env` does — so you
*can* call them something else. Don't: every doc and GRANT block here assumes
these names, and a teammate reading your `.env` should recognise it.

The `.env` keys that matter:

```
DB_HOST_PORTAL / DB_PORT_PORTAL / DB_DATABASE_PORTAL / DB_USERNAME_PORTAL / DB_PASSWORD_PORTAL
DB_HOST_FEEDBACK / DB_PORT_FEEDBACK / DB_DATABASE_FEEDBACK / DB_USERNAME_FEEDBACK / DB_PASSWORD_FEEDBACK
DB_USERNAME_FEEDBACK_PUBLIC / DB_PASSWORD_FEEDBACK_PUBLIC
DB_HOST_CONTENT / DB_PORT_CONTENT / DB_DATABASE_CONTENT / DB_USERNAME_CONTENT / DB_PASSWORD_CONTENT
DB_USERNAME_CONTENT_PUBLIC / DB_PASSWORD_CONTENT_PUBLIC
```

`FeedbackPublic` points at the *same database* as `Feedback` but with a
deliberately restricted MySQL user — that's what makes "the website can only
ever write, never read" a database-enforced guarantee. **The public contact
forms will not work until that user exists.** Create it with the `GRANT` block
in [Schema_Reference.md](Schema_Reference.md#two-connections-one-database--how-write-only-is-enforced),
then put its credentials in `DB_USERNAME_FEEDBACK_PUBLIC` / `DB_PASSWORD_FEEDBACK_PUBLIC`.

`ContentPublic` works the same way for the events/newsletters database — a
SELECT-only user, so the website can read published content but never change
it. That user **does not exist in any environment yet**; until it does, leave
`DB_USERNAME_CONTENT_PUBLIC` / `DB_PASSWORD_CONTENT_PUBLIC` pointing at the
same credentials as `DB_*_CONTENT` and nothing breaks locally. The `GRANT`
block is in [Schema_Reference.md](Schema_Reference.md#contentpublic--the-read-only-half).

Migrate and seed:

```bash
php artisan migrate --database=Portal     # ALWAYS --database=Portal - see gotchas
php artisan db:seed --class=ServiceSeeder
php artisan db:seed --class=ResourceSeeder
php artisan db:seed --class=CountySeeder
php artisan db:seed --class=EventCategorySeeder
```

Those seeders fill the lookup tables — services / resources / counties for the
website's contact forms, event categories for the events page. Without them
those dropdowns and badges come up empty. All are idempotent, so they're safe
to re-run.

**Then create accounts, or you cannot get into the staff panel.** There is no
sign-up screen: `/login` will reject everything until users exist.

```bash
php artisan db:seed --class=LocalStaffUserSeeder --database=Portal
```

That creates one login per role (plus a deactivated account, for checking that
`is_active` is enforced). It refuses to run unless `APP_ENV=local`. The password
comes from `LOCAL_SEED_PASSWORD` in `.env`, falling back to `password` when
that is unset.

| Email | Role | Lands on |
|---|---|---|
| `officer@harborsafe.test` | `law_enforcement` | `/police` — **the only role that sees the officer screens and the assessment wizard** |
| `police-admin@harborsafe.test` | `police_admin` | `/police` |
| `admin@harborsafe.test` | `admin` | `/content` |
| `secretary@harborsafe.test` | `secretary` | `/content` |
| `inactive@harborsafe.test` | `admin`, deactivated | nothing — refused at the login screen |

Build the staff panel's theme (the Filament panel, served from the site root — officer
screens, the assessment wizard, content management — is styled by a custom
Tailwind theme in `resources/css/filament/staff/theme.css`, compiled by Vite):

```bash
npm install
npm run build            # re-run after changing theme.css or the panel's Blade views
```

Without this build the panel fails to load its stylesheet. Use `npm run dev`
instead while editing styles, so changes recompile live.

The Download PDF buttons need nothing from npm. They print in pure PHP through
dompdf (a Composer package), because the production host is shared hosting
that can't run Node or Chrome.

Start it:

```bash
php artisan serve        # http://127.0.0.1:8000, sign-in at /login
```

---

## 3. Website (`website/frontend/`)

The public site: home, about, events, resources, get support, and the
**Contact page with the two public forms**.

```bash
cd website/frontend
npm install
npm run dev              # http://localhost:3000
```

**Three parts of this site need the backend running (step 2).** All fetch from
the visitor's browser — it is a static export, so there is no server to fetch
on its behalf:

| Page | Reads | Writes |
|---|---|---|
| Contact (the two forms) | `GET /api/public/{services,resources,counties}` | `POST /api/public/{resource-requests,service-feedback}` |
| Events & News | `GET /api/public/{events,newsletters}` and `/api/public/content-files/{id}` for images | — |
| The civilian assessment (§4) | `GET /api/public/relationships` | `POST /api/public/civilian-assessments` |

Without the backend up, the contact forms and the assessment cannot load their
dropdowns and the events page shows its error state with the crisis line after
a 12-second timeout. That is the intended behaviour, not a bug — but it does mean
`npm run dev` alone is not enough to see those pages working.

Events and newsletters come from the staff panel, so the page is empty until
someone publishes something there. Empty is a normal state and the page says so.

The API base URL defaults to `http://127.0.0.1:8000`, so nothing to configure
locally. It is inlined at build time, so on a deploy target it must be set
**before** `npm run build`. To point at a different backend, create
`website/frontend/.env.local`:

```
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000
```

---

## 4. The civilian assessment

The public lethality assessment is a page of the website, at
[/assessment/](http://localhost:3000/assessment/), linked from Get Support. It
submits in one request to `POST /api/public/civilian-assessments`, which writes
through the restricted `PortalPublic` database user (INSERT-only, see
[Schema_Reference.md](Schema_Reference.md)). Locally that user is optional: with
`DB_USERNAME_PORTAL_PUBLIC` unset and `APP_ENV=local`, the backend uses the
Portal credentials. On any other server it must be set.

The backend's CORS config allows `http://localhost:3000` in local dev; set
`WEBSITE_URL` in the backend's `.env` for anything else.

---

## 5. Running everything at once

Two terminals:

```bash
# 1 - API
cd portal/backend && php artisan serve

# 2 - website (including the civilian assessment)
cd website/frontend && npm run dev
```

Then: website http://localhost:3000 (assessment at /assessment/),
API http://127.0.0.1:8000/api, staff panel http://127.0.0.1:8000 (sign-in at /login).

---

## 6. Tests

```bash
# Backend (Pest) - from portal/backend
php artisan test
php artisan test tests/Feature/Public/        # just the public form endpoints
php artisan test --filter='throttled'         # single test by name

# End-to-end (Playwright) - from the repo root
npm install
npx playwright test
```

Backend tests run against your **real local database**, not an in-memory one —
the app's named connections (`Portal`, `Feedback`, `FeedbackPublic`) have no
sqlite stand-in configured. The `tests/Feature/Public/` tests create their own
throwaway rows and delete them again afterwards, so they don't leave residue,
but the database does have to be running and migrated.

Playwright boots `website/frontend` only — it doesn't exercise the portal or
the backend.

The website has no test runner of its own; Playwright is its test suite.

---

## 7. Gotchas worth knowing before they bite

**`php artisan test` should pass in full; any failure is real.**
`tests/Feature/ExampleTest.php` checks Laravel's `/up` health route, not `/`: `/` is the
staff panel root, which redirects an anonymous visitor to `/login` (302), so the stock
scaffold's `GET /` → 200 assertion failed for months before it was repointed.

**Always migrate with `--database=Portal`,** whatever connection the migration
actually targets. Portal's `migrations` table is this project's single
canonical ledger. Running `migrate --database=Feedback` against a database
whose own `migrations` table is empty makes Laravel replay the *entire*
migration history into it. This has happened once already.

**Editing an already-run migration doesn't change an already-migrated
database.** Laravel only tracks migration *filenames*. If you change a
migration that has already run, that environment needs a deliberate
`migrate:fresh --database=Portal` or a new migration. Check with
`php artisan migrate:status --database=Portal` rather than assuming.

**The root `package.json` is not a runnable app.** Its `dev`/`build`/`start`
scripts are leftovers from before the repo was split; there's no `app/` at the
root. The root exists to host the shared ESLint config and the Playwright
setup. `npm run lint` at the root lints the website and the specs together — but it
needs `npm install` at the root first.

**Saving an unfinished officer assessment needs a secure address.** The wizard
keeps an unfinished form in the browser tab, encrypted with the browser's Web
Crypto, which browsers only allow over https or on `localhost` / `127.0.0.1`.
Opened over plain http on a network address (another device on your Wi-Fi, say),
nothing is saved and the wizard behaves as it did before. Production must be https.

**Granting a new table to the restricted user is manual.** Migrations can't
issue MySQL `GRANT`s. If you add a table the public forms write to, add the
matching `GRANT INSERT` (see `Schema_Reference.md`) or submissions will fail
with a permissions error at runtime.

---

## 8. Where to read next

- [Schema_Reference.md](Schema_Reference.md) — full schema, ER diagrams,
  table-by-table notes, and the restricted-user `GRANT` block. Much easier to
  read than the migration files.
- [CLAUDE.md](CLAUDE.md) — architecture notes and conventions.
