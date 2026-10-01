# Running the staff portal on Windows

For a frontend developer who has not run PHP before. Gets you from nothing to a
working staff portal you can log into and restyle. About 30 minutes, most of it
downloads.

The [README](README.md) is the full reference for the whole repo. This covers
the part it assumes you already have: PHP on your machine.

---

## 1. What you are actually working on

The officer screens used to be Next.js pages in `portal/frontend`. They aren't
any more — they moved into a **Filament panel** that the Laravel backend
serves. So the portal UI now lives in `portal/backend`, and it is:

- **Blade templates** — HTML files with `{{ }}` for values and `@if` / `@foreach`
  for logic. Close enough to JSX that you will be fine.
- **Tailwind** — the same utility classes you already use. This is most of the
  visual work and it transfers directly.
- **PHP page classes** — small files that say which template to render and who
  may see it.

`portal/frontend` still exists but is down to one page: the anonymous civilian
assessment at `/`. Visiting `/login`, `/admin` or `/police` there just
redirects you to the panel.

You do **not** need to learn PHP to restyle these screens. You mostly need the
PHP toolchain installed so you can run the app and see your changes.

---

## 2. Install three things

### XAMPP — gives you PHP and MySQL in one installer

Download from [apachefriends.org](https://www.apachefriends.org/) and install
to the default `C:\xampp`.

You only need two pieces of it:

- the **PHP** binary, to run the app
- **MySQL**, for the database

**You do not need to configure Apache.** Laravel ships its own dev server. Do
not spend time on virtual hosts or `htdocs` — nothing in this project goes
there.

After installing, open the **XAMPP Control Panel** and press **Start** next to
**MySQL**. Leave Apache stopped. MySQL has to be running whenever you work on
the project, and it does not start on boot by default.

### Composer — PHP's package manager (PHP's `npm`)

Download `Composer-Setup.exe` from
[getcomposer.org/download](https://getcomposer.org/download/) and run it. When
it asks for your PHP executable, point it at `C:\xampp\php\php.exe`.

The installer adds itself to your PATH, so there is nothing more to do for it.

### Node.js 20+

From [nodejs.org](https://nodejs.org/) if you don't already have it. You
probably do.

---

## 3. Add PHP to your PATH

The XAMPP installer does **not** do this, so `php` won't be recognised in a
terminal until you add it yourself. This is the step people get stuck on.

1. Press Start, type `environment`, open **Edit the system environment
   variables**
2. Click **Environment Variables…**
3. Under **User variables**, select **Path**, click **Edit**
4. Click **New** and add:

   ```
   C:\xampp\php
   ```

5. OK out of all three dialogs
6. **Close every open terminal and open a new one.** PATH changes only apply to
   new terminals — this is the usual reason it "didn't work"

Check it:

```bash
php -v          # should print PHP 8.3 or newer
composer -V     # should print Composer 2.x
node -v         # should print v20 or newer
```

If `php -v` still fails, your XAMPP is somewhere other than `C:\xampp` — use
that path instead.

---

## 4. Turn on one PHP extension

The panel needs PHP's `intl` extension. It ships with XAMPP but is switched
off, and **`composer install` will refuse to run without it** with a wall of red
about `filament/support requires ext-intl`.

1. Open `C:\xampp\php\php.ini` in your editor
2. Find the line `;extension=intl` (search for `extension=intl`)
3. Delete the leading semicolon so it reads `extension=intl`
4. Save

Check it — this must print `bool(true)`:

```bash
php -r "var_dump(extension_loaded('intl'));"
```

If it prints `false`, run `php --ini` to see which `php.ini` your terminal
actually loads. It is not always the one you just edited.

---

## 5. Set up the project

From the repo root:

```bash
cd portal\backend

composer install
copy .env.example .env
php artisan key:generate
```

`.env` holds your local settings and is never committed. With default XAMPP the
database section already works as shipped — user `root`, blank password — so
you shouldn't need to edit anything.

Create the databases, run the migrations, and load the starter data:

```bash
php artisan db:create

php artisan migrate --database=Portal

php artisan db:seed --class=ServiceSeeder
php artisan db:seed --class=ResourceSeeder
php artisan db:seed --class=CountySeeder
php artisan db:seed --class=EventCategorySeeder
php artisan db:seed --class=LocalStaffUserSeeder --database=Portal
```

`--database=Portal` on `migrate` is not optional — see the README's gotchas for
why. That last seeder creates the accounts you log in with; without it there is
no way into the panel, because there is no sign-up screen.

Build the panel's stylesheet:

```bash
npm install
npm run build
```

Create the uploads symlink:

```bash
php artisan storage:link
```

Start it:

```bash
php artisan serve
```

Open **http://127.0.0.1:8000/staff**.

---

## 6. Log in

Password for all of these is `password`.

| Email | What you'll see |
|---|---|
| `officer@harborsafe.test` | **Use this one.** The officer home and the assessment wizard — the only screens that are actually built |
| `admin@harborsafe.test` | A "Coming soon" page |
| `secretary@harborsafe.test` | The same "Coming soon" page |

Most of the panel is still placeholders. The officer screens are the real work.

---

## 7. Where the files are

Paths are from `portal/backend`.

**The templates — this is where you'll spend your time:**

```
resources/views/filament/pages/
    police.blade.php               the officer home, fully built
    new-assessment.blade.php       the assessment wizard shell
    new-assessment/                its pieces, one file each
        heading.blade.php     intro.blade.php      officer.blade.php
        questions.blade.php   review.blade.php     submit-button.blade.php
    content-management.blade.php   placeholder
    officer-placeholder.blade.php  My Assessments and Search Records
    auth/login.blade.php           the sign-in screen

resources/views/filament/partials/   small shared pieces
resources/views/components/          officer-icon.blade.php, the SVG icons
```

**The stylesheet:**

```
resources/css/filament/staff/theme.css
```

Colours, fonts and the sidebar live here. `#5C0F8B` is the brand purple.

**The page classes — mostly leave these alone:**

```
app/Filament/Pages/
    Police.php  NewAssessment.php  MyAssessments.php  SearchRecords.php
    ContentManagement.php  Auth/Login.php
```

Each one names its template, its sidebar icon and its position, and which roles
may open it. `police.blade.php` next to `Police.php` is worth reading together
once — the class is tiny and the template is ordinary Tailwind markup.

**The panel's shell** (sidebar, fonts, colours, nav order):

```
app/Providers/Filament/StaffPanelProvider.php
```

---

## 8. Working on styles

Run this instead of `npm run build` while you work, and it recompiles as you
save:

```bash
npm run dev
```

Leave it running in its own terminal alongside `php artisan serve`.

**The one trap:** Tailwind only generates the classes it can find by scanning
files, and it is told which folders to scan at the top of `theme.css`:

```css
@source '../../../../app/Filament/**/*';
@source '../../../../resources/views/filament/**/*';
@source '../../../../resources/views/components/officer-icon.blade.php';
```

Anything you add under `resources/views/filament/` is picked up automatically.
But `resources/views/components/` lists **one file by name** — so a new
component there gets no styling at all until you add an `@source` line for it.
If classes are mysteriously not applying, check this first.

---

## 9. When something breaks

| What you see | What it means |
|---|---|
| `php` is not recognised | PATH (step 3), or you didn't open a new terminal |
| `requires ext-intl` during `composer install` | Step 4 |
| `No application encryption key` | You skipped `php artisan key:generate` |
| `SQLSTATE[HY000] [2002]` or connection refused | MySQL isn't running — start it in the XAMPP Control Panel |
| `Unknown database` | Run `php artisan db:create` |
| Login rejects every password | You skipped `LocalStaffUserSeeder` |
| Panel loads but looks unstyled | You skipped `npm run build` |
| Your new Tailwind classes do nothing | Section 8, the `@source` trap |
| Uploaded images 404 | You skipped `php artisan storage:link` |

Two commands worth knowing when something is stale:

```bash
php artisan config:clear      # after editing .env
php artisan view:clear        # if a Blade change seems not to apply
```

---

## 10. Running the rest

You only need the backend for panel work. If you want everything up, three
terminals:

```bash
cd portal\backend   && php artisan serve     # API + panel  :8000
cd website\frontend && npm run dev           # public site  :3000
cd portal\frontend  && npm run dev           # civilian flow :3001
```

Both frontends need `npm install` first.

---

## Where to read next

- [README.md](README.md) — the whole repo, all three apps, tests
- [Filament_CMS_Design.md](Filament_CMS_Design.md) — what the panel is meant to
  become, which roles see what, and what is still unbuilt
