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

## 2. Install four things

> **Read this bit before downloading anything.** XAMPP is the obvious choice on
> Windows and it is *not sufficient on its own*. XAMPP for Windows ships at most
> **PHP 8.2.12**, and this project needs **PHP 8.3 or newer** — Laravel 13
> itself requires it, so there is no way to lower the bar. You install XAMPP for
> the database, and PHP separately.

### XAMPP — for MariaDB only

Download from [apachefriends.org](https://www.apachefriends.org/) and install
to the default `C:\xampp`.

You need exactly one piece of it: **MySQL** (really MariaDB). After installing,
open the **XAMPP Control Panel** and press **Start** next to **MySQL**. Leave
Apache stopped, and ignore the PHP that came with it.

**Do not configure Apache.** Laravel ships its own dev server. Nothing in this
project goes in `htdocs`, and you never need a virtual host.

MySQL must be running whenever you work on the project, and it does not start
on boot by default.

### PHP 8.3+ — installed separately

From [windows.php.net/download](https://windows.php.net/download/), take a
**Non-Thread-Safe (NTS), x64 Zip** of PHP 8.3 or newer. NTS is the right build
for the command line and `php artisan serve`; the thread-safe one is only for
running PHP inside Apache, which we aren't.

Extract it to **`C:\php`**. Keeping it out of `C:\xampp\php` matters: a XAMPP
update would otherwise overwrite your PHP and put you back on 8.2.

### Composer — PHP's package manager (PHP's `npm`)

Download `Composer-Setup.exe` from
[getcomposer.org/download](https://getcomposer.org/download/) and run it. When
it asks for your PHP executable, point it at **`C:\php\php.exe`** — not the one
in XAMPP. Do this *after* step 4, or Composer will complain about missing
extensions.

The installer adds itself to your PATH.

### Node.js 20+

From [nodejs.org](https://nodejs.org/) if you don't already have it. You
probably do.

---

## 3. Add PHP to your PATH

Nothing does this for you, so `php` won't be recognised in a terminal until you
add it. This is the step people get stuck on.

1. Press Start, type `environment`, open **Edit the system environment
   variables**
2. Click **Environment Variables…**
3. Under **User variables**, select **Path**, click **Edit**
4. Click **New** and add:

   ```
   C:\php
   ```

5. If `C:\xampp\php` is already in the list, **remove it** — otherwise you may
   get XAMPP's PHP 8.2 instead and nothing will make sense
6. OK out of all three dialogs
7. **Close every open terminal and open a new one.** PATH changes only apply to
   new terminals — this is the usual reason it "didn't work"

Check it:

```bash
php -v          # must print 8.3 or newer
node -v         # v20 or newer
```

If `php -v` prints 8.2.x you are still getting XAMPP's copy — recheck step 5.

---

## 4. Create php.ini and turn on seven extensions

**A PHP zip from windows.php.net has no `php.ini` at all**, and every optional
extension is switched off. XAMPP pre-enables these, which is why most guides
only mention `intl`. You have to do all of it yourself.

First create the file:

```bash
copy C:\php\php.ini-development C:\php\php.ini
```

Then open `C:\php\php.ini` and uncomment these **eight** lines by deleting the
leading `;`. Search for each one:

```ini
extension_dir = "ext"      ; near line 758 — without this none of the rest load

extension=curl
extension=fileinfo
extension=intl             ; the staff panel needs this one
extension=mbstring
extension=openssl
extension=pdo_mysql        ; without this there is no database at all
extension=zip
```

**Then change two values that are already uncommented** — these are not
extensions, they are size limits, and the defaults are too small for the
panel's uploads:

```ini
upload_max_filesize = 8M   ; default is 2M
post_max_size = 12M        ; default is 8M — must stay above the line above
```

Event images are capped at 4 MB and newsletter PDFs at 8 MB. With the stock
`upload_max_filesize = 2M`, **PHP throws the file away before Laravel ever
sees it**, so you get an unhelpful error instead of the panel's own "file too
large" message. `post_max_size` bounds the whole request rather than the one
file, so it has to stay larger than `upload_max_filesize`.

Save. Then check — this lists anything still missing, so you want an **empty**
array:

```bash
php -r "var_dump(array_diff(['curl','fileinfo','intl','mbstring','openssl','pdo_mysql','zip'], get_loaded_extensions()));"
```

```
array(0) {
}
```

That is what success looks like. Anything named in the output is still
commented out.

And check the two limits separately:

```bash
php -r "printf('upload=%s post=%s%s', ini_get('upload_max_filesize'), ini_get('post_max_size'), PHP_EOL);"
```

```
upload=8M post=12M
```

If extensions you uncommented are still missing, run `php --ini` and confirm
"Loaded Configuration File" says `C:\php\php.ini`. If it says `(none)`, the
copy above didn't land in the right place.

`composer install` fails with a different error for each missing one, and the
messages don't always name the extension clearly — so it's worth getting all
eight right before moving on.

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

Create the storage symlink. Uploads do not need it — event images and
newsletter PDFs go in the database — but Laravel's own tooling assumes it
exists, so run it once:

```bash
php artisan storage:link
```

Start it:

```bash
php artisan serve
```

Open **http://127.0.0.1:8000** — you will be sent to the sign-in page at `/login`.

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
    officer-placeholder.blade.php  Search Records
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
    Police.php  NewAssessment.php  SearchRecords.php
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
| `php -v` prints **8.2.x** | You're getting XAMPP's PHP — remove `C:\xampp\php` from PATH (step 3.5) |
| `requires php ^8.3` / `your php version (8.2…) does not satisfy` | Same cause as above |
| `php --ini` says Loaded Configuration File **(none)** | You didn't copy `php.ini-development` to `php.ini` (step 4) |
| `requires ext-intl` during `composer install` | Step 4 — and check the other seven while you're there |
| `could not find driver` on any database command | `extension=pdo_mysql` still commented out (step 4) |
| `No application encryption key` | You skipped `php artisan key:generate` |
| `SQLSTATE[HY000] [2002]` or connection refused | MySQL isn't running — start it in the XAMPP Control Panel |
| `Unknown database` | Run `php artisan db:create` |
| Login rejects every password | You skipped `LocalStaffUserSeeder` |
| Panel loads but looks unstyled | You skipped `npm run build` |
| Your new Tailwind classes do nothing | Section 8, the `@source` trap |
| An upload fails with an unhelpful error | `upload_max_filesize` / `post_max_size` too low (step 4). PHP discards the file before Laravel runs, so you never see the panel's own "too large" message |
| `npm run dev` on a frontend dies on `globals.css` with "the module factory is not available" | Nothing to do with your browser cache, despite what it says. Stop the dev server, delete that frontend's `.next` directory, and start again. It happens when an old postcss from the repo root's `node_modules` shadows the frontend's own — both `next.config.mjs` files pin `turbopack.root` to prevent it, so if you hit this, check that pin is still there |
| The upload box says "up to 2 MB" when it should say 4 | Same cause — the panel prints whatever php.ini will actually accept rather than over-promising |

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
