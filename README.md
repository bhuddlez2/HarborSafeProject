# HarborSafe

Three applications in one repository, backing Harbor Safe House & Advocacy Center:

| Path | What it is | Dev URL |
|---|---|---|
| `portal/backend/` | Laravel 13 JSON API. Backs **both** other apps. | http://127.0.0.1:8000 |
| `website/frontend/` | Next.js 16, static export. The public informational site. | http://localhost:3000 |
| `portal/frontend/` | Next.js 16, server mode. The staff/law-enforcement portal. | http://localhost:3001 |

Nothing but the backend talks to the database. The website reaches only a
narrow `/api/public/*` slice of the API and can never read anything back —
see [Schema_Reference.md](Schema_Reference.md).

---

## 1. Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3+ | Comes with XAMPP; `php -v` to check |
| Composer | 2.x | |
| Node.js | 20+ | Next.js 16 requires it |
| MariaDB or MySQL | 10.4+ / 8.0+ | **See the note below — you may already have this running** |

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

Then edit `.env` and fill in the database section. Two physical databases are
needed — create them if they don't exist:

```sql
CREATE DATABASE assessment_app_db;   -- the Portal connection
CREATE DATABASE feedback_app_db;     -- the Feedback + FeedbackPublic connections
```

The `.env` keys that matter:

```
DB_HOST_PORTAL / DB_PORT_PORTAL / DB_DATABASE_PORTAL / DB_USERNAME_PORTAL / DB_PASSWORD_PORTAL
DB_HOST_FEEDBACK / DB_PORT_FEEDBACK / DB_DATABASE_FEEDBACK / DB_USERNAME_FEEDBACK / DB_PASSWORD_FEEDBACK
DB_USERNAME_FEEDBACK_PUBLIC / DB_PASSWORD_FEEDBACK_PUBLIC
```

`FeedbackPublic` points at the *same database* as `Feedback` but with a
deliberately restricted MySQL user — that's what makes "the website can only
ever write, never read" a database-enforced guarantee. **The public contact
forms will not work until that user exists.** Create it with the `GRANT` block
in [Schema_Reference.md](Schema_Reference.md#two-connections-one-database--how-write-only-is-enforced),
then put its credentials in `DB_USERNAME_FEEDBACK_PUBLIC` / `DB_PASSWORD_FEEDBACK_PUBLIC`.

Migrate and seed:

```bash
php artisan migrate --database=Portal     # ALWAYS --database=Portal - see gotchas
php artisan db:seed --class=ServiceSeeder
php artisan db:seed --class=ResourceSeeder
php artisan db:seed --class=CountySeeder
```

Those three seeders fill the lookup tables (services / resources / counties)
that the website's contact forms read their dropdowns from. Without them the
forms load empty. They're idempotent — safe to re-run.

Start it:

```bash
php artisan serve        # http://127.0.0.1:8000
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

The contact forms need the backend running (step 2). They read their option
lists from `GET /api/public/{services,resources,counties}` and submit to
`POST /api/public/{resource-requests,service-feedback}`.

The API base URL defaults to `http://127.0.0.1:8000`, so nothing to configure
locally. To point at a different backend, create `website/frontend/.env.local`:

```
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000
```

---

## 4. Portal (`portal/frontend/`)

**There is a landing page** — `/` lists the two assessment flows. (It arrived
with the frontend merge; earlier there wasn't one.)

| Route | What it is |
|---|---|
| `/` | Landing page, links to both flows |
| `/civilian` | Civilian (anonymous) assessment |
| `/law-enforcement` | Law-enforcement assessment |

```bash
cd portal/frontend
npm install
npm run dev -- -p 3001   # http://localhost:3001
```

**Use `-p 3001`.** Both frontends default to port 3000, so without it the
second one you start either fails or silently picks another port. The backend's
CORS config allows 3000 and 3001 in local dev, so 3001 works out of the box;
any other port needs adding to `PORTAL_URL` in the backend's `.env`.

---

## 5. Running everything at once

Three terminals:

```bash
# 1 - API
cd portal/backend && php artisan serve

# 2 - website
cd website/frontend && npm run dev

# 3 - portal
cd portal/frontend && npm run dev -- -p 3001
```

Then: website http://localhost:3000, portal http://localhost:3001,
API http://127.0.0.1:8000/api.

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

Neither frontend has a test runner configured.

---

## 7. Gotchas worth knowing before they bite

**`php artisan test` fails exactly one test, on purpose.**
`tests/Feature/ExampleTest.php` is Pest's stock scaffold test asserting
`GET /` returns 200; `routes/web.php` is empty so it genuinely 404s. Expected
until it's rewritten against a real route. Anything *else* failing is real.

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
setup. `npm run lint` at the root lints both frontends and the specs together —
but it needs `npm install` at the root first.

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
